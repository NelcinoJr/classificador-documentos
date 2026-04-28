<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classificador de Documentos (PoC)</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: sans-serif; background-color: #f4f4f9; color: #333; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #2c3e50; }
        form { display: flex; flex-direction: column; gap: 15px; margin-bottom: 20px; }
        label { font-weight: bold; }
        input[type="file"], select { padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 12px; background-color: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background-color: #2980b9; }
        .result-box { margin-top: 20px; padding: 20px; border-radius: 4px; border: 1px solid #ddd; background-color: #f8f9fa; }
        .result-box pre { white-space: pre-wrap; word-wrap: break-word; background: #eee; padding: 10px; border-radius: 4px; }
        .costs { margin-top: 15px; padding: 15px; background-color: #e8f4f8; border-left: 4px solid #3498db; }
        .loading { display: none; text-align: center; margin-top: 20px; font-weight: bold; color: #e67e22; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Classificador de Documentos IA</h1>
        <p>Faça upload de um PDF (ex: Guia DAS, INSS) para extração de texto e classificação via Gemini.</p>
        
        <form action="upload.php" method="POST" enctype="multipart/form-data" id="uploadForm">
            <div>
                <label for="pdf_file">Selecione o arquivo PDF:</label>
                <input type="file" name="pdf_file" id="pdf_file" accept="application/pdf" required>
            </div>
            
            <div>
                <label for="gemini_model">Escolha o Modelo do Gemini:</label>
                <select name="gemini_model" id="gemini_model">
                    <option value="gemini-1.5-flash">Gemini 1.5 Flash (Rápido e Barato)</option>
                    <option value="gemini-1.5-pro">Gemini 1.5 Pro (Raciocínio Complexo)</option>
                    <option value="gemini-2.5-flash">Gemini 2.5 Flash</option>
                    <option value="gemini-2.5-pro">Gemini 2.5 Pro</option>
                    <option value="gemini-3.0-ultra">Gemini 3.0 Ultra</option>
                    <option value="gemini-3.1">Gemini 3.1</option>
                </select>
            </div>

            <div>
                <label for="api_key">Sua Chave de API do Gemini:</label>
                <input type="password" name="api_key" id="api_key" placeholder="AIzaSy..." required>
            </div>

            <button type="submit">Enviar e Analisar</button>
        </form>

        <div class="loading" id="loadingIndicator">
            Analisando documento... Por favor, aguarde. Pode levar alguns segundos.
        </div>

        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <?php 
                $result = isset($_SESSION['gemini_result']) ? $_SESSION['gemini_result'] : null;
                $costs = isset($_SESSION['gemini_costs']) ? $_SESSION['gemini_costs'] : null;
            ?>
            
            <div class="result-box">
                <h2>Resultado da Análise</h2>
                <?php if ($result): ?>
                    <?php 
                        $decoded = json_decode($result, true);
                        if ($decoded) {
                            echo "<ul>";
                            echo "<li><strong>Tipo de Solicitação:</strong> " . htmlspecialchars($decoded['tipo_solicitacao'] ?? 'N/A') . "</li>";
                            echo "<li><strong>Subtipo do Documento:</strong> " . htmlspecialchars($decoded['subtipo'] ?? 'N/A') . "</li>";
                            echo "<li><strong>Competência:</strong> " . htmlspecialchars($decoded['competencia'] ?? 'N/A') . "</li>";
                            echo "<li><strong>Vencimento:</strong> " . htmlspecialchars($decoded['vencimento'] ?? 'N/A') . "</li>";
                            echo "<li><strong>Valor:</strong> R$ " . htmlspecialchars($decoded['valor'] ?? 'N/A') . "</li>";
                            echo "<li><strong>CNPJ Encontrado:</strong> " . htmlspecialchars($decoded['cnpj'] ?? 'N/A') . "</li>";
                            echo "</ul>";
                        } else {
                            echo "<pre>" . htmlspecialchars($result) . "</pre>";
                        }
                    ?>
                <?php else: ?>
                    <p>Nenhum resultado recebido.</p>
                <?php endif; ?>
                
                <?php if ($costs): ?>
                    <div class="costs">
                        <h3>Estimativa de Custos</h3>
                        <p><strong>Modelo:</strong> <?php echo htmlspecialchars($costs['model']); ?></p>
                        <p><strong>Tokens Enviados (Input):</strong> <?php echo number_format($costs['input_tokens'], 0, ',', '.'); ?></p>
                        <p><strong>Tokens Recebidos (Output):</strong> <?php echo number_format($costs['output_tokens'], 0, ',', '.'); ?></p>
                        <p><strong>Custo Estimado na Requisição:</strong> USD $<?php echo number_format($costs['total_cost_usd'], 6, '.', ','); ?> (Aprox. R$ <?php echo number_format($costs['total_cost_brl'], 4, ',', '.'); ?>)</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="result-box" style="border-color: #e74c3c; background-color: #fadbd8;">
                <h2 style="color: #c0392b;">Erro</h2>
                <p><?php echo htmlspecialchars($_GET['error']); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function() {
            document.getElementById('loadingIndicator').style.display = 'block';
            document.querySelector('button[type="submit"]').disabled = true;
        });
    </script>
</body>
</html>