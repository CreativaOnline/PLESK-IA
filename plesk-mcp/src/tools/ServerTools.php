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
        // Strategy 1: API REST
        $result = $client->get('/api/v2/server/statistics');
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        // Strategy 2: Read system metrics directly

        // CPU
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

        // RAM
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

        // Disco
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

    public static function listIpAddresses(PleskClient $client, array $args = []): array
    {
        $result = $client->get('/api/v2/server/ips');
        if (!$result['ok']) {
            return ['success' => false, 'data' => null, 'message' => $result['error']];
        }
        return ['success' => true, 'data' => $result['data'], 'message' => ''];
    }
}
