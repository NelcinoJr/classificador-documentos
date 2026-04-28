<?php
require 'config.php';
require 'vendor/autoload.php';

use Aws\Textract\TextractClient;

// Verifica se está rodando via CLI (Terminal)
if (php_sapi_name() !== 'cli') {
    die("Este script deve ser executado no terminal (CLI). Exemplo: php worker.php\n");
}

echo "Iniciando worker de processamento de documentos...\n";

// Inicializa o cliente AWS Textract
$textractClient = new TextractClient([
    'version'     => 'latest',
    'region'      => AWS_REGION,
    'credentials' => [
        'key'    => AWS_ACCESS_KEY,
        'secret' => AWS_SECRET_KEY,
    ],
]);

function callGeminiAPI($textoExtraido) {
    // Prompt para guiar o Gemini 2.5 Flash / Pro
    $systemInstruction = "Você é um assistente contábil especializado no Brasil. Eu vou enviar um texto extraído via OCR de um documento e você precisa classificar. Responda ESTRITAMENTE em formato JSON, sem crases markdown. A estrutura do JSON deve ser: {\"tipo_documento\": \"Tributo|Nota Fiscal|Bancario|Outros\", \"nome_documento\": \"Nome exato (Ex: DAS - Simples Nacional, Guia INSS, etc)\", \"competencia\": \"MM/AAAA ou null\", \"data_vencimento\": \"DD/MM/AAAA ou null\", \"valor\": \"0.00 ou null\"}";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . GEMINI_API_KEY;
    
    $payload = [
        "systemInstruction" => [
            "parts" => [
                ["text" => $systemInstruction]
            ]
        ],
        "contents" => [
            [
                "parts" => [
                    ["text" => "Texto do documento extraído:\n" . $textoExtraido]
                ]
            ]
        ],
        "generationConfig" => [
            "responseMimeType" => "application/json",
            "temperature" => 0.1
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Erro na API do Gemini: " . $response);
    }

    return json_decode($response, true);
}

// Loop infinito simulando um Daemon / Fila
while (true) {
    $docId = null;
    try {
        // 1. Busca o próximo documento pendente (apenas 1 por vez)
        $stmt = $pdo->query("SELECT id, caminho_arquivo FROM documentos WHERE status = 'pendente' ORDER BY id ASC LIMIT 1");
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            // Se não tiver nada, dorme por 5 segundos antes de tentar de novo
            echo date('H:i:s') . " - Nenhum documento pendente. Aguardando...\n";
            sleep(5);
            continue;
        }

        $docId = $doc['id'];
        $filePath = $doc['caminho_arquivo'];

        echo "\n[".date('H:i:s')."] Processando Documento #$docId...\n";

        // 2. Marca como 'processando'
        $pdo->prepare("UPDATE documentos SET status = 'processando' WHERE id = ?")->execute([$docId]);

        if (!file_exists($filePath)) {
            throw new Exception("Arquivo não encontrado no disco: $filePath");
        }

        // 3. AWS Textract: Extrai o Texto Local Sincronamente
        echo "  - Extraindo texto com AWS Textract...\n";
        $fileBytes = file_get_contents($filePath);
        
        $textractResult = $textractClient->analyzeDocument([
            'Document' => [
                'Bytes' => $fileBytes
            ],
            'FeatureTypes' => ['FORMS'] // ou apenas usar detectDocumentText para texto puro e mais barato
        ]);

        $textoExtraido = "";
        if (!empty($textractResult['Blocks'])) {
            foreach ($textractResult['Blocks'] as $block) {
                if ($block['BlockType'] === 'LINE') {
                    $textoExtraido .= $block['Text'] . "\n";
                }
            }
        }

        if (empty(trim($textoExtraido))) {
            throw new Exception("Textract não conseguiu extrair nenhum texto legível do arquivo.");
        }

        // 4. Google Gemini 2.5: Envia o texto para classificação
        echo "  - Enviando para o Gemini 2.5...\n";
        $geminiResponse = callGeminiAPI($textoExtraido);

        // 5. Analisando Retorno e Calculando Custos
        $candidates = $geminiResponse['candidates'][0] ?? null;
        $usageMetadata = $geminiResponse['usageMetadata'] ?? null;

        if (!$candidates) {
            throw new Exception("Gemini não retornou dados válidos: " . json_encode($geminiResponse));
        }

        // Parse do JSON que a IA devolveu
        $textoRespostaGemini = $candidates['content']['parts'][0]['text'];
        $jsonLimpo = trim(str_replace(['```json', '```'], '', $textoRespostaGemini));
        $classificacaoArray = json_decode($jsonLimpo, true);
        
        $tipoDocumento = $classificacaoArray['tipo_documento'] ?? 'Desconhecido';
        
        // Cálculo do Custo de Tokens
        $custoEstimado = 0.0;
        if ($usageMetadata) {
            $promptTokens = $usageMetadata['promptTokenCount'] ?? 0;
            $candidateTokens = $usageMetadata['candidatesTokenCount'] ?? 0;
            
            // Fórmula: (Tokens / 1.000.000) * Preço por Milhão
            $custoPrompt = ($promptTokens / 1000000) * GEMINI_COST_PER_MILLION_PROMPT;
            $custoCandidate = ($candidateTokens / 1000000) * GEMINI_COST_PER_MILLION_CANDIDATE;
            $custoEstimado = $custoPrompt + $custoCandidate;
        }

        // 6. Atualiza o Banco de Dados como 'Concluido'
        echo "  - Concluído com sucesso! Custo estimado: $$custoEstimado\n";
        $stmtUpdate = $pdo->prepare("
            UPDATE documentos 
            SET status = 'concluido', 
                tipo_documento = ?, 
                metadados_gemini = ?, 
                texto_extraido = ?, 
                custo_estimado = ?, 
                data_processamento = NOW() 
            WHERE id = ?
        ");
        $stmtUpdate->execute([
            $tipoDocumento,
            $jsonLimpo,
            $textoExtraido,
            $custoEstimado,
            $docId
        ]);

    } catch (Exception $e) {
        echo "  [ERRO] " . $e->getMessage() . "\n";
        
        // Em caso de erro, atualiza a tabela marcando como erro
        if ($docId) {
            $stmtError = $pdo->prepare("UPDATE documentos SET status = 'erro', erro_log = ? WHERE id = ?");
            $stmtError->execute([$e->getMessage(), $docId]);
        }
        
        sleep(2); // Pausa breve antes do próximo loop
    }
}
