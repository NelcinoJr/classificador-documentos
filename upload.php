<?php
session_start();
require 'vendor/autoload.php';
require 'config.php';

// Estimativas de preços fictícias/baseadas na documentação para 2026 por 1 Milhão de Tokens (USD)
$pricing = [
    'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30],
    'gemini-1.5-pro'   => ['input' => 1.25,  'output' => 5.00],
    'gemini-2.5-flash' => ['input' => 0.15,  'output' => 0.60],
    'gemini-2.5-pro'   => ['input' => 1.50,  'output' => 6.00]
];

$dolar_hoje = 5.00; // Taxa de conversão para BRL

function callGeminiAPI($text, $model, $apiKey) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    
    $prompt = "Você é um classificador inteligente de documentos contábeis.
Abaixo está o texto extraído de um arquivo PDF.
Analise o texto e extraia as seguintes informações em formato JSON estrito (não use formatação Markdown como ```json, retorne apenas o objeto JSON):
- tipo_solicitacao: 'Tributo' ou 'Outros'
- subtipo: Qual é o documento? (Ex: DAS_MEI, INSS, DARF, FGTS, Nota_Fiscal, Relatorio, Desconhecido)
- competencia: Mês e ano de referência do documento (ex: 03/2026). Retorne null se não achar.
- vencimento: Data de vencimento no formato YYYY-MM-DD. Retorne null se não achar.
- valor: Valor total a pagar numérico (ex: 710.31). Retorne null se não achar.
- cnpj: CNPJ da empresa que consta no documento. Retorne null se não achar.

Texto do documento:
" . $text;

    $data = [
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ],
        "generationConfig" => [
            "response_mime_type" => "application/json",
            "temperature" => 0.1
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200) {
        throw new Exception("Erro na API do Gemini (HTTP $httpCode): " . $response);
    }

    return json_decode($response, true);
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_files'])) {
    
    $apiKey = $_POST['api_key'] ?? '';
    $model = $_POST['gemini_model'] ?? 'gemini-1.5-flash';

    if (empty($apiKey)) {
        header("Location: index.php?error=" . urlencode("Por favor, forneça sua chave da API do Gemini."));
        exit;
    }

    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $files = $_FILES['pdf_files'];
    $fileCount = count($files['name']);
    
    $batchResults = [];
    $totalAiCalls = 0;
    $totalInputTokensLote = 0;
    $totalOutputTokensLote = 0;
    $totalCostUsdLote = 0;

    $parser = new \Smalot\PdfParser\Parser();

    for ($i = 0; $i < $fileCount; $i++) {
        $tmpName = $files['tmp_name'][$i];
        $originalName = $files['name'][$i];
        
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        $uploadPath = $uploadDir . uniqid() . '_' . basename($originalName);

        if (move_uploaded_file($tmpName, $uploadPath)) {
            try {
                // 1. Extração do Texto
                $pdf = $parser->parseFile($uploadPath);
                $text = $pdf->getText();

                if (empty(trim($text))) {
                    throw new Exception("PDF sem texto (imagem escaneada).");
                }

                // 2. Sempre chama o Gemini para extração completa
                $apiResponse = callGeminiAPI($text, $model, $apiKey);

                if (!isset($apiResponse['candidates'][0]['content']['parts'][0]['text'])) {
                    throw new Exception("Resposta inesperada da API.");
                }

                $jsonString = $apiResponse['candidates'][0]['content']['parts'][0]['text'];
                $jsonResultArray = json_decode($jsonString, true) ?: [];
                $processadoPor = 'IA';
                $totalAiCalls++;

                // Contabilizar Tokens do Arquivo
                $inputTokensDoc = $apiResponse['usageMetadata']['promptTokenCount'] ?? str_word_count($text);
                $outputTokensDoc = $apiResponse['usageMetadata']['candidatesTokenCount'] ?? 150;
                
                $totalInputTokensLote += $inputTokensDoc;
                $totalOutputTokensLote += $outputTokensDoc;

                // Calcular custo do arquivo
                $priceConfig = $pricing[$model] ?? $pricing['gemini-1.5-flash'];
                $costInputUsdDoc = ($inputTokensDoc / 1000000) * $priceConfig['input'];
                $costOutputUsdDoc = ($outputTokensDoc / 1000000) * $priceConfig['output'];
                $docCostUsd = $costInputUsdDoc + $costOutputUsdDoc;
                $docCostBrl = $docCostUsd * $dolar_hoje;
                
                $totalCostUsdLote += $docCostUsd;

                // Salvar na Sessão para exibir na tela
                $batchResults[] = [
                    'arquivo' => $originalName,
                    'processado_por' => $processadoPor,
                    'dados' => $jsonResultArray,
                    'custo_usd' => $docCostUsd,
                    'custo_brl' => $docCostBrl
                ];

                // 3. Salvar no Banco de Dados (SQLite)
                global $pdo;
                $stmt = $pdo->prepare("INSERT INTO documentos (nome_arquivo, tipo_solicitacao, subtipo, competencia, vencimento, valor, cnpj, processado_por, custo_usd, custo_brl) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $originalName,
                    $jsonResultArray['tipo_solicitacao'] ?? null,
                    $jsonResultArray['subtipo'] ?? null,
                    $jsonResultArray['competencia'] ?? null,
                    $jsonResultArray['vencimento'] ?? null,
                    $jsonResultArray['valor'] ?? null,
                    $jsonResultArray['cnpj'] ?? null,
                    $processadoPor,
                    $docCostUsd,
                    $docCostBrl
                ]);

            } catch (Exception $e) {
                $batchResults[] = [
                    'arquivo' => $originalName,
                    'processado_por' => 'Erro',
                    'dados' => ['subtipo' => 'Falha: ' . $e->getMessage()],
                    'custo_usd' => 0,
                    'custo_brl' => 0
                ];
            } finally {
                // Limpeza
                if (file_exists($uploadPath)) {
                    unlink($uploadPath);
                }
            }
        }
    }

    // Custos Totais do Lote
    $totalCostBrlLote = $totalCostUsdLote * $dolar_hoje;

    $_SESSION['batch_results'] = $batchResults;
    $_SESSION['batch_costs'] = [
        'ai_calls' => $totalAiCalls,
        'total_tokens' => $totalInputTokensLote + $totalOutputTokensLote,
        'total_cost_usd' => $totalCostUsdLote,
        'total_cost_brl' => $totalCostBrlLote
    ];

    header("Location: index.php?success=1");
    exit;

} else {
    header("Location: index.php");
    exit;
}
