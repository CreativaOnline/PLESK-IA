<?php
/**
 * Helper para listar buzones de correo de un dominio.
 * Se ejecuta como root via sudo.
 * Uso: sudo /opt/plesk/php/8.2/bin/php mailboxes_helper.php <dominio>
 */

$domain = isset($argv[1]) ? basename($argv[1]) : '';
if ($domain === '') {
    echo json_encode(['error' => 'Domain argument required']);
    exit(1);
}

$output   = [];
$exitCode = 0;
exec('plesk bin mail --list -domain=' . escapeshellarg($domain) . ' 2>/dev/null', $output, $exitCode);

$mailboxes = [];
foreach ($output as $line) {
    $line = trim($line);
    if ($line !== '' && strpos($line, '@') !== false) {
        $mailboxes[] = ['email' => $line];
    }
}

echo json_encode([
    'exit'      => $exitCode,
    'domain'    => $domain,
    'mailboxes' => $mailboxes,
]);
