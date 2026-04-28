<?php
session_start();
require 'vendor/autoload.php';

// Estimativas de preços fictícias/baseadas na documentação para 2026 por 1 Milhão de Tokens (USD)
$pricing = [
    'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30],
    'gemini-1.5-pro'   => ['input' => 1.25,  'output' => 5.00],
    'gemini-2.5-flash' => ['input' => 0.15,  'output' => 0.60],
    'gemini-2.5-pro'   => ['input' => 1.50,  'output' => 6.00],
    'gemini-3.0-ultra' => ['input' => 2.00,  'output' => 8.00],
    'gemini-3.1'       => ['input' => 2.50,  'output' => 10.00]
];

$dolar_hoje = 5.00; // Taxa de conversão para BRL

function callGeminiAPI($text, $model, $apiKey) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    
    // Prompt focado na estrutura solicitada
    $prompt = "Você é um classificador inteligente de documentos contábeis.
Abaixo está o texto extraído de um arquivo PDF.
Analise o texto e extraia as seguintes informações em formato JSON estrito (não use formatação Markdown como ```json, retorne apenas o objeto JSON):
- tipo_solicitacao: 'Tributo' ou 'Outros' (Se for guia de pagamento de imposto, é Tributo. Caso contrário, Outros)
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_file'])) {
    
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

    $tmpName = $_FILES['pdf_file']['tmp_name'];
    $fileName = basename($_FILES['pdf_file']['name']);
    $uploadPath = $uploadDir . uniqid() . '_' . $fileName;

    if (move_uploaded_file($tmpName, $uploadPath)) {
        try {
            // 1. Extração do Texto usando PDFParser
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($uploadPath);
            $text = $pdf->getText();

            if (empty(trim($text))) {
                throw new Exception("Não foi possível extrair texto do PDF. Talvez seja uma imagem escaneada sem OCR aplicado.");
            }

            // 2. Chamada à API do Gemini
            $apiResponse = callGeminiAPI($text, $model, $apiKey);

            if (!isset($apiResponse['candidates'][0]['content']['parts'][0]['text'])) {
                throw new Exception("Resposta inesperada da API: " . json_encode($apiResponse));
            }

            $jsonResult = $apiResponse['candidates'][0]['content']['parts'][0]['text'];
            
            // 3. Obter contagem de tokens do uso da API
            $inputTokens = $apiResponse['usageMetadata']['promptTokenCount'] ?? str_word_count($text); // Fallback to word count if API doesn't provide
            $outputTokens = $apiResponse['usageMetadata']['candidatesTokenCount'] ?? 150; // Fallback estimate

            // 4. Calcular custos baseados no modelo
            $priceConfig = $pricing[$model] ?? $pricing['gemini-1.5-flash'];
            $costInputUsd = ($inputTokens / 1000000) * $priceConfig['input'];
            $costOutputUsd = ($outputTokens / 1000000) * $priceConfig['output'];
            $totalCostUsd = $costInputUsd + $costOutputUsd;
            $totalCostBrl = $totalCostUsd * $dolar_hoje;

            $_SESSION['gemini_result'] = $jsonResult;
            $_SESSION['gemini_costs'] = [
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_cost_usd' => $totalCostUsd,
                'total_cost_brl' => $totalCostBrl
            ];

            // Limpeza
            unlink($uploadPath);

            header("Location: index.php?success=1");
            exit;

        } catch (Exception $e) {
            header("Location: index.php?error=" . urlencode($e->getMessage()));
            exit;
        }
    } else {
        header("Location: index.php?error=" . urlencode("Falha ao fazer upload do arquivo."));
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
