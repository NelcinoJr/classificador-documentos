<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classificador de Documentos em Lote</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .loading { display: none; }
        .spinner { border: 4px solid rgba(0, 0, 0, 0.1); width: 36px; height: 36px; border-radius: 50%; border-left-color: #3b82f6; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans min-h-screen p-6 md:p-12">
    <div class="max-w-5xl mx-auto space-y-8">
        
        <!-- Header -->
        <div class="text-center space-y-2">
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Classificador IA</h1>
            <p class="text-gray-500">Upload de PDFs em lote com extração de dados via Google Gemini.</p>
        </div>

        <!-- Formulário Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
            <form action="upload.php" method="POST" enctype="multipart/form-data" id="uploadForm" class="space-y-6">
                
                <!-- Input Files -->
                <div>
                    <label for="pdf_files" class="block text-sm font-semibold text-gray-700 mb-2">Selecione os arquivos PDF:</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-colors">
                        <div class="space-y-1 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-gray-600 justify-center">
                                <label for="pdf_files" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none">
                                    <span>Fazer upload de arquivos</span>
                                    <input id="pdf_files" name="pdf_files[]" type="file" class="sr-only" accept="application/pdf" multiple required>
                                </label>
                            </div>
                            <p class="text-xs text-gray-500">PDFs até 10MB. Você pode selecionar vários.</p>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Modelo -->
                    <div>
                        <label for="gemini_model" class="block text-sm font-semibold text-gray-700 mb-2">Modelo do Gemini:</label>
                        <select name="gemini_model" id="gemini_model" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2.5 border bg-white">
                            <option value="gemini-1.5-flash">Gemini 1.5 Flash (Rápido e Barato)</option>
                            <option value="gemini-1.5-pro">Gemini 1.5 Pro (Raciocínio Complexo)</option>
                            <option value="gemini-2.5-flash">Gemini 2.5 Flash</option>
                            <option value="gemini-2.5-pro">Gemini 2.5 Pro</option>
                        </select>
                    </div>

                    <!-- API Key -->
                    <div>
                        <label for="api_key" class="block text-sm font-semibold text-gray-700 mb-2">Chave de API do Gemini:</label>
                        <input type="password" name="api_key" id="api_key" placeholder="AIzaSy..." value="<?php echo htmlspecialchars($_SESSION['gemini_api_key'] ?? ''); ?>" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2.5 border">
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-4 pt-4 border-t border-gray-100">
                    <button type="submit" class="flex-1 flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        Processar Documentos
                    </button>
                    <a href="list.php" class="flex-1 flex justify-center py-3 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        Ver Histórico Salvo
                    </a>
                </div>
            </form>
            
            <!-- Loading -->
            <div id="loadingIndicator" class="hidden mt-6 flex-col items-center justify-center p-6 bg-blue-50 rounded-xl border border-blue-100">
                <div class="spinner mb-4"></div>
                <p class="text-blue-800 font-medium">Processando lote de documentos com IA...</p>
                <p class="text-blue-600 text-sm mt-1">Isso pode levar alguns segundos dependendo da quantidade.</p>
            </div>
        </div>

        <!-- Resultados -->
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <?php 
                $results = isset($_SESSION['batch_results']) ? $_SESSION['batch_results'] : [];
                $costs = isset($_SESSION['batch_costs']) ? $_SESSION['batch_costs'] : null;
            ?>
            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 bg-green-50/50 flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-800">Resultados do Lote</h2>
                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">Sucesso</span>
                </div>
                
                <?php if (!empty($results)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-600">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-4">Arquivo</th>
                                    <th class="px-6 py-4">Classificação</th>
                                    <th class="px-6 py-4">Dados Extras</th>
                                    <th class="px-6 py-4 text-right">Custo do Arquivo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($results as $res): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 font-medium text-gray-900 truncate max-w-[200px]" title="<?php echo htmlspecialchars($res['arquivo']); ?>">
                                            <?php echo htmlspecialchars($res['arquivo']); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="block font-bold text-gray-800"><?php echo htmlspecialchars($res['dados']['tipo_solicitacao'] ?? 'N/A'); ?></span>
                                            <span class="text-xs text-gray-500 uppercase"><?php echo htmlspecialchars($res['dados']['subtipo'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-xs">
                                                <span class="text-gray-400">Venc:</span> <span class="font-medium text-red-600"><?php echo htmlspecialchars($res['dados']['vencimento'] ?? '-'); ?></span><br>
                                                <span class="text-gray-400">Valor:</span> <span class="font-medium text-green-700">R$ <?php echo htmlspecialchars($res['dados']['valor'] ?? '-'); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="block font-bold text-gray-800">R$ <?php echo number_format($res['custo_brl'] ?? 0, 4, ',', '.'); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="p-6 text-gray-500 text-center">Nenhum resultado processado.</p>
                <?php endif; ?>
                
                <!-- Resumo de Custos -->
                <?php if ($costs && $costs['total_cost_usd'] > 0): ?>
                    <div class="bg-gray-50 p-6 border-t border-gray-200">
                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wider mb-4">Resumo da API (Gemini)</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="bg-white p-4 rounded-lg border border-gray-100 shadow-sm">
                                <p class="text-xs text-gray-500">Arquivos Processados</p>
                                <p class="text-xl font-bold text-gray-900"><?php echo count($results); ?></p>
                            </div>
                            <div class="bg-white p-4 rounded-lg border border-gray-100 shadow-sm">
                                <p class="text-xs text-gray-500">Total de Tokens</p>
                                <p class="text-xl font-bold text-gray-900"><?php echo number_format($costs['total_tokens'], 0, ',', '.'); ?></p>
                            </div>
                            <div class="bg-white p-4 rounded-lg border border-gray-100 shadow-sm">
                                <p class="text-xs text-gray-500">Custo Total (USD)</p>
                                <p class="text-xl font-bold text-gray-900">$<?php echo number_format($costs['total_cost_usd'], 4, '.', ','); ?></p>
                            </div>
                            <div class="bg-blue-50 p-4 rounded-lg border border-blue-100 shadow-sm">
                                <p class="text-xs text-blue-600 font-semibold">Custo Total (BRL)</p>
                                <p class="text-2xl font-black text-blue-800">R$ <?php echo number_format($costs['total_cost_brl'], 4, ',', '.'); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-md mt-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Erro no processamento</h3>
                        <p class="text-sm text-red-700 mt-1"><?php echo htmlspecialchars($_GET['error']); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function() {
            document.getElementById('loadingIndicator').classList.remove('hidden');
            document.getElementById('loadingIndicator').classList.add('flex');
            const btn = document.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = 'Enviando...';
            btn.classList.add('opacity-75', 'cursor-not-allowed');
        });
        
        // Mostrar o nome dos arquivos selecionados
        document.getElementById('pdf_files').addEventListener('change', function(e) {
            const fileCount = e.target.files.length;
            const labelSpan = document.querySelector('label[for="pdf_files"] span');
            if (fileCount > 0) {
                labelSpan.textContent = fileCount + ' arquivo(s) selecionado(s)';
                labelSpan.parentElement.classList.replace('text-blue-600', 'text-green-600');
            } else {
                labelSpan.textContent = 'Fazer upload de arquivos';
                labelSpan.parentElement.classList.replace('text-green-600', 'text-blue-600');
            }
        });
    </script>
</body>
</html>