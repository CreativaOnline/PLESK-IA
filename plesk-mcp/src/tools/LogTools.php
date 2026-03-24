<?php

class LogTools
{
    public static function scanMaillog(PleskClient $client, array $args): array
    {
        // Leer las últimas N líneas del maillog buscando patrones sospechosos
        $lines   = (int)($args['lines'] ?? 500);
        $filter  = $args['filter'] ?? '';
        $logFile = '/var/log/maillog';

        if (!is_readable($logFile)) {
            // Intentar rutas alternativas
            foreach (['/var/log/mail.log', '/var/log/postfix.log'] as $alt) {
                if (is_readable($alt)) { $logFile = $alt; break; }
            }
        }

        if (!is_readable($logFile)) {
            return ['success' => false, 'data' => null,
                    'message' => 'No se puede leer el maillog.'];
        }

        // Leer últimas $lines líneas
        $output = [];
        exec('tail -' . (int)$lines . ' ' . escapeshellarg($logFile), $output);

        // Patrones sospechosos de spam saliente
        $spamPatterns = [
            'status=deferred', 'status=bounced', 'relay=none',
            'blocked', 'spam', 'reject', 'rate limit',
            'does not pass', 'SPF', 'DKIM.*fail',
        ];

        $suspicious = [];
        $senders    = [];
        $stats      = ['total_lines' => count($output), 'deferred' => 0,
                       'bounced' => 0, 'sent' => 0, 'rejected' => 0];

        foreach ($output as $line) {
            // Filtro opcional
            if ($filter !== '' && stripos($line, $filter) === false) continue;

            // Contadores
            if (strpos($line, 'status=sent')     !== false) $stats['sent']++;
            if (strpos($line, 'status=deferred') !== false) $stats['deferred']++;
            if (strpos($line, 'status=bounced')  !== false) $stats['bounced']++;
            if (strpos($line, 'reject')          !== false) $stats['rejected']++;

            // Detectar líneas sospechosas
            foreach ($spamPatterns as $pattern) {
                if (preg_match('/' . $pattern . '/i', $line)) {
                    $suspicious[] = $line;
                    break;
                }
            }

            // Extraer remitentes únicos
            if (preg_match('/from=<([^>]+)>/', $line, $m)) {
                $from = $m[1];
                if ($from !== '' && $from !== 'MAILER-DAEMON') {
                    $domain = substr($from, strpos($from, '@') + 1);
                    $senders[$domain] = ($senders[$domain] ?? 0) + 1;
                }
            }
        }

        // Ordenar remitentes por volumen
        arsort($senders);

        return [
            'success' => true,
            'data'    => [
                'log_file'        => $logFile,
                'stats'           => $stats,
                'top_senders'     => array_slice($senders, 0, 20, true),
                'suspicious_lines'=> array_slice($suspicious, -50),
            ],
            'message' => '',
        ];
    }

    public static function scanMalware(PleskClient $client, array $args): array
    {
        $domain  = $args['domain'] ?? '';
        $maxFiles = (int)($args['max_files'] ?? 5000);

        // Determinar ruta del vhost
        if ($domain !== '') {
            $paths = [
                '/var/www/vhosts/' . $domain . '/httpdocs',
                '/var/www/vhosts/' . $domain,
            ];
        } else {
            $paths = ['/var/www/vhosts'];
        }

        $basePath = '';
        foreach ($paths as $p) {
            if (is_dir($p)) { $basePath = $p; break; }
        }

        if ($basePath === '') {
            return ['success' => false, 'data' => null,
                    'message' => 'Directorio no encontrado: ' . $domain];
        }

        // Patrones de malware
        $patterns = [
            'eval(base64_decode'  => 'base64_eval',
            'eval(gzinflate'      => 'gzip_eval',
            'eval(str_rot13'      => 'rot13_eval',
            'preg_replace.*\/e'   => 'preg_replace_exec',
            'passthru('           => 'passthru',
            'shell_exec('         => 'shell_exec',
            'system('             => 'system_call',
            'base64_decode.*eval' => 'base64_eval_rev',
            '\$_POST.*eval'       => 'post_eval',
            '\$_GET.*eval'        => 'get_eval',
            'FilesMan'            => 'webshell_filesman',
            'c99shell'            => 'webshell_c99',
            'r57shell'            => 'webshell_r57',
        ];

        $findings  = [];
        $scanned   = 0;
        $phpInUploads = [];

        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($it as $file) {
                if ($scanned >= $maxFiles) break;
                if (!$file->isFile()) continue;

                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['php', 'phtml', 'php5', 'php7'], true)) continue;

                $scanned++;
                $path = $file->getPathname();

                // PHP en uploads
                if (strpos($path, '/uploads/') !== false) {
                    $phpInUploads[] = $path;
                }

                // Leer y escanear (solo primeros 100KB)
                $content = @file_get_contents($path, false, null, 0, 102400);
                if ($content === false) continue;

                foreach ($patterns as $pattern => $type) {
                    if (preg_match('/' . $pattern . '/i', $content)) {
                        $findings[] = [
                            'file'    => str_replace($basePath, '', $path),
                            'type'    => $type,
                            'pattern' => $pattern,
                            'size'    => $file->getSize(),
                            'mtime'   => date('Y-m-d H:i:s', $file->getMTime()),
                        ];
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            return ['success' => false, 'data' => null, 'message' => $e->getMessage()];
        }

        return [
            'success' => true,
            'data'    => [
                'base_path'     => $basePath,
                'files_scanned' => $scanned,
                'total_findings'=> count($findings),
                'php_in_uploads'=> $phpInUploads,
                'findings'      => $findings,
                'clean'         => count($findings) === 0 && count($phpInUploads) === 0,
            ],
            'message' => '',
        ];
    }
}
