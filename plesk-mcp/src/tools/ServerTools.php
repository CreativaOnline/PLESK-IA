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
        $stats = ['source' => 'system', 'cpu_load' => [], 'memory' => [], 'disk' => []];

        // CPU load from /proc/loadavg
        if (is_readable('/proc/loadavg')) {
            $loadavg = file_get_contents('/proc/loadavg');
            if ($loadavg !== false) {
                $parts = explode(' ', trim($loadavg));
                $stats['cpu_load'] = [
                    'load_1min'  => (float) ($parts[0] ?? 0),
                    'load_5min'  => (float) ($parts[1] ?? 0),
                    'load_15min' => (float) ($parts[2] ?? 0),
                ];
            }
        }

        // Memory from /proc/meminfo
        if (is_readable('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            if ($meminfo !== false) {
                $mem = [];
                if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $m)) {
                    $mem['total_kb'] = (int) $m[1];
                    $mem['total_mb'] = round((int) $m[1] / 1024, 1);
                }
                if (preg_match('/MemFree:\s+(\d+)\s+kB/', $meminfo, $m)) {
                    $mem['free_kb'] = (int) $m[1];
                    $mem['free_mb'] = round((int) $m[1] / 1024, 1);
                }
                if (preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $m)) {
                    $mem['available_kb'] = (int) $m[1];
                    $mem['available_mb'] = round((int) $m[1] / 1024, 1);
                }
                if (isset($mem['total_kb']) && $mem['total_kb'] > 0) {
                    $used = $mem['total_kb'] - ($mem['available_kb'] ?? $mem['free_kb'] ?? 0);
                    $mem['used_mb']     = round($used / 1024, 1);
                    $mem['used_percent'] = round($used / $mem['total_kb'] * 100, 1);
                }
                $stats['memory'] = $mem;
            }
        }

        // Disk usage
        $totalDisk = disk_total_space('/');
        $freeDisk  = disk_free_space('/');
        if ($totalDisk !== false && $freeDisk !== false) {
            $usedDisk = $totalDisk - $freeDisk;
            $stats['disk'] = [
                'total_gb'     => round($totalDisk / 1073741824, 2),
                'free_gb'      => round($freeDisk / 1073741824, 2),
                'used_gb'      => round($usedDisk / 1073741824, 2),
                'used_percent' => round($usedDisk / $totalDisk * 100, 1),
            ];
        }

        return ['success' => true, 'data' => $stats, 'message' => ''];
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
