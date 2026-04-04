<?php

$mode = $argv[1] ?? '';

switch ($mode) {
    case 'grep_file':
        $path    = $argv[2] ?? '';
        $pattern = $argv[3] ?? '';
        $maxLines = (int)($argv[4] ?? 200);
        if ($maxLines < 1)    $maxLines = 200;
        if ($maxLines > 5000) $maxLines = 5000;
        grepFile($path, $pattern, $maxLines);
        break;

    case 'scan_sql':
        $path     = $argv[2] ?? '';
        $maxLines = (int)($argv[3] ?? 500);
        scanSql($path, $maxLines);
        break;

    case 'file_stats':
        $path = $argv[2] ?? '';
        fileStats($path);
        break;

    default:
        echo json_encode(['error' => 'Modo no válido. Usa: grep_file, scan_sql o file_stats']);
        exit(1);
}

function grepFile(string $path, string $pattern, int $maxLines): void
{
    if ($path === '' || $pattern === '') {
        echo json_encode(['error' => 'Se requieren path y pattern']);
        exit(1);
    }

    if (!validatePath($path)) {
        echo json_encode(['error' => 'Ruta no permitida: ' . $path]);
        exit(1);
    }

    $realPath = realpath($path);
    if ($realPath === false) {
        echo json_encode(['error' => 'Ruta no encontrada: ' . $path]);
        exit(1);
    }

    $cmd = 'grep -rn -i ' . escapeshellarg($pattern) . ' ' . escapeshellarg($realPath)
         . ' --include="*.log" --include="*.txt" --include="*.php" --include="*.conf"'
         . ' -m ' . $maxLines;

    $output = [];
    exec($cmd . ' 2>/dev/null', $output);

    echo json_encode([
        'mode'        => 'grep_file',
        'path'        => $realPath,
        'pattern'     => $pattern,
        'total_matches' => count($output),
        'max_lines'   => $maxLines,
        'matches'     => $output,
    ]);
}

function scanSql(string $path, int $maxLines): void
{
    if ($path === '') {
        $candidates = [
            '/var/log/mysql/error.log',
            '/var/log/mariadb/mariadb.log',
            '/var/log/mysql.log',
            '/var/log/mysqld.log',
            '/var/lib/mysql/' . gethostname() . '.log',
        ];
        foreach ($candidates as $c) {
            if (is_file($c) && is_readable($c)) {
                $path = $c;
                break;
            }
        }
    }

    if ($path === '' || !is_file($path) || !is_readable($path)) {
        echo json_encode(['error' => 'No se encontró log SQL legible. Especifica path.']);
        exit(1);
    }

    $suspiciousPatterns = [
        'DROP TABLE', 'DROP DATABASE', 'TRUNCATE',
        'INTO OUTFILE', 'INTO DUMPFILE', 'LOAD_FILE',
        'UNION SELECT', 'UNION ALL SELECT',
        'SLEEP(', 'BENCHMARK(', 'WAITFOR DELAY',
        'xp_cmdshell', 'information_schema',
        '0x', 'CHAR(', 'UNHEX(',
    ];

    $lines = [];
    $cmd = 'tail -n ' . $maxLines . ' ' . escapeshellarg($path);
    exec($cmd . ' 2>/dev/null', $lines);

    $findings = [];
    foreach ($lines as $i => $line) {
        $upper = strtoupper($line);
        foreach ($suspiciousPatterns as $pat) {
            if (strpos($upper, strtoupper($pat)) !== false) {
                $findings[] = [
                    'line_num'  => $i + 1,
                    'pattern'   => $pat,
                    'content'   => mb_substr($line, 0, 500),
                ];
                break;
            }
        }
    }

    echo json_encode([
        'mode'           => 'scan_sql',
        'path'           => $path,
        'lines_scanned'  => count($lines),
        'total_findings' => count($findings),
        'findings'       => array_slice($findings, 0, 200),
    ]);
}

function fileStats(string $path): void
{
    if ($path === '') {
        echo json_encode(['error' => 'Se requiere path']);
        exit(1);
    }

    if (!validatePath($path)) {
        echo json_encode(['error' => 'Ruta no permitida: ' . $path]);
        exit(1);
    }

    $realPath = realpath($path);
    if ($realPath === false) {
        echo json_encode(['error' => 'Ruta no encontrada: ' . $path]);
        exit(1);
    }

    $stat = @stat($realPath);
    if ($stat === false) {
        echo json_encode(['error' => 'No se pudo obtener stat de: ' . $realPath]);
        exit(1);
    }

    $info = [
        'mode'    => 'file_stats',
        'path'    => $realPath,
        'type'    => filetype($realPath),
        'size'    => $stat['size'],
        'size_human' => formatBytes($stat['size']),
        'permissions' => substr(sprintf('%o', $stat['mode']), -4),
        'owner'   => function_exists('posix_getpwuid') ? (posix_getpwuid($stat['uid'])['name'] ?? $stat['uid']) : $stat['uid'],
        'group'   => function_exists('posix_getgrgid') ? (posix_getgrgid($stat['gid'])['name'] ?? $stat['gid']) : $stat['gid'],
        'modified' => date('Y-m-d H:i:s', $stat['mtime']),
        'accessed' => date('Y-m-d H:i:s', $stat['atime']),
        'created'  => date('Y-m-d H:i:s', $stat['ctime']),
    ];

    if (is_dir($realPath)) {
        $count = 0;
        $totalSize = 0;
        $cmd = 'find ' . escapeshellarg($realPath) . ' -maxdepth 1 -type f';
        $files = [];
        exec($cmd . ' 2>/dev/null', $files);
        $info['file_count'] = count($files);

        $cmd2 = 'du -sb ' . escapeshellarg($realPath);
        $du = [];
        exec($cmd2 . ' 2>/dev/null', $du);
        if (!empty($du[0])) {
            $parts = explode("\t", $du[0]);
            $info['total_size'] = (int)$parts[0];
            $info['total_size_human'] = formatBytes((int)$parts[0]);
        }
    }

    echo json_encode($info);
}

function validatePath(string $path): bool
{
    $allowedPrefixes = [
        '/var/log/',
        '/usr/local/psa/var/log/',
        '/var/www/vhosts/',
        '/etc/postfix/',
        '/var/lib/mysql/',
    ];

    $realPath = realpath($path);
    if ($realPath === false) {
        $realPath = $path;
    }

    foreach ($allowedPrefixes as $prefix) {
        if (strpos($realPath, $prefix) === 0) {
            return true;
        }
    }
    return false;
}

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $size = (float)$bytes;
    while ($size >= 1024 && $i < 4) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}
