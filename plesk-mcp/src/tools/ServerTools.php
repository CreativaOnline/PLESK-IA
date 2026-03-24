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
                $parts = explode(' ', $loadavg);
                $stats['cpu_load'] = [
                    '1min'  => (float) $parts[0],
                    '5min'  => (float) $parts[1],
                    '15min' => (float) $parts[2],
                ];
            }
        }

        // Memory from /proc/meminfo
        if (is_readable('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            if ($meminfo !== false) {
                $parsed = [];
                preg_match_all('/^(\w+):\s+(\d+)/m', $meminfo, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $parsed[$match[1]] = (int) $match[2];
                }
                $totalKb     = $parsed['MemTotal'] ?? 0;
                $freeKb      = $parsed['MemFree'] ?? 0;
                $availableKb = $parsed['MemAvailable'] ?? $freeKb;
                if ($totalKb > 0) {
                    $totalMb = (int) round($totalKb / 1024);
                    $freeMb  = (int) round($availableKb / 1024);
                    $usedMb  = $totalMb - $freeMb;
                    $stats['memory'] = [
                        'total_mb'     => $totalMb,
                        'used_mb'      => $usedMb,
                        'free_mb'      => $freeMb,
                        'percent_used' => round($usedMb / $totalMb * 100, 1),
                    ];
                }
            }
        }

        // Disk usage
        $totalDisk = @disk_total_space('/');
        $freeDisk  = @disk_free_space('/');
        if ($totalDisk !== false && $freeDisk !== false && $totalDisk > 0) {
            $totalGb = round($totalDisk / 1073741824, 2);
            $freeGb  = round($freeDisk / 1073741824, 2);
            $usedGb  = round(($totalDisk - $freeDisk) / 1073741824, 2);
            $stats['disk'] = [
                'total_gb'     => $totalGb,
                'used_gb'      => $usedGb,
                'free_gb'      => $freeGb,
                'percent_used' => round($usedGb / $totalGb * 100, 1),
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
