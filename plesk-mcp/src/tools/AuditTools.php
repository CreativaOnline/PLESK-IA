<?php

class AuditTools
{
    public static function auditScan(PleskClient $client, array $args): array
    {
        $mode = trim($args['mode'] ?? '');

        if (!in_array($mode, ['grep_file', 'scan_sql', 'file_stats'], true)) {
            return ['success' => false, 'data' => null,
                    'message' => 'Modo no válido. Usa: grep_file, scan_sql o file_stats.'];
        }

        $phpBin     = '/opt/plesk/php/8.2/bin/php';
        $helperPath = realpath(__DIR__ . '/../../bin/audit_helper.php');

        switch ($mode) {
            case 'grep_file':
                $path    = trim($args['path'] ?? '');
                $pattern = trim($args['pattern'] ?? '');
                $maxLines = (int)($args['max_lines'] ?? 200);
                if ($path === '' || $pattern === '') {
                    return ['success' => false, 'data' => null,
                            'message' => 'grep_file requiere path y pattern.'];
                }
                $cmdArgs = escapeshellarg($mode) . ' '
                         . escapeshellarg($path) . ' '
                         . escapeshellarg($pattern) . ' '
                         . escapeshellarg((string)$maxLines);
                break;

            case 'scan_sql':
                $path     = trim($args['path'] ?? '');
                $maxLines = (int)($args['max_lines'] ?? 500);
                $cmdArgs  = escapeshellarg($mode) . ' '
                          . escapeshellarg($path) . ' '
                          . escapeshellarg((string)$maxLines);
                break;

            case 'file_stats':
                $path = trim($args['path'] ?? '');
                if ($path === '') {
                    return ['success' => false, 'data' => null,
                            'message' => 'file_stats requiere path.'];
                }
                $cmdArgs = escapeshellarg($mode) . ' '
                         . escapeshellarg($path);
                break;
        }

        $cmd = 'sudo ' . escapeshellarg($phpBin) . ' '
             . escapeshellarg($helperPath) . ' ' . $cmdArgs;

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['success' => false, 'data' => null,
                    'message' => 'No se pudo ejecutar el helper de audit.'];
        }

        fclose($pipes[0]);
        $output   = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr   = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($output === '') {
            return ['success' => false, 'data' => null,
                    'message' => 'El helper de audit no devolvió datos (exit=' . $exitCode . ').'
                               . ($stderr !== '' ? ' stderr: ' . trim($stderr) : '')];
        }

        $data = json_decode($output, true);
        if (!is_array($data)) {
            return ['success' => false, 'data' => null,
                    'message' => 'Respuesta JSON inválida del helper de audit.'];
        }

        if (isset($data['error'])) {
            return ['success' => false, 'data' => $data,
                    'message' => $data['error']];
        }

        return ['success' => true, 'data' => $data, 'message' => ''];
    }
}
