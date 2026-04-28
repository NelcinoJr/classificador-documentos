<?php
require 'config.php';

// Limpar banco de dados
if (isset($_GET['action']) && $_GET['action'] == 'clear') {
    try {
        $pdo->exec("DELETE FROM documentos");
        header("Location: list.php?cleared=1");
        exit;
    } catch (PDOException $e) {
        die("Erro ao limpar banco de dados: " . $e->getMessage());
    }
}

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
    <!-- Adicionando Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .badge-local { background-color: #2ecc71; color: white; padding: 4px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-ai { background-color: #9b59b6; color: white; padding: 4px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans p-6 md:p-12">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <a href="index.php" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm transition-colors">
                &larr; Voltar para Upload
            </a>
            
            <!-- Botão para limpar o banco -->
            <a href="list.php?action=clear" onclick="return confirm('Tem certeza que deseja apagar todo o histórico do banco de dados?');" class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md shadow-sm transition-colors">
                Limpar Banco de Dados
            </a>
        </div>
        
        <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-100">
            <div class="p-6 border-b border-gray-100 bg-gray-50/50">
                <h1 class="text-2xl font-bold text-gray-800 text-center">Histórico de Documentos Processados</h1>
                <p class="text-center text-sm text-gray-500 mt-1">Visão detalhada de custos e informações extraídas.</p>
            </div>
            
            <?php if (isset($_GET['cleared']) && $_GET['cleared'] == 1): ?>
                <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 mx-6 mt-6" role="alert">
                    Banco de dados limpo com sucesso!
                </div>
            <?php endif; ?>

            <div class="overflow-x-auto p-6">
                <?php if (count($documentos) > 0): ?>
                    <table class="w-full text-sm text-left text-gray-600">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-100 border-b border-gray-200">
                            <tr>
                                <th scope="col" class="px-6 py-3 font-semibold rounded-tl-lg">ID</th>
                                <th scope="col" class="px-6 py-3 font-semibold">Data Proc.</th>
                                <th scope="col" class="px-6 py-3 font-semibold">Arquivo</th>
                                <th scope="col" class="px-6 py-3 font-semibold">Classificação</th>
                                <th scope="col" class="px-6 py-3 font-semibold text-center">Origem</th>
                                <th scope="col" class="px-6 py-3 font-semibold">Dados Básicos</th>
                                <th scope="col" class="px-6 py-3 font-semibold text-right rounded-tr-lg">Custo Unitário</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documentos as $doc): ?>
                                <tr class="bg-white border-b hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($doc['id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500"><?php echo date('d/m/Y H:i', strtotime($doc['data_processamento'])); ?></td>
                                    <td class="px-6 py-4 font-medium text-blue-600 truncate max-w-[200px]" title="<?php echo htmlspecialchars($doc['nome_arquivo']); ?>">
                                        <?php echo htmlspecialchars($doc['nome_arquivo']); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="block font-bold text-gray-800"><?php echo htmlspecialchars($doc['tipo_solicitacao'] ?? '-'); ?></span>
                                        <span class="text-xs text-gray-500 uppercase tracking-wide"><?php echo htmlspecialchars($doc['subtipo'] ?? ''); ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($doc['processado_por'] == 'Local'): ?>
                                            <span class="badge-local">Local (Regex)</span>
                                        <?php elseif ($doc['processado_por'] == 'IA'): ?>
                                            <span class="badge-ai">IA (Gemini)</span>
                                        <?php else: ?>
                                            <span class="text-red-500 font-bold">Falha</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-xs">
                                        <div class="mb-1"><span class="text-gray-400">CNPJ:</span> <span class="font-medium"><?php echo htmlspecialchars($doc['cnpj'] ?? '-'); ?></span></div>
                                        <div class="mb-1"><span class="text-gray-400">Venc:</span> <span class="font-medium text-red-600"><?php echo htmlspecialchars($doc['vencimento'] ?? '-'); ?></span></div>
                                        <div><span class="text-gray-400">Valor:</span> <span class="font-medium text-green-700">R$ <?php echo htmlspecialchars($doc['valor'] ?? '-'); ?></span></div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <?php if(isset($doc['custo_brl']) && $doc['custo_brl'] > 0): ?>
                                            <span class="block font-bold text-gray-800">R$ <?php echo number_format($doc['custo_brl'], 4, ',', '.'); ?></span>
                                            <span class="text-xs text-gray-400">USD $<?php echo number_format($doc['custo_usd'], 4, '.', ','); ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400 italic">R$ 0,00</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">Nenhum documento</h3>
                        <p class="mt-1 text-sm text-gray-500">Nenhum documento processado ainda. Vá para a tela de upload.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>