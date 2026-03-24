<?php
$user     = getenv('PLESK_USER');
$password = getenv('PLESK_PASSWORD');
$token    = getenv('MCP_TOKEN');

if (empty($user) || empty($password) || empty($token)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server configuration error']);
    exit;
}

return [
    'plesk_url'      => 'https://dedi50173.mad02.tuservidoronline.com:8443',
    'plesk_user'     => $user,
    'plesk_password' => $password,
    'mcp_token'      => $token,
    'ssl_verify'     => false,
];
