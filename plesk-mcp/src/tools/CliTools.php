<?php

class CliTools
{
    private const WHITELIST = [
        'mail --get-queue',
        'subscription --list',
        'subscription --info',
        'db --list',
        'statistics --list-domains',
        'ext --list',
        'repair --standalone --dry-run',
        'ip --list',
    ];

    public static function executeCli(PleskClient $client, array $args): array
    {
        $command = trim($args['command'] ?? '');
        if ($command === '') {
            return ['success' => false, 'data' => null, 'message' => 'El parámetro "command" es requerido.'];
        }

        if (!in_array($command, self::WHITELIST, true)) {
            return [
                'success' => false,
                'data'    => null,
                'message' => 'Comando no permitido. Comandos disponibles: ' . implode(', ', self::WHITELIST),
            ];
        }

        $params = explode(' ', $command);
        $result = $client->cli($params);
        if (!$result['ok']) {
            return ['success' => false, 'data' => null, 'message' => $result['error']];
        }
        return ['success' => true, 'data' => $result['data'], 'message' => ''];
    }
}
