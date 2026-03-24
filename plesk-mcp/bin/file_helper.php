<?php

$path     = $argv[1] ?? '';
$maxBytes = (int)($argv[2] ?? 100000);

if ($path === '') {
    echo json_encode(['error' => 'El parámetro path es requerido.']);
    exit(1);
}

$allowedPrefixes = [
    '/var/www/vhosts/',
    '/var/log/',
    '/usr/local/psa/var/log/',
    '/etc/postfix/',
];

$realPath = realpath($path);
if ($realPath === false) {
    echo json_encode(['error' => 'El archivo no existe o la ruta es inválida: ' . $path]);
    exit(1);
}

$allowed = false;
foreach ($allowedPrefixes as $prefix) {
    if (strpos($realPath, $prefix) === 0) {
        $allowed = true;
        break;
    }
}

if (!$allowed) {
    echo json_encode(['error' => 'Ruta no permitida. Solo se permite leer dentro de: '
                                . implode(', ', $allowedPrefixes)]);
    exit(1);
}

if (!is_file($realPath)) {
    echo json_encode(['error' => 'La ruta no es un archivo regular: ' . $realPath]);
    exit(1);
}

if (!is_readable($realPath)) {
    echo json_encode(['error' => 'Sin permisos de lectura: ' . $realPath]);
    exit(1);
}

$size      = filesize($realPath);
$truncated = $size > $maxBytes;
$content   = @file_get_contents($realPath, false, null, 0, $maxBytes);

if ($content === false) {
    echo json_encode(['error' => 'Error al leer el archivo: ' . $realPath]);
    exit(1);
}

echo json_encode([
    'path'      => $realPath,
    'size'      => $size,
    'truncated' => $truncated,
    'content'   => $content,
]);
