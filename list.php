<?php
require 'config.php';

try {
    $stmt = $pdo->query("SELECT * FROM documentos ORDER BY data_processamento DESC");
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar documentos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Documentos - ContaÁgil</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: sans-serif; background-color: #f4f4f9; color: #333; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background-color: #2c3e50; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .badge-local { background-color: #2ecc71; color: white; padding: 3px 6px; border-radius: 12px; font-size: 11px; }
        .badge-ai { background-color: #9b59b6; color: white; padding: 3px 6px; border-radius: 12px; font-size: 11px; }
        .btn-back { display: inline-block; margin-bottom: 20px; padding: 10px 15px; background-color: #3498db; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; }
        .btn-back:hover { background-color: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="btn-back">&larr; Voltar para Upload</a>
        
        <h1>Histórico de Documentos Processados</h1>
        
        <?php if (count($documentos) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data Proc.</th>
                        <th>Arquivo</th>
                        <th>Classificação</th>
                        <th>Origem (Motor)</th>
                        <th>CNPJ</th>
                        <th>Valor (R$)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documentos as $doc): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($doc['id']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($doc['data_processamento'])); ?></td>
                            <td><?php echo htmlspecialchars($doc['nome_arquivo']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($doc['tipo_solicitacao'] ?? '-'); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($doc['subtipo'] ?? ''); ?></small>
                            </td>
                            <td>
                                <?php if ($doc['processado_por'] == 'Local'): ?>
                                    <span class="badge-local">Local (Regex)</span>
                                <?php elseif ($doc['processado_por'] == 'IA'): ?>
                                    <span class="badge-ai">IA (Gemini)</span>
                                <?php else: ?>
                                    <span style="color:red; font-weight:bold;">Falha</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($doc['cnpj'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($doc['valor'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; margin-top: 30px; color: #7f8c8d;">Nenhum documento processado ainda.</p>
        <?php endif; ?>
    </div>
</body>
</html>