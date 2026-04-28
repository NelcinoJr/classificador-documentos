<?php
// Configurações e Instanciação do Banco de Dados SQLite

$dbPath = __DIR__ . '/uploads_data/database.sqlite';
$dir = dirname($dbPath);

if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Criar tabela se não existir
    $query = "
    CREATE TABLE IF NOT EXISTS documentos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome_arquivo TEXT,
        tipo_solicitacao TEXT,
        subtipo TEXT,
        competencia TEXT,
        vencimento TEXT,
        valor TEXT,
        cnpj TEXT,
        processado_por TEXT, -- 'Regex Local' ou 'Gemini IA'
        data_processamento DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($query);

} catch (PDOException $e) {
    die("Erro ao conectar com o banco de dados: " . $e->getMessage());
}
