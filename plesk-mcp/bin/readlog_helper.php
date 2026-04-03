<?php

$path    = $argv[1] ?? '';
$pattern = $argv[2] ?? '';
$lines   = (int)($argv[3] ?? 100);

if ($lines < 1)    $lines = 100;
if ($lines > 5000) $lines = 5000;

$allowedPrefixes = [
    '/var/log/',
    '/usr/local/psa/var/log/',
];

$realPath = realpath($path);

if ($realPath === false || !is_file($realPath) || !is_readable($realPath)) {
    echo json_encode(['error' => 'Archivo no encontrado o no legible: ' . $path]);
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
    echo json_encode(['error' => 'Ruta no permitida. Solo se permite /var/log/ y /usr/local/psa/var/log/']);
    exit(1);
}

$cmd = 'tail -n ' . $lines . ' ' . escapeshellarg($realPath);

if ($pattern !== '') {
    $cmd .= ' | grep -i ' . escapeshellarg($pattern);
}

$output = [];
exec($cmd . ' 2>/dev/null', $output);

echo json_encode([
    'path'        => $realPath,
    'pattern'     => $pattern !== '' ? $pattern : null,
    'total_lines' => count($output),
    'max_lines'   => $lines,
    'lines'       => $output,
]);
