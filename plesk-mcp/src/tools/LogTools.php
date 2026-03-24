<?php

class LogTools
{
    public static function scanMaillog(PleskClient $client, array $args): array
    {
        $phpBin     = '/opt/plesk/php/8.2/bin/php';
        $helperPath = realpath(__DIR__ . '/../../bin/maillog_helper.php');
        $linesArg   = escapeshellarg((string)($args['lines'] ?? 500));
        $filterArg  = escapeshellarg($args['filter'] ?? '');

        $cmd = 'sudo ' . escapeshellarg($phpBin) . ' '
             . escapeshellarg($helperPath) . ' '
             . $linesArg . ' ' . $filterArg;

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['success' => false, 'data' => null,
                    'message' => 'No se pudo ejecutar el helper de maillog.'];
        }

        fclose($pipes[0]);
        $output   = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || $output === '') {
            return ['success' => false, 'data' => null,
                    'message' => 'El helper de maillog falló (exit=' . $exitCode . ').'];
        }

        $data = json_decode($output, true);
        if (!is_array($data)) {
            return ['success' => false, 'data' => null,
                    'message' => 'Respuesta JSON inválida del helper de maillog.'];
        }

        return ['success' => true, 'data' => $data, 'message' => ''];
    }

    public static function scanMalware(PleskClient $client, array $args): array
    {
        $phpBin     = '/opt/plesk/php/8.2/bin/php';
        $helperPath = realpath(__DIR__ . '/../../bin/malware_helper.php');
        $domainArg  = escapeshellarg($args['domain'] ?? '');
        $maxArg     = escapeshellarg((string)($args['max_files'] ?? 5000));

        $cmd = 'sudo ' . escapeshellarg($phpBin) . ' '
             . escapeshellarg($helperPath) . ' '
             . $domainArg . ' ' . $maxArg;

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['success' => false, 'data' => null,
                    'message' => 'No se pudo ejecutar el helper de malware.'];
        }

        fclose($pipes[0]);
        $output   = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || $output === '') {
            return ['success' => false, 'data' => null,
                    'message' => 'El helper de malware falló (exit=' . $exitCode . ').'];
        }

        $data = json_decode($output, true);
        if (!is_array($data)) {
            return ['success' => false, 'data' => null,
                    'message' => 'Respuesta JSON inválida del helper de malware.'];
        }

        return ['success' => true, 'data' => $data, 'message' => ''];
    }
}
