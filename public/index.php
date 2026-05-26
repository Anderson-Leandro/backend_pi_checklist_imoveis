<?php

// Conexão com o banco (use as variáveis do .env ou defina aqui para dev)
$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_DATABASE') ?: 'appdb';
$user = getenv('DB_USERNAME') ?: 'appuser';
$pass = getenv('DB_PASSWORD') ?: 'secret';

// Cabeçalhos padrão de API
header('Content-Type: application/json');

// Roteamento simples
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/' && $method === 'GET') {
    echo json_encode(['status' => 'ok', 'message' => 'API funcionando!']);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Rota não encontrada']);
