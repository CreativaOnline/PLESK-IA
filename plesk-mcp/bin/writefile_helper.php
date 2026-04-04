<?php

$path    = $argv[1] ?? '';
$content = '';

$stdin = fopen('php://stdin', 'r');
if ($stdin) {
    $content = stream_get_contents($stdin);
    fclose($stdin);
}

if ($path === '') {
    echo json_encode(['error' => 'Se requiere path']);
    exit(1);
}

$allowedPrefixes = [
    '/var/www/vhosts/',
    '/var/log/',
    '/usr/local/psa/var/log/',
    '/etc/postfix/',
];

$dir = dirname($path);
$realDir = realpath($dir);

if ($realDir === false) {
    echo json_encode(['error' => 'Directorio padre no existe: ' . $dir]);
    exit(1);
}

$allowed = false;
foreach ($allowedPrefixes as $prefix) {
    if (strpos($realDir . '/', $prefix) === 0) {
        $allowed = true;
        break;
    }
}

if (!$allowed) {
    echo json_encode(['error' => 'Ruta no permitida. Prefijos válidos: ' . implode(', ', $allowedPrefixes)]);
    exit(1);
}

$fullPath = $realDir . '/' . basename($path);

$dangerousExtensions = ['.sh', '.bash', '.bin', '.exe', '.so'];
$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
if (in_array('.' . $ext, $dangerousExtensions)) {
    echo json_encode(['error' => 'Extensión no permitida: .' . $ext]);
    exit(1);
}

$backup = null;
if (file_exists($fullPath)) {
    $backup = $fullPath . '.bak.' . date('YmdHis');
    if (!@copy($fullPath, $backup)) {
        echo json_encode(['error' => 'No se pudo crear backup de: ' . $fullPath]);
        exit(1);
    }
}

$bytes = @file_put_contents($fullPath, $content);
if ($bytes === false) {
    echo json_encode(['error' => 'No se pudo escribir en: ' . $fullPath]);
    exit(1);
}

echo json_encode([
    'path'    => $fullPath,
    'bytes'   => $bytes,
    'backup'  => $backup,
    'created' => !file_exists($fullPath) || $backup === null,
]);
