<?php

class WpCliTools
{
    public static function executeWpCli(PleskClient $client, array $args): array
    {
        $domain  = trim($args['domain'] ?? '');
        $command = trim($args['command'] ?? '');

        if ($domain === '') {
            return ['success' => false, 'data' => null,
                    'message' => 'El parámetro "domain" es requerido.'];
        }

        if ($command === '') {
            return ['success' => false, 'data' => null,
                    'message' => 'El parámetro "command" es requerido.'];
        }

        $phpBin     = '/opt/plesk/php/8.2/bin/php';
        $helperPath = realpath(__DIR__ . '/../../bin/wpcli_helper.php');
        $domainArg  = escapeshellarg($domain);
        $commandArg = escapeshellarg($command);

        $cmd = 'sudo ' . escapeshellarg($phpBin) . ' '
             . escapeshellarg($helperPath) . ' '
             . $domainArg . ' ' . $commandArg;

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['success' => false, 'data' => null,
                    'message' => 'No se pudo ejecutar el helper de WP-CLI.'];
        }

        fclose($pipes[0]);
        $output   = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr   = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($output === '') {
            return ['success' => false, 'data' => null,
                    'message' => 'El helper de WP-CLI no devolvió datos (exit=' . $exitCode . ').'
                               . ($stderr !== '' ? ' stderr: ' . trim($stderr) : '')];
        }

        $data = json_decode($output, true);
        if (!is_array($data)) {
            return ['success' => false, 'data' => null,
                    'message' => 'Respuesta JSON inválida del helper de WP-CLI.'];
        }

        // El helper devuelve {"error":"..."} con exit(1) en caso de error
        if (isset($data['error'])) {
            return ['success' => false, 'data' => $data,
                    'message' => $data['error']];
        }

        return ['success' => true, 'data' => $data, 'message' => ''];
    }
}
