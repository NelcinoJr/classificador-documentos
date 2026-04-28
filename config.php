<?php
// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Substitua pela sua senha
define('DB_NAME', 'classificador_db');

// Configurações da AWS (Textract)
define('AWS_ACCESS_KEY', 'SUA_AWS_ACCESS_KEY');
define('AWS_SECRET_KEY', 'SUA_AWS_SECRET_KEY');
define('AWS_REGION', 'us-east-1');

// Configurações do Google Gemini 2.5
define('GEMINI_API_KEY', 'SUA_CHAVE_DO_GEMINI');
// Tabela de Preços do Gemini 2.5 Flash (exemplo em dólares, por 1 milhão de tokens)
// https://cloud.google.com/gemini-enterprise-agent-platform/generative-ai/pricing
define('GEMINI_COST_PER_MILLION_PROMPT', 0.075);
define('GEMINI_COST_PER_MILLION_CANDIDATE', 0.30);

// Conexão com o banco de dados
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Cria o banco e a tabela automaticamente se não existir
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`");
    $pdo->exec("USE `" . DB_NAME . "`");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS documentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome_arquivo VARCHAR(255) NOT NULL,
        caminho_arquivo VARCHAR(255) NOT NULL,
        status ENUM('pendente', 'processando', 'concluido', 'erro') DEFAULT 'pendente',
        tipo_documento VARCHAR(100) NULL,
        metadados_gemini JSON NULL,
        custo_estimado DECIMAL(10, 6) DEFAULT 0.000000,
        texto_extraido LONGTEXT NULL,
        erro_log TEXT NULL,
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        data_processamento DATETIME NULL
    )");
} catch(PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}
?>