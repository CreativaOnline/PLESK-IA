<?php

class WriteTools
{
    public static function writeFile(PleskClient $client, array $args): array
    {
        $path    = trim($args['path'] ?? '');
        $content = $args['content'] ?? '';
        $confirm = (bool)($args['confirm'] ?? false);

        if ($path === '') {
            return ['success' => false, 'data' => null,
                    'message' => 'El parámetro "path" es requerido.'];
        }

        if (!$confirm) {
            return ['success' => false, 'data' => null,
                    'message' => 'ACCIÓN DE ESCRITURA: Debes enviar confirm:true para escribir. Se creará un backup automático del archivo original.'];
        }

        $phpBin     = '/opt/plesk/php/8.2/bin/php';
        $helperPath = realpath(__DIR__ . '/../../bin/writefile_helper.php');
        $pathArg    = escapeshellarg($path);

        $cmd = 'sudo ' . escapeshellarg($phpBin) . ' '
             . escapeshellarg($helperPath) . ' ' . $pathArg;

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['success' => false, 'data' => null,
                    'message' => 'No se pudo ejecutar el helper de escritura.'];
        }

        fwrite($pipes[0], $content);
        fclose($pipes[0]);
        $output   = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr   = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($output === '') {
            return ['success' => false, 'data' => null,
                    'message' => 'El helper de escritura no devolvió datos (exit=' . $exitCode . ').'
                               . ($stderr !== '' ? ' stderr: ' . trim($stderr) : '')];
        }

        $data = json_decode($output, true);
        if (!is_array($data)) {
            return ['success' => false, 'data' => null,
                    'message' => 'Respuesta JSON inválida del helper de escritura.'];
        }

        if (isset($data['error'])) {
            return ['success' => false, 'data' => $data,
                    'message' => $data['error']];
        }

        return ['success' => true, 'data' => $data, 'message' => ''];
    }
}
