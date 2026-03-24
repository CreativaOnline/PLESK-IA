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
        $loadavg = @file_get_contents('/proc/loadavg');
        if ($loadavg !== false && $loadavg !== '') {
            $parts = explode(' ', trim($loadavg));
            if (count($parts) >= 3) {
                $stats['cpu_load'] = [
                    'load_1min'  => (float) $parts[0],
                    'load_5min'  => (float) $parts[1],
                    'load_15min' => (float) $parts[2],
                ];
            }
        }

        // Memory from /proc/meminfo
        $meminfo = @file_get_contents('/proc/meminfo');
        if ($meminfo !== false && $meminfo !== '') {
            $mem = [];
            $matches = [];
            preg_match_all('/^(MemTotal|MemFree|MemAvailable):\s+(\d+)\s+kB/m', $meminfo, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $key   = $match[1];
                $valKb = (int) $match[2];
                $valMb = round($valKb / 1024, 1);
                if ($key === 'MemTotal') {
                    $mem['total_kb'] = $valKb;
                    $mem['total_mb'] = $valMb;
                } elseif ($key === 'MemFree') {
                    $mem['free_kb'] = $valKb;
                    $mem['free_mb'] = $valMb;
                } elseif ($key === 'MemAvailable') {
                    $mem['available_kb'] = $valKb;
                    $mem['available_mb'] = $valMb;
                }
            }
            if (isset($mem['total_kb']) && $mem['total_kb'] > 0) {
                $used = $mem['total_kb'] - ($mem['available_kb'] ?? $mem['free_kb'] ?? 0);
                $mem['used_mb']      = round($used / 1024, 1);
                $mem['used_percent'] = round($used / $mem['total_kb'] * 100, 1);
            }
            $stats['memory'] = $mem;
        }

        // Disk usage
        $totalDisk = @disk_total_space('/');
        $freeDisk  = @disk_free_space('/');
        if ($totalDisk !== false && $freeDisk !== false && $totalDisk > 0) {
            $usedDisk = $totalDisk - $freeDisk;
            $stats['disk'] = [
                'total_gb'     => round($totalDisk / 1024 / 1024 / 1024, 2),
                'free_gb'      => round($freeDisk / 1024 / 1024 / 1024, 2),
                'used_gb'      => round($usedDisk / 1024 / 1024 / 1024, 2),
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
