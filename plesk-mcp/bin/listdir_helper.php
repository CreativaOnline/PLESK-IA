<?php
/**
 * Listdir Helper — ejecutado via sudo desde FileTools::listDir()
 * Uso: sudo php listdir_helper.php <path> [pattern]
 *
 * Valida whitelist, resuelve realpath, lista directorio.
 * Devuelve JSON por stdout.
 */

$path    = $argv[1] ?? '';
$pattern = $argv[2] ?? '*';

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
if ($realPath === false || !is_dir($realPath)) {
    echo json_encode(['error' => 'El directorio no existe o la ruta es inválida: ' . $path]);
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
    echo json_encode(['error' => 'Ruta no permitida. Solo se permite listar dentro de: '
                                . implode(', ', $allowedPrefixes)]);
    exit(1);
}

if (!is_readable($realPath)) {
    echo json_encode(['error' => 'Sin permisos de lectura: ' . $realPath]);
    exit(1);
}

// Listar con glob (no recursivo)
$globPath = rtrim($realPath, '/') . '/' . $pattern;
$matches  = glob($globPath);

// Incluir archivos ocultos si pattern es *
if ($pattern === '*') {
    $hidden = glob(rtrim($realPath, '/') . '/.*');
    if (is_array($hidden)) {
        $hidden = array_filter($hidden, function ($p) {
            $base = basename($p);
            return $base !== '.' && $base !== '..';
        });
        $matches = array_merge($matches ?: [], $hidden);
    }
}

if (!is_array($matches)) {
    $matches = [];
}

$dirs  = [];
$files = [];

foreach ($matches as $item) {
    $name  = basename($item);
    $mtime = @filemtime($item);

    if (is_link($item)) {
        $files[] = [
            'name'  => $name,
            'type'  => 'link',
            'size'  => @filesize($item) ?: null,
            'mtime' => $mtime !== false ? date('Y-m-d H:i:s', $mtime) : null,
        ];
    } elseif (is_dir($item)) {
        $dirs[] = [
            'name'  => $name,
            'type'  => 'dir',
            'size'  => null,
            'mtime' => $mtime !== false ? date('Y-m-d H:i:s', $mtime) : null,
        ];
    } else {
        $files[] = [
            'name'  => $name,
            'type'  => 'file',
            'size'  => @filesize($item) ?: null,
            'mtime' => $mtime !== false ? date('Y-m-d H:i:s', $mtime) : null,
        ];
    }
}

// Ordenar alfabéticamente
usort($dirs,  fn($a, $b) => strcasecmp($a['name'], $b['name']));
usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

$entries = array_merge($dirs, $files);

echo json_encode([
    'path'    => $realPath,
    'entries' => $entries,
    'total'   => count($entries),
]);
