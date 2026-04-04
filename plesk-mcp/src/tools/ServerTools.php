<?php

class ServerTools
{
    public static function serverInfo(PleskClient $client, array $args = []): array
    {
        $result = $client->get('/api/v2/server');
        if (!$result['ok']) {
            return ['success' => false, 'data' => null, 'message' => $result['error']];
        }
        return ['success' => true, 'data' => $result['data'], 'message' => ''];
    }

    public static function serverStats(PleskClient $client, array $args = []): array
    {
        $result = $client->get('/api/v2/server/statistics');
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        $data = self::runHelper();
        if ($data !== null && isset($data['system'])) {
            return [
                'success' => true,
                'data'    => [
                    'source'   => 'helper',
                    'cpu_load' => $data['system']['cpu_load'],
                    'memory'   => $data['system']['memory'],
                    'disk'     => $data['system']['disk'],
                ],
                'message' => '',
            ];
        }

        $loadavg = @file_get_contents('/proc/loadavg');
        if ($loadavg !== false) {
            $parts = explode(' ', trim($loadavg));
            $cpu = [
                '1min'  => (float) ($parts[0] ?? 0),
                '5min'  => (float) ($parts[1] ?? 0),
                '15min' => (float) ($parts[2] ?? 0),
            ];
        } else {
            $cpu = [];
        }

        $meminfo = @file_get_contents('/proc/meminfo');
        if ($meminfo !== false) {
            preg_match('/MemTotal:\s+(\d+)/i',     $meminfo, $mTotal);
            preg_match('/MemFree:\s+(\d+)/i',      $meminfo, $mFree);
            preg_match('/MemAvailable:\s+(\d+)/i', $meminfo, $mAvail);
            $total = (int) ($mTotal[1] ?? 0);
            $avail = (int) ($mAvail[1] ?? 0);
            $used  = $total - $avail;
            $memory = [
                'total_mb'     => (int) round($total / 1024),
                'used_mb'      => (int) round($used  / 1024),
                'free_mb'      => (int) round($avail / 1024),
                'percent_used' => $total > 0 ? round($used / $total * 100, 1) : 0,
            ];
        } else {
            $memory = [];
        }

        $total = @disk_total_space('/');
        $free  = @disk_free_space('/');
        if ($total !== false && $free !== false) {
            $used = $total - $free;
            $disk = [
                'total_gb'     => round($total / 1073741824, 2),
                'used_gb'      => round($used  / 1073741824, 2),
                'free_gb'      => round($free  / 1073741824, 2),
                'percent_used' => round($used  / $total * 100, 1),
            ];
        } else {
            $disk = [];
        }

        return [
            'success' => true,
            'data'    => [
                'source'   => 'system',
                'cpu_load' => $cpu,
                'memory'   => $memory,
                'disk'     => $disk,
            ],
            'message' => '',
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

    public static function listIpAddresses(PleskClient $client, array $args = []): array
    {
        $result = $client->get('/api/v2/server/ips');
        if (!$result['ok']) {
            return ['success' => false, 'data' => null, 'message' => $result['error']];
        }
        return ['success' => true, 'data' => $result['data'], 'message' => ''];
    }
}
