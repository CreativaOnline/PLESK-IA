<?php

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/PleskClient.php';
require_once __DIR__ . '/src/McpServer.php';
require_once __DIR__ . '/src/tools/DomainsTools.php';
require_once __DIR__ . '/src/tools/MailTools.php';
require_once __DIR__ . '/src/tools/DatabaseTools.php';
require_once __DIR__ . '/src/tools/ServerTools.php';
require_once __DIR__ . '/src/tools/CliTools.php';
require_once __DIR__ . '/src/tools/LogTools.php';
require_once __DIR__ . '/src/tools/FileTools.php';
require_once __DIR__ . '/src/tools/AuditTools.php';
require_once __DIR__ . '/src/tools/DnsTools.php';
require_once __DIR__ . '/src/tools/WpCliTools.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$client = new PleskClient($config);
$server = new McpServer($config, $client);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode($server->getInfo(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verify($config)) {
        http_response_code(401);
        echo json_encode([
            'jsonrpc' => '2.0',
            'id'      => null,
            'error'   => [
                'code'    => -32000,
                'message' => 'Unauthorized: token inválido o ausente.',
            ],
        ]);
        exit;
    }

    $rawBody = file_get_contents('php://input');
    $request = json_decode($rawBody, true);

    if (!is_array($request) || !isset($request['method'])) {
        http_response_code(400);
        echo json_encode([
            'jsonrpc' => '2.0',
            'id'      => null,
            'error'   => [
                'code'    => -32700,
                'message' => 'Parse error: JSON inválido o falta el campo "method".',
            ],
        ]);
        exit;
    }

    $response = $server->handle($request);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode([
    'jsonrpc' => '2.0',
    'id'      => null,
    'error'   => [
        'code'    => -32600,
        'message' => 'Method not allowed. Use GET or POST.',
    ],
]);
