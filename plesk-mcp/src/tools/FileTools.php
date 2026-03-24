<?php

class FileTools
{
    private static array $allowedPrefixes = [
        '/var/www/vhosts/',
        '/var/log/',
        '/usr/local/psa/var/log/',
        '/etc/postfix/',
    ];

    public static function readFile(PleskClient $client, array $args): array
    {
        $path     = $args['path'] ?? '';
        $maxBytes = (int)($args['max_bytes'] ?? 100000);

        if ($path === '') {
            return ['success' => false, 'data' => null,
                    'message' => 'El parámetro "path" es requerido.'];
        }

        // Resolver ruta real para evitar path traversal
        $realPath = realpath($path);
        if ($realPath === false) {
            return ['success' => false, 'data' => null,
                    'message' => 'El archivo no existe o la ruta es inválida: ' . $path];
        }

        // Verificar whitelist de rutas permitidas
        $allowed = false;
        foreach (self::$allowedPrefixes as $prefix) {
            if (strpos($realPath, $prefix) === 0) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            return ['success' => false, 'data' => null,
                    'message' => 'Ruta no permitida. Solo se permite leer dentro de: '
                               . implode(', ', self::$allowedPrefixes)];
        }

        if (!is_file($realPath)) {
            return ['success' => false, 'data' => null,
                    'message' => 'La ruta no es un archivo regular: ' . $realPath];
        }

        if (!is_readable($realPath)) {
            return ['success' => false, 'data' => null,
                    'message' => 'Sin permisos de lectura: ' . $realPath];
        }

        $size      = filesize($realPath);
        $truncated = $size > $maxBytes;
        $content   = @file_get_contents($realPath, false, null, 0, $maxBytes);

        if ($content === false) {
            return ['success' => false, 'data' => null,
                    'message' => 'Error al leer el archivo: ' . $realPath];
        }

        return [
            'success' => true,
            'data'    => [
                'path'      => $realPath,
                'size'      => $size,
                'truncated' => $truncated,
                'content'   => $content,
            ],
            'message' => '',
        ];
    }

    public static function listDir(PleskClient $client, array $args): array
    {
        $path    = $args['path'] ?? '';
        $pattern = $args['pattern'] ?? '*';

        if ($path === '') {
            return ['success' => false, 'data' => null,
                    'message' => 'El parámetro "path" es requerido.'];
        }

        // Resolver ruta real para evitar path traversal
        $realPath = realpath($path);
        if ($realPath === false || !is_dir($realPath)) {
            return ['success' => false, 'data' => null,
                    'message' => 'El directorio no existe o la ruta es inválida: ' . $path];
        }

        // Verificar whitelist de rutas permitidas
        $allowed = false;
        foreach (self::$allowedPrefixes as $prefix) {
            if (strpos($realPath, $prefix) === 0) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            return ['success' => false, 'data' => null,
                    'message' => 'Ruta no permitida. Solo se permite listar dentro de: '
                               . implode(', ', self::$allowedPrefixes)];
        }

        if (!is_readable($realPath)) {
            return ['success' => false, 'data' => null,
                    'message' => 'Sin permisos de lectura: ' . $realPath];
        }

        // Listar con glob (no recursivo)
        $globPath = rtrim($realPath, '/') . '/' . $pattern;
        $matches  = glob($globPath);

        // Incluir archivos ocultos si pattern es * o .*
        if ($pattern === '*') {
            $hidden = glob(rtrim($realPath, '/') . '/.*');
            if (is_array($hidden)) {
                // Filtrar . y ..
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

            if (is_dir($item)) {
                $dirs[] = [
                    'name'  => $name,
                    'type'  => 'dir',
                    'size'  => null,
                    'mtime' => $mtime !== false ? date('Y-m-d H:i:s', $mtime) : null,
                ];
            } elseif (is_link($item)) {
                $files[] = [
                    'name'  => $name,
                    'type'  => 'link',
                    'size'  => @filesize($item) ?: null,
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

        return [
            'success' => true,
            'data'    => [
                'path'    => $realPath,
                'entries' => $entries,
                'total'   => count($entries),
            ],
            'message' => '',
        ];
    }
}
