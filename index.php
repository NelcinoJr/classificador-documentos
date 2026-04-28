<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classificador de Documentos em Lote</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: sans-serif; background-color: #f4f4f9; color: #333; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #2c3e50; }
        form { display: flex; flex-direction: column; gap: 15px; margin-bottom: 20px; }
        label { font-weight: bold; }
        input[type="file"], select { padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 12px; background-color: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background-color: #2980b9; }
        .btn-secondary { background-color: #2ecc71; margin-top: 10px; display: inline-block; text-align: center; text-decoration: none; padding: 12px; border-radius: 4px; color: white; font-weight: bold;}
        .btn-secondary:hover { background-color: #27ae60; }
        .result-box { margin-top: 20px; padding: 20px; border-radius: 4px; border: 1px solid #ddd; background-color: #f8f9fa; }
        .costs { margin-top: 15px; padding: 15px; background-color: #e8f4f8; border-left: 4px solid #3498db; }
        .loading { display: none; text-align: center; margin-top: 20px; font-weight: bold; color: #e67e22; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .badge-local { background-color: #2ecc71; color: white; padding: 3px 6px; border-radius: 12px; font-size: 11px; }
        .badge-ai { background-color: #9b59b6; color: white; padding: 3px 6px; border-radius: 12px; font-size: 11px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Classificador de Documentos em Lote</h1>
        <p>Faça upload de múltiplos PDFs. O sistema tentará classificar localmente primeiro para economizar créditos. Caso não consiga, enviará para o Gemini.</p>
        
        <form action="upload.php" method="POST" enctype="multipart/form-data" id="uploadForm">
            <div>
                <label for="pdf_files">Selecione os arquivos PDF (pode selecionar vários):</label>
                <!-- Atributo 'multiple' e 'name' como array [] -->
                <input type="file" name="pdf_files[]" id="pdf_files" accept="application/pdf" multiple required>
            </div>
            
            <div>
                <label for="gemini_model">Escolha o Modelo do Gemini (Fallback):</label>
                <select name="gemini_model" id="gemini_model">
                    <option value="gemini-1.5-flash">Gemini 1.5 Flash (Rápido e Barato)</option>
                    <option value="gemini-1.5-pro">Gemini 1.5 Pro (Raciocínio Complexo)</option>
                    <option value="gemini-2.5-flash">Gemini 2.5 Flash</option>
                    <option value="gemini-2.5-pro">Gemini 2.5 Pro</option>
                </select>
                <small style="color: #666; display: block; margin-top: 5px;">A IA só será chamada se a pré-classificação local falhar.</small>
            </div>

            <div>
                <label for="api_key">Sua Chave de API do Gemini:</label>
                <input type="password" name="api_key" id="api_key" placeholder="AIzaSy..." required>
            </div>

            <button type="submit">Processar Documentos</button>
        </form>

        <a href="list.php" class="btn-secondary">Ver Histórico no Banco de Dados</a>

        <div class="loading" id="loadingIndicator">
            Processando lote de documentos... Por favor, aguarde.
        </div>

        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <?php 
                $results = isset($_SESSION['batch_results']) ? $_SESSION['batch_results'] : [];
                $costs = isset($_SESSION['batch_costs']) ? $_SESSION['batch_costs'] : null;
            ?>
            
            <div class="result-box">
                <h2>Resultados do Processamento</h2>
                
                <?php if (!empty($results)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Arquivo</th>
                                <th>Tipo</th>
                                <th>Subtipo</th>
                                <th>Origem</th>
                                <th>Vencimento / Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $res): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($res['arquivo']); ?></td>
                                    <td><?php echo htmlspecialchars($res['dados']['tipo_solicitacao'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($res['dados']['subtipo'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (($res['processado_por'] ?? '') == 'Local'): ?>
                                            <span class="badge-local">Local (Regex)</span>
                                        <?php else: ?>
                                            <span class="badge-ai">IA (Gemini)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($res['dados']['vencimento'] ?? '-'); ?> <br>
                                        R$ <?php echo htmlspecialchars($res['dados']['valor'] ?? '-'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Nenhum resultado processado.</p>
                <?php endif; ?>
                
                <?php if ($costs && $costs['total_cost_usd'] > 0): ?>
                    <div class="costs">
                        <h3>Estimativa de Custos (Arquivos enviados para IA)</h3>
                        <p><strong>Arquivos analisados por IA:</strong> <?php echo $costs['ai_calls']; ?> de <?php echo count($results); ?></p>
                        <p><strong>Tokens Totais (In+Out):</strong> <?php echo number_format($costs['total_tokens'], 0, ',', '.'); ?></p>
                        <p><strong>Custo Total do Lote:</strong> USD $<?php echo number_format($costs['total_cost_usd'], 6, '.', ','); ?> (Aprox. R$ <?php echo number_format($costs['total_cost_brl'], 4, ',', '.'); ?>)</p>
                    </div>
                <?php else: ?>
                     <div class="costs" style="border-left-color: #2ecc71;">
                        <h3>Custo Zero! 🎉</h3>
                        <p>Todos os arquivos deste lote foram identificados com sucesso pelo classificador local. Nenhuma chamada à API do Gemini foi necessária.</p>
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