<?php

$domain  = $argv[1] ?? '';
$command = $argv[2] ?? '';

if ($domain === '' || $command === '') {
    echo json_encode(['error' => 'Se requieren los parámetros domain y command.']);
    exit(1);
}

$domain = basename($domain);

$pathCandidates = [
    '/var/www/vhosts/' . $domain . '/httpdocs',
    '/var/www/vhosts/' . $domain . '/public_html',
    '/var/www/vhosts/' . $domain,
];

$wpPath = '';
foreach ($pathCandidates as $candidate) {
    if (is_file($candidate . '/wp-config.php')) {
        $wpPath = $candidate;
        break;
    }
}

if ($wpPath === '') {
    echo json_encode([
        'error' => 'No se encontró WordPress en el dominio: ' . $domain
                 . '. Rutas probadas: ' . implode(', ', $pathCandidates),
    ]);
    exit(1);
}

$wpCliBin = '';
$wpCliCandidates = [
    '/usr/local/bin/wp',
    '/usr/bin/wp',
    '/opt/plesk/php/8.2/bin/wp',
    '/usr/local/sbin/wp',
];

foreach ($wpCliCandidates as $bin) {
    if (is_executable($bin)) {
        $wpCliBin = $bin;
        break;
    }
}

if ($wpCliBin === '') {
    echo json_encode(['error' => 'WP-CLI no encontrado. Rutas probadas: ' . implode(', ', $wpCliCandidates)]);
    exit(1);
}

$allowedPrefixes = [
    'core version',
    'core check-update',
    'core is-installed',
    'plugin list',
    'plugin status',
    'plugin get',
    'plugin verify-checksums',
    'theme list',
    'theme status',
    'theme get',
    'user list',
    'user count',
    'user get',
    'option get',
    'option list',
    'db size',
    'db tables',
    'db check',
    'cron event list',
    'cron schedule list',
    'config list',
    'config get',
    'config path',
    'cache type',
    'rewrite list',
    'post list',
    'post get',
    'term list',
    'taxonomy list',
    'comment list',
    'comment count',
    'sidebar list',
    'widget list',
    'menu list',
    'menu item list',
    'role list',
    'cap list',
    'language core list',
    'language plugin list',
    'language theme list',
    'site list',
    'search-replace --dry-run',
    // Evaluación
    'eval-file',
    'eval',
    'transient list',
    'transient get',
    'media list',
    'maintenance-mode status',
    // Plugins (escritura)
    'plugin install',
    'plugin activate',
    'plugin deactivate',
    'plugin delete',
    'plugin update',
    // Temas (escritura)
    'theme install',
    'theme activate',
    'theme delete',
    'theme update',
    // Core (actualización)
    'core update',
    'core update-db',
    // Usuarios (escritura)
    'user create',
    'user update',
    'user delete',
    'user set-role',
    // Opciones (escritura)
    'option update',
    'option add',
    'option delete',
    // Base de datos
    'db optimize',
    'db repair',
    'db export',
    'db import',
    // Search-replace real
    'search-replace',
    // Cache
    'cache flush',
    // Transients
    'transient delete',
    // Mantenimiento
    'maintenance-mode activate',
    'maintenance-mode deactivate',
    // Cron
    'cron event run',
    'cron event delete',
    // Media
    'media regenerate',
    // Rewrite
    'rewrite flush',
    // Idiomas
    'language core install',
    'language plugin install',
    'language theme install',
];

$commandAllowed = false;
foreach ($allowedPrefixes as $prefix) {
    if (strpos($command, $prefix) === 0) {
        $commandAllowed = true;
        break;
    }
}

if (!$commandAllowed) {
    echo json_encode([
        'error'            => 'Comando WP-CLI no permitido: ' . $command,
        'allowed_commands' => $allowedPrefixes,
    ]);
    exit(1);
}

$fullCmd = escapeshellarg($wpCliBin)
         . ' --path=' . escapeshellarg($wpPath)
         . ' --allow-root'
         . ' ' . $command
         . ' 2>&1';

$output   = [];
$exitCode = 0;
exec($fullCmd, $output, $exitCode);
$outputStr = implode("\n", $output);

echo json_encode([
    'domain'    => $domain,
    'wp_path'   => $wpPath,
    'wp_cli'    => $wpCliBin,
    'command'   => $command,
    'exit_code' => $exitCode,
    'output'    => $outputStr,
]);
