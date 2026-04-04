<?php

class FileTools
{
    private static array $allowedPrefixes = [
        '/var/www/vhosts/',
        '/var/log/',
        '/usr/local/psa/var/log/',
        '/etc/postfix/',
    ];

    public static function readFile(PleskClient $client, array $args): array
    {
        $path     = $args['path'] ?? '';
        $maxBytes = (int)($args['max_bytes'] ?? 100000);

        if ($path === '') {
            return ['success' => false, 'data' => null,
                    'message' => 'El parámetro "path" es requerido.'];
        }

        $phpBin     = '/opt/plesk/php/8.2/bin/php';
        $helperPath = realpath(__DIR__ . '/../../bin/file_helper.php');
        if ($helperPath === false) {
            $helperPath = __DIR__ . '/../../bin/file_helper.php';
        }
        $pathArg    = escapeshellarg($path);
        $bytesArg   = escapeshellarg((string)$maxBytes);

        $cmd = 'sudo ' . escapeshellarg($phpBin) . ' '
             . escapeshellarg($helperPath) . ' '
             . $pathArg . ' ' . $bytesArg;

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['success' => false, 'data' => null,
                    'message' => 'No se pudo ejecutar el helper de file.'];
        }

        fclose($pipes[0]);
        $output   = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr   = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($output === '') {
            return ['success' => false, 'data' => null,
                    'message' => 'El helper de file no devolvió datos (exit=' . $exitCode . ').'
                               . ($stderr !== '' ? ' stderr: ' . trim($stderr) : '')];
        }

        $data = json_decode($output, true);
        if (!is_array($data)) {
            return ['success' => false, 'data' => null,
                    'message' => 'Respuesta JSON inválida del helper de file.'];
        }

        if (isset($data['error'])) {
            return ['success' => false, 'data' => null,
                    'message' => $data['error']];
        }

        return ['success' => true, 'data' => $data, 'message' => ''];
    }

    public static function listDir(PleskClient $client, array $args): array
    {
        $path    = $args['path'] ?? '';
        $pattern = $args['pattern'] ?? '*';

        if ($path === '') {
            return ['success' => false, 'data' => null,
                    'message' => 'El parámetro "path" es requerido.'];
        }

        $phpBin     = '/opt/plesk/php/8.2/bin/php';
        $helperPath = realpath(__DIR__ . '/../../bin/listdir_helper.php');
        if ($helperPath === false) {
            $helperPath = __DIR__ . '/../../bin/listdir_helper.php';
        }
        $pathArg    = escapeshellarg($path);
        $patternArg = escapeshellarg($pattern);

        $cmd = 'sudo ' . escapeshellarg($phpBin) . ' '
             . escapeshellarg($helperPath) . ' '
             . $pathArg . ' ' . $patternArg;

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['success' => false, 'data' => null,
                    'message' => 'No se pudo ejecutar el helper de listdir.'];
        }

        fclose($pipes[0]);
        $output   = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr   = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($output === '') {
            return ['success' => false, 'data' => null,
                    'message' => 'El helper de listdir no devolvió datos (exit=' . $exitCode . ').'
                               . ($stderr !== '' ? ' stderr: ' . trim($stderr) : '')];
        }

        $data = json_decode($output, true);
        if (!is_array($data)) {
            return ['success' => false, 'data' => null,
                    'message' => 'Respuesta JSON inválida del helper de listdir.'];
        }

        if (isset($data['error'])) {
            return ['success' => false, 'data' => null,
                    'message' => $data['error']];
        }

        return ['success' => true, 'data' => $data, 'message' => ''];
    }
}
