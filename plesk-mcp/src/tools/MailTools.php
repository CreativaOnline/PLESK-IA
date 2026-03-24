<?php

class MailTools
{
    public static function mailQueue(PleskClient $client, array $args = []): array
    {
        // Strategy 1: API REST
        $result = $client->get('/api/v2/mail/messages');
        if ($result['ok']) {
            return [
                'success' => true,
                'data'    => [
                    'source'       => 'api',
                    'total'        => is_array($result['data']) ? count($result['data']) : 0,
                    'queues'       => $result['data'],
                    'mailq_output' => null,
                ],
                'message' => '',
            ];
        }

        // Strategy 2: Count files in postfix spool directories
        $spoolDirs = [
            'deferred' => '/var/spool/postfix/deferred',
            'active'   => '/var/spool/postfix/active',
            'incoming' => '/var/spool/postfix/incoming',
            'hold'     => '/var/spool/postfix/hold',
            'bounce'   => '/var/spool/postfix/bounce',
        ];

        $queues = [];
        $total  = 0;
        $spoolReadable = false;

        foreach ($spoolDirs as $name => $dir) {
            if (is_dir($dir) && is_readable($dir)) {
                $spoolReadable = true;
                $count = 0;
                try {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    foreach ($iterator as $file) {
                        if ($file->isFile()) {
                            $count++;
                        }
                    }
                } catch (\Exception $e) {
                    $count = 0;
                }
                $queues[$name] = $count;
                $total += $count;
            } else {
                $queues[$name] = null;
            }
        }

        if ($spoolReadable) {
            return [
                'success' => true,
                'data'    => [
                    'source'       => 'postfix_spool',
                    'total'        => $total,
                    'queues'       => $queues,
                    'mailq_output' => null,
                ],
                'message' => '',
            ];
        }

        // Strategy 3: Execute mailq binary
        $mailqPaths = ['/usr/sbin/mailq', '/usr/bin/mailq', '/usr/local/sbin/mailq'];
        foreach ($mailqPaths as $mailqPath) {
            if (is_executable($mailqPath)) {
                $output   = [];
                $exitCode = 0;
                exec($mailqPath . ' 2>&1', $output, $exitCode);
                $outputStr = implode("\n", $output);

                $msgCount = 0;
                if (preg_match('/(\d+)\s+Request/', $outputStr, $m)) {
                    $msgCount = (int) $m[1];
                } elseif (stripos($outputStr, 'Mail queue is empty') !== false) {
                    $msgCount = 0;
                }

                return [
                    'success' => true,
                    'data'    => [
                        'source'       => 'mailq',
                        'total'        => $msgCount,
                        'queues'       => null,
                        'mailq_output' => $outputStr,
                    ],
                    'message' => '',
                ];
            }
        }

        return [
            'success' => false,
            'data'    => null,
            'message' => 'No se pudo obtener la cola de correo. API REST, spool de postfix y mailq no disponibles.',
        ];
    }

    public static function listMailboxes(PleskClient $client, array $args): array
    {
        $domain = $args['domain'] ?? '';
        if ($domain === '') {
            return ['success' => false, 'data' => null, 'message' => 'El parámetro "domain" es requerido.'];
        }
        $result = $client->get('/api/v2/mail-domains/' . urlencode($domain) . '/mail-users');
        if (!$result['ok']) {
            return ['success' => false, 'data' => null, 'message' => $result['error']];
        }
        return ['success' => true, 'data' => $result['data'], 'message' => ''];
    }

    public static function mailDomainInfo(PleskClient $client, array $args): array
    {
        $domain = $args['domain'] ?? '';
        if ($domain === '') {
            return ['success' => false, 'data' => null, 'message' => 'El parámetro "domain" es requerido.'];
        }
        $result = $client->get('/api/v2/mail-domains/' . urlencode($domain));
        if (!$result['ok']) {
            return ['success' => false, 'data' => null, 'message' => $result['error']];
        }
        return ['success' => true, 'data' => $result['data'], 'message' => ''];
    }

    public static function clearMailQueue(PleskClient $client, array $args): array
    {
        $confirm = $args['confirm'] ?? false;
        if ($confirm !== true) {
            return [
                'success' => false,
                'data'    => null,
                'message' => 'Debes confirmar con confirm:true para ejecutar esta acción.',
            ];
        }

        // Strategy 1: postsuper -d ALL
        $postsuperPath = '/usr/sbin/postsuper';
        if (is_executable($postsuperPath)) {
            $output   = [];
            $exitCode = 0;
            exec($postsuperPath . ' -d ALL 2>&1', $output, $exitCode);
            $outputStr = implode("\n", $output);

            return [
                'success' => $exitCode === 0,
                'data'    => ['source' => 'postsuper', 'output' => $outputStr, 'exit_code' => $exitCode],
                'message' => $exitCode === 0 ? '' : 'postsuper terminó con código ' . $exitCode . ': ' . $outputStr,
            ];
        }

        // Strategy 2: Plesk CLI fallback
        $result = $client->cli(['repair', '--mail']);
        if ($result['ok']) {
            return ['success' => true, 'data' => ['source' => 'plesk_cli', 'output' => $result['data']], 'message' => ''];
        }

        return [
            'success' => false,
            'data'    => null,
            'message' => 'No se pudo limpiar la cola. postsuper no disponible y Plesk CLI retornó: ' . $result['error'],
        ];
    }
}
