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

// Função de pré-classificação local para economizar custos
function classificadorLocal($text) {
    $textUpper = strtoupper($text);
    $result = [
        'tipo_solicitacao' => null,
        'subtipo' => null,
        'competencia' => null,
        'vencimento' => null,
        'valor' => null,
        'cnpj' => null
    ];

    // Regex comuns
    // CNPJ
    if (preg_match('/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}/', $text, $matches)) {
        $result['cnpj'] = $matches[0];
    }
    
    // Valor (formato brasileiro: 1.234,56 ou 123,45)
    if (preg_match('/(?:VALOR TOTAL|VALOR A PAGAR|VALOR COBRADO).*?(?:R\$)?\s*(\d{1,3}(?:\.\d{3})*,\d{2})/i', $text, $matches)) {
        $result['valor'] = str_replace(['.', ','], ['', '.'], $matches[1]);
    }

    // Identificação de DAS
    if (strpos($textUpper, 'DOCUMENTO DE ARRECADAÇÃO DO SIMPLES NACIONAL') !== false || strpos($textUpper, 'DAS') !== false) {
        $result['tipo_solicitacao'] = 'Tributo';
        $result['subtipo'] = 'DAS';
        return $result; // Retorna rápido se achar
    }

    // Identificação de INSS / GPS
    if (strpos($textUpper, 'GUIA DA PREVIDÊNCIA SOCIAL') !== false || strpos($textUpper, 'INSS') !== false) {
        $result['tipo_solicitacao'] = 'Tributo';
        $result['subtipo'] = 'INSS';
        return $result;
    }

    // Identificação de Nota Fiscal
    if (strpos($textUpper, 'NOTA FISCAL') !== false || strpos($textUpper, 'DANFE') !== false) {
        $result['tipo_solicitacao'] = 'Outros';
        $result['subtipo'] = 'Nota_Fiscal';
        return $result;
    }
    
    // Identificação de Extrato Bancário / OFX
    if (strpos($textUpper, 'EXTRATO') !== false || strpos($textUpper, 'OFX') !== false || strpos($textUpper, 'SALDO ANTERIOR') !== false) {
        $result['tipo_solicitacao'] = 'Outros';
        $result['subtipo'] = 'Extrato_Bancario';
        return $result;
    }

    // Se não encontrou regras suficientes, retorna null para o subtipo, obrigando a chamar a IA
    return null; 
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
    $totalInputTokens = 0;
    $totalOutputTokens = 0;

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

                // 2. Tentar Classificação Local Primeiro (Economia)
                $localResult = classificadorLocal($text);
                
                if ($localResult && $localResult['subtipo'] !== null) {
                    $jsonResultArray = $localResult;
                    $processadoPor = 'Local';
                } else {
                    // 3. Fallback: Se não identificou localmente, chama o Gemini
                    $apiResponse = callGeminiAPI($text, $model, $apiKey);

                    if (!isset($apiResponse['candidates'][0]['content']['parts'][0]['text'])) {
                        throw new Exception("Resposta inesperada da API.");
                    }

                    $jsonString = $apiResponse['candidates'][0]['content']['parts'][0]['text'];
                    $jsonResultArray = json_decode($jsonString, true) ?: [];
                    $processadoPor = 'IA';
                    $totalAiCalls++;

                    // Contabilizar Tokens
                    $totalInputTokens += $apiResponse['usageMetadata']['promptTokenCount'] ?? str_word_count($text);
                    $totalOutputTokens += $apiResponse['usageMetadata']['candidatesTokenCount'] ?? 150;
                }

                // Salvar na Sessão para exibir na tela
                $batchResults[] = [
                    'arquivo' => $originalName,
                    'processado_por' => $processadoPor,
                    'dados' => $jsonResultArray
                ];

                // 4. Salvar no Banco de Dados (SQLite)
                global $pdo;
                $stmt = $pdo->prepare("INSERT INTO documentos (nome_arquivo, tipo_solicitacao, subtipo, competencia, vencimento, valor, cnpj, processado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $originalName,
                    $jsonResultArray['tipo_solicitacao'] ?? null,
                    $jsonResultArray['subtipo'] ?? null,
                    $jsonResultArray['competencia'] ?? null,
                    $jsonResultArray['vencimento'] ?? null,
                    $jsonResultArray['valor'] ?? null,
                    $jsonResultArray['cnpj'] ?? null,
                    $processadoPor
                ]);

            } catch (Exception $e) {
                $batchResults[] = [
                    'arquivo' => $originalName,
                    'processado_por' => 'Erro',
                    'dados' => ['subtipo' => 'Falha: ' . $e->getMessage()]
                ];
            } finally {
                // Limpeza
                if (file_exists($uploadPath)) {
                    unlink($uploadPath);
                }
            }
        }
    }

    // Calcular Custos Totais do Lote
    $priceConfig = $pricing[$model] ?? $pricing['gemini-1.5-flash'];
    $costInputUsd = ($totalInputTokens / 1000000) * $priceConfig['input'];
    $costOutputUsd = ($totalOutputTokens / 1000000) * $priceConfig['output'];
    $totalCostUsd = $costInputUsd + $costOutputUsd;
    $totalCostBrl = $totalCostUsd * $dolar_hoje;

    $_SESSION['batch_results'] = $batchResults;
    $_SESSION['batch_costs'] = [
        'ai_calls' => $totalAiCalls,
        'total_tokens' => $totalInputTokens + $totalOutputTokens,
        'total_cost_usd' => $totalCostUsd,
        'total_cost_brl' => $totalCostBrl
    ];

    header("Location: index.php?success=1");
    exit;

} else {
    header("Location: index.php");
    exit;
}
