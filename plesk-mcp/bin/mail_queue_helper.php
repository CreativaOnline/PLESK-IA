<?php
// Script auxiliar para leer la cola de correo con permisos elevados.
// Debe ejecutarse como usuario con acceso a postfix (ej: root o postfix).
// Llamado desde MailTools.php via proc_open con sudo.

$result = ['total' => 0, 'queues' => [], 'mailq_output' => ''];

// Contar mensajes por cola en el spool de Postfix
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
    $result['queues'][$queue] = $count;
    $result['total'] += $count;
}

// Obtener output de mailq
foreach (['/usr/sbin/mailq', '/usr/bin/mailq'] as $path) {
    if (is_executable($path)) {
        exec($path . ' 2>/dev/null', $output);
        $result['mailq_output'] = implode("\n", $output);
        break;
    }
}

echo json_encode($result);
