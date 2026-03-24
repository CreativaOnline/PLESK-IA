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
}
