<?php
require 'config.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id, nome_arquivo, status, tipo_documento, custo_estimado, data_criacao, data_processamento FROM documentos ORDER BY id DESC LIMIT 50");
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formata campos específicos para apresentação
    foreach ($documentos as &$doc) {
        $doc['custo_estimado'] = '$' . number_format($doc['custo_estimado'], 6, ',', '.');
        $doc['data_criacao'] = date('d/m/Y H:i:s', strtotime($doc['data_criacao']));
    }

    echo json_encode(['success' => true, 'data' => $documentos]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar documentos: ' . $e->getMessage()]);
}