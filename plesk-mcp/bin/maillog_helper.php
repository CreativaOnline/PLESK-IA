<?php
/**
 * Maillog Helper — ejecutado via sudo desde LogTools::scanMaillog()
 * Uso: sudo php maillog_helper.php <lines> [filter]
 *
 * Detecta automáticamente la ruta del log de correo.
 * Fallback a journalctl si no hay archivo de log.
 * Devuelve JSON por stdout.
 */

$lines  = (int)($argv[1] ?? 500);
$filter = $argv[2] ?? '';

// --- 1. Detectar ruta del log ---
$logCandidates = [
    '/var/log/mail.log',
    '/var/log/maillog',
    '/var/log/mail/mail.log',
];

$logFile = '';
$source  = '';

foreach ($logCandidates as $candidate) {
    if (file_exists($candidate) && is_readable($candidate)) {
        $logFile = $candidate;
        $source  = 'file';
        break;
    }
}

// --- 2. Leer líneas del log o usar journalctl ---
$rawLines = [];

if ($logFile !== '') {
    // Leer desde archivo
    $output = [];
    exec('tail -' . $lines . ' ' . escapeshellarg($logFile) . ' 2>/dev/null', $output);
    $rawLines = $output;
} else {
    // Fallback: journalctl
    $journalCmd = 'journalctl -u postfix --no-pager -n ' . $lines . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($journalCmd, $output, $exitCode);

    if ($exitCode === 0 && count($output) > 0) {
        $rawLines = $output;
        $source   = 'journalctl';
        $logFile  = 'journalctl -u postfix';
    } else {
        // Nada funciona — devolver error descriptivo
        $triedPaths = implode(', ', $logCandidates);
        echo json_encode([
            'error'        => true,
            'log_file'     => null,
            'source'       => 'none',
            'stats'        => null,
            'top_senders'  => null,
            'suspicious_lines' => null,
            'message'      => 'No se encontró log de correo. Rutas probadas: ' . $triedPaths
                            . '. journalctl -u postfix tampoco devolvió datos (exit=' . $exitCode . ').',
        ]);
        exit(0);
    }
}

// --- 3. Analizar las líneas ---
$spamPatterns = [
    'status=deferred', 'status=bounced', 'relay=none',
    'blocked', 'spam', 'reject', 'rate limit',
    'does not pass', 'SPF', 'DKIM.*fail',
];

$suspicious = [];
$senders    = [];
$stats      = [
    'total_lines' => count($rawLines),
    'deferred'    => 0,
    'bounced'     => 0,
    'sent'        => 0,
    'rejected'    => 0,
];

foreach ($rawLines as $line) {
    // Filtro opcional
    if ($filter !== '' && stripos($line, $filter) === false) {
        continue;
    }

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

echo json_encode([
    'log_file'         => $logFile,
    'source'           => $source,
    'stats'            => $stats,
    'top_senders'      => array_slice($senders, 0, 20, true),
    'suspicious_lines' => array_slice($suspicious, -50),
]);
