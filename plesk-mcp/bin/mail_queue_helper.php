<?php
$result = [
    'mail'   => ['total' => 0, 'queues' => [], 'mailq_output' => ''],
    'system' => ['cpu_load' => [], 'memory' => [], 'disk' => []],
];
$spoolDirs = [
    'deferred' => '/var/spool/postfix/deferred',
    'active'   => '/var/spool/postfix/active',
    'incoming' => '/var/spool/postfix/incoming',
    'hold'     => '/var/spool/postfix/hold',
    'bounce'   => '/var/spool/postfix/bounce',
];
foreach ($spoolDirs as $queue => $dir) {
    $count = 0;
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile()) $count++;
        }
    }
    $result['mail']['queues'][$queue] = $count;
    $result['mail']['total'] += $count;
}
foreach (['/usr/sbin/mailq', '/usr/bin/mailq'] as $path) {
    if (is_executable($path)) {
        exec($path . ' 2>/dev/null', $output);
        $result['mail']['mailq_output'] = implode("\n", $output);
        break;
    }
}
$loadavg = @file_get_contents('/proc/loadavg');
if ($loadavg !== false) {
    $parts = explode(' ', trim($loadavg));
    $result['system']['cpu_load'] = [
        '1min'  => (float)($parts[0] ?? 0),
        '5min'  => (float)($parts[1] ?? 0),
        '15min' => (float)($parts[2] ?? 0),
    ];
}
$meminfo = @file_get_contents('/proc/meminfo');
if ($meminfo !== false) {
    preg_match('/MemTotal:\s+(\d+)/i',     $meminfo, $mT);
    preg_match('/MemAvailable:\s+(\d+)/i', $meminfo, $mA);
    $t = (int)($mT[1] ?? 0);
    $a = (int)($mA[1] ?? 0);
    $u = $t - $a;
    $result['system']['memory'] = [
        'total_mb'     => (int)round($t / 1024),
        'used_mb'      => (int)round($u / 1024),
        'free_mb'      => (int)round($a / 1024),
        'percent_used' => $t > 0 ? round($u / $t * 100, 1) : 0,
    ];
}
$dt = @disk_total_space('/');
$df = @disk_free_space('/');
if ($dt !== false && $df !== false) {
    $du = $dt - $df;
    $result['system']['disk'] = [
        'total_gb'     => round($dt / 1073741824, 2),
        'used_gb'      => round($du / 1073741824, 2),
        'free_gb'      => round($df / 1073741824, 2),
        'percent_used' => round($du / $dt * 100, 1),
    ];
}
echo json_encode($result);
