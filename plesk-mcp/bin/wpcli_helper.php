<?php
/**
 * WP-CLI Helper — ejecutado via sudo desde WpCliTools::executeWpCli()
 * Uso: sudo php wpcli_helper.php <domain> <wp_command>
 *
 * Valida dominio, busca wp-cli, valida whitelist de subcomandos.
 * Ejecuta wp-cli con --path y --allow-root.
 * Devuelve JSON por stdout.
 */

$domain  = $argv[1] ?? '';
$command = $argv[2] ?? '';

if ($domain === '' || $command === '') {
    echo json_encode(['error' => 'Se requieren los parámetros domain y command.']);
    exit(1);
}

// --- 1. Validar dominio y encontrar ruta WordPress ---
$domain = basename($domain); // evitar path traversal

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

// --- 2. Encontrar binario wp-cli ---
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

// --- 3. Whitelist de subcomandos permitidos (solo lectura) ---
$allowedPrefixes = [
    // Core
    'core version',
    'core check-update',
    'core is-installed',
    // Plugins
    'plugin list',
    'plugin status',
    'plugin get',
    'plugin verify-checksums',
    // Temas
    'theme list',
    'theme status',
    'theme get',
    // Usuarios
    'user list',
    'user count',
    'user get',
    // Opciones (solo lectura)
    'option get',
    'option list',
    // Base de datos
    'db size',
    'db tables',
    'db check',
    // Cron
    'cron event list',
    'cron schedule list',
    // Config
    'config list',
    'config get',
    'config path',
    // Cache
    'cache type',
    // Rewrite
    'rewrite list',
    // Posts (solo lectura)
    'post list',
    'post get',
    // Taxonomías
    'term list',
    'taxonomy list',
    // Comentarios
    'comment list',
    'comment count',
    // Sidebars / widgets
    'sidebar list',
    'widget list',
    // Menús
    'menu list',
    'menu item list',
    // Roles
    'role list',
    'cap list',
    // Idioma
    'language core list',
    'language plugin list',
    'language theme list',
    // Multisite
    'site list',
    // Búsqueda (solo dry-run)
    'search-replace --dry-run',
    // Evaluación
    'eval-file',
    // Transients
    'transient list',
    'transient get',
    // Media
    'media list',
    // Maintenance
    'maintenance-mode status',
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

// --- 4. Construir y ejecutar comando ---
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
