<?php

class MailTools
{
    public static function mailQueue(PleskClient $client, array $args = []): array
    {
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

        $data = self::runHelper();
        if ($data !== null && isset($data['mail'])) {
            return [
                'success' => true,
                'data'    => [
                    'source'       => 'helper',
                    'total'        => $data['mail']['total']        ?? 0,
                    'queues'       => $data['mail']['queues']       ?? [],
                    'mailq_output' => $data['mail']['mailq_output'] ?? '',
                ],
                'message' => '',
            ];
        }

        $xmlResult = self::getQueueViaXmlApi($client);
        if ($xmlResult !== null) {
            return $xmlResult;
        }

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

        $binaries = [
            '/usr/sbin/mailq'            => ['mailq'],
            '/usr/bin/mailq'             => ['mailq'],
            '/usr/local/sbin/mailq'      => ['mailq'],
            '/usr/sbin/postqueue'        => ['postqueue', '-p'],
            '/usr/local/psa/bin/postfix' => ['postqueue', '-p'],
        ];

        foreach ($binaries as $binPath => $cmdParts) {
            if (!is_executable($binPath)) {
                continue;
            }
            $outputStr = self::runViaProcOpen($binPath, array_slice($cmdParts, 1));
            if ($outputStr === null) {
                continue;
            }

            $msgCount = 0;
            if (preg_match('/(\d+)\s+Request/', $outputStr, $m)) {
                $msgCount = (int) $m[1];
            } elseif (stripos($outputStr, 'Mail queue is empty') !== false) {
                $msgCount = 0;
            }

            return [
                'success' => true,
                'data'    => [
                    'source'       => 'proc_open',
                    'total'        => $msgCount,
                    'queues'       => null,
                    'mailq_output' => $outputStr,
                ],
                'message' => '',
            ];
        }

        return [
            'success' => false,
            'data'    => null,
            'message' => 'No se pudo obtener la cola de correo. API REST, helper sudo, XML-RPC, spool de postfix y mailq/postqueue no disponibles.',
        ];
    }

    public static function listMailboxes(PleskClient $client, array $args): array
    {
        $domain = $args['domain'] ?? '';
        if ($domain === '') {
            return ['success' => false, 'data' => null, 'message' => 'El parámetro "domain" es requerido.'];
        }

        $result = $client->get('/api/v2/mail-domains/' . urlencode($domain) . '/mail-users');
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        $safeDomain = basename($domain);

        $phpBin = '/opt/plesk/php/8.2/bin/php';
        $script = '$o=[]; exec("plesk bin mail --list -domain=" . escapeshellarg($argv[1]) . " 2>/dev/null", $o, $c);'
                . '$m=[]; foreach($o as $l){$l=trim($l); if($l!=="" && strpos($l,"@")!==false) $m[]=["email"=>$l];}'
                . 'echo json_encode(["exit"=>$c,"mailboxes"=>$m]);';
        $cmd = 'sudo ' . escapeshellarg($phpBin) . ' -r ' . escapeshellarg($script)
             . ' -- ' . escapeshellarg($safeDomain);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (is_resource($process)) {
            fclose($pipes[0]);
            $output   = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode === 0 && $output !== '') {
                $parsed = json_decode($output, true);
                if (is_array($parsed) && isset($parsed['mailboxes'])) {
                    return [
                        'success' => true,
                        'data'    => ['source' => 'cli_sudo', 'domain' => $safeDomain, 'mailboxes' => $parsed['mailboxes']],
                        'message' => '',
                    ];
                }
            }
        }

        return ['success' => false, 'data' => null,
                'message' => 'No se pudo listar buzones de ' . $safeDomain . '. API REST y CLI no disponibles.'];
    }

    public static function mailDomainInfo(PleskClient $client, array $args): array
    {
        $domain = $args['domain'] ?? '';
        if ($domain === '') {
            return ['success' => false, 'data' => null, 'message' => 'El parámetro "domain" es requerido.'];
        }

        $result = $client->get('/api/v2/mail-domains/' . urlencode($domain));
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        $safeDomain = basename($domain);
        $cliResult = $client->cli(['domain', '--info', $safeDomain]);
        if ($cliResult['ok'] && !empty($cliResult['data'])) {
            $info = ['source' => 'cli', 'domain' => $safeDomain, 'raw' => $cliResult['data']];
            $lines = is_string($cliResult['data'])
                ? array_map('trim', explode("\n", $cliResult['data']))
                : [];
            foreach ($lines as $line) {
                if (preg_match('/^(.+?):\s+(.+)$/', $line, $m)) {
                    $key = strtolower(str_replace(' ', '_', trim($m[1])));
                    $info[$key] = trim($m[2]);
                }
            }
            return ['success' => true, 'data' => $info, 'message' => ''];
        }

        $dnsInfo = ['source' => 'dns', 'domain' => $safeDomain];
        $mx = @dns_get_record($safeDomain, DNS_MX);
        if (is_array($mx)) {
            $dnsInfo['mx'] = array_map(function ($r) {
                return ['priority' => $r['pri'] ?? 0, 'target' => $r['target'] ?? ''];
            }, $mx);
        }
        $txt = @dns_get_record($safeDomain, DNS_TXT);
        if (is_array($txt)) {
            foreach ($txt as $r) {
                $t = $r['txt'] ?? '';
                if (stripos($t, 'v=spf1') === 0)  $dnsInfo['spf'] = $t;
            }
        }
        $dmarc = @dns_get_record('_dmarc.' . $safeDomain, DNS_TXT);
        if (is_array($dmarc)) {
            foreach ($dmarc as $r) {
                $t = $r['txt'] ?? '';
                if (stripos($t, 'v=dmarc1') === 0)  $dnsInfo['dmarc'] = $t;
            }
        }

        if (isset($dnsInfo['mx']) || isset($dnsInfo['spf'])) {
            return ['success' => true, 'data' => $dnsInfo, 'message' => ''];
        }

        return ['success' => false, 'data' => null,
                'message' => 'No se pudo obtener info de correo de ' . $safeDomain . '. API REST, CLI y DNS no disponibles.'];
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

        $phpBin     = '/opt/plesk/php/8.2/bin/php';
        $postsuper  = '/usr/sbin/postsuper';
        $cmd        = 'sudo ' . escapeshellarg($phpBin) . ' -r '
                    . escapeshellarg(
                        'exec(' . var_export($postsuper . ' -d ALL 2>&1', true)
                        . ', $o, $c); echo json_encode(["output"=>implode("\n",$o),"exit"=>$c]);'
                      );
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (is_resource($process)) {
            fclose($pipes[0]);
            $output   = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            if ($exitCode === 0 && $output !== '') {
                $result = json_decode($output, true);
                if (is_array($result)) {
                    return [
                        'success' => $result['exit'] === 0,
                        'data'    => ['output' => $result['output']],
                        'message' => $result['exit'] === 0
                            ? 'Cola limpiada correctamente.'
                            : 'Error al limpiar la cola.',
                    ];
                }
            }
        }

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

    private static function runHelper(): ?array
    {
        $helperPath = realpath(__DIR__ . '/../../bin/mail_queue_helper.php');
        if ($helperPath === false) {
            $helperPath = dirname(__DIR__, 2) . '/bin/mail_queue_helper.php';
        }
        $phpBin     = '/opt/plesk/php/8.2/bin/php';
        $cmd        = 'sudo ' . escapeshellarg($phpBin) . ' '
                    . escapeshellarg($helperPath);
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) return null;
        fclose($pipes[0]);
        $output   = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0 || $output === '') return null;
        $data = json_decode($output, true);
        return is_array($data) ? $data : null;
    }

    private static function getQueueViaXmlApi(PleskClient $client): ?array
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<packet>'
            . '<server>'
            . '<get_mail_stat/>'
            . '</server>'
            . '</packet>';

        $result = $client->postXml('/enterprise/control/agent.php', $xml);
        if (!$result['ok'] || empty($result['data'])) {
            return null;
        }

        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($result['data']);
        libxml_use_internal_errors($prev);

        if ($doc === false) {
            return null;
        }

        $resultNode = $doc->server->get_mail_stat->result ?? null;
        if ($resultNode === null) {
            return null;
        }

        $status = (string) ($resultNode->status ?? '');
        if ($status !== 'ok') {
            return null;
        }

        $mailStat = [];
        $total = 0;

        if (isset($resultNode->in_queue)) {
            $mailStat['in_queue'] = (int) (string) $resultNode->in_queue;
            $total = $mailStat['in_queue'];
        }
        if (isset($resultNode->total_sent)) {
            $mailStat['total_sent'] = (int) (string) $resultNode->total_sent;
        }
        if (isset($resultNode->total_received)) {
            $mailStat['total_received'] = (int) (string) $resultNode->total_received;
        }

        if (empty($mailStat)) {
            foreach ($resultNode->children() as $child) {
                $name = $child->getName();
                if ($name !== 'status') {
                    $mailStat[$name] = (string) $child;
                }
            }
        }

        return [
            'success' => true,
            'data'    => [
                'source'       => 'xml_api',
                'total'        => $total,
                'queues'       => $mailStat,
                'mailq_output' => null,
            ],
            'message' => '',
        ];
    }

    private static function runViaProcOpen(string $binary, array $extraArgs = []): ?string
    {
        $cmd = escapeshellarg($binary);
        foreach ($extraArgs as $arg) {
            $cmd .= ' ' . escapeshellarg($arg);
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0 && $stdout === '' && $stderr === '') {
            return null;
        }

        return ($stdout !== '' ? $stdout : $stderr);
    }
}
