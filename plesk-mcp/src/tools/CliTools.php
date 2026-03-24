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

        // Try Plesk CLI first
        $params = explode(' ', $command);
        $result = $client->cli($params);
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        // CLI failed (likely 404), use fallback per command
        return self::fallback($client, $command, $args);
    }

    private static function fallback(PleskClient $client, string $command, array $args): array
    {
        switch ($command) {
            case 'mail --get-queue':
                return MailTools::mailQueue($client);

            case 'subscription --list':
                return self::fallbackSubscriptionList($client);

            case 'subscription --info':
                return self::fallbackSubscriptionInfo($client, $args);

            case 'db --list':
                return self::fallbackDbList($client);

            case 'statistics --list-domains':
                return self::fallbackStatsDomains($client);

            case 'ext --list':
                return self::fallbackExtList($client);

            case 'repair --standalone --dry-run':
                return self::fallbackRepairDryRun();

            case 'ip --list':
                $r = $client->get('/api/v2/server/ips');
                if ($r['ok']) {
                    return ['success' => true, 'data' => $r['data'], 'message' => ''];
                }
                return ['success' => false, 'data' => null, 'message' => 'ip --list falló: ' . $r['error']];

            default:
                return ['success' => false, 'data' => null, 'message' => 'Sin fallback disponible para: ' . $command];
        }
    }

    private static function fallbackSubscriptionList(PleskClient $client): array
    {
        $result = $client->get('/api/v2/domains');
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        $result2 = $client->get('/api/v2/webspaces');
        if ($result2['ok']) {
            return ['success' => true, 'data' => $result2['data'], 'message' => ''];
        }

        return [
            'success' => false,
            'data'    => null,
            'message' => 'subscription --list: ni CLI, ni /api/v2/domains, ni /api/v2/webspaces disponibles. Último error: ' . $result2['error'],
        ];
    }

    private static function fallbackSubscriptionInfo(PleskClient $client, array $args): array
    {
        // Extract domain name from additional args if provided (e.g., "subscription --info domain.com")
        $parts = explode(' ', trim($args['command'] ?? ''));
        $domainName = $parts[2] ?? '';

        if ($domainName !== '') {
            $result = $client->get('/api/v2/domains?name=' . urlencode($domainName));
            if ($result['ok']) {
                return ['success' => true, 'data' => $result['data'], 'message' => ''];
            }
        }

        // Without a specific domain, list all
        $result2 = $client->get('/api/v2/domains');
        if ($result2['ok']) {
            return ['success' => true, 'data' => $result2['data'], 'message' => ''];
        }

        return [
            'success' => false,
            'data'    => null,
            'message' => 'subscription --info: no se pudo obtener información. Error: ' . $result2['error'],
        ];
    }

    private static function fallbackDbList(PleskClient $client): array
    {
        $result = $client->get('/api/v2/databases');
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        return [
            'success' => false,
            'data'    => null,
            'message' => 'db --list: el endpoint /api/v2/databases no está disponible. Error: ' . $result['error'],
        ];
    }

    private static function fallbackStatsDomains(PleskClient $client): array
    {
        $result = $client->get('/api/v2/server/statistics');
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        // Fallback to system stats
        return ServerTools::serverStats($client);
    }

    private static function fallbackExtList(PleskClient $client): array
    {
        $result = $client->get('/api/v2/modules');
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        return [
            'success' => false,
            'data'    => null,
            'message' => 'ext --list: ni CLI ni /api/v2/modules disponibles. '
                . 'Consulta las extensiones directamente desde el panel de Plesk. Error: ' . $result['error'],
        ];
    }

    private static function fallbackRepairDryRun(): array
    {
        $repairPath = '/usr/local/psa/bin/repair';
        if (is_executable($repairPath)) {
            $output   = [];
            $exitCode = 0;
            exec($repairPath . ' --standalone --dry-run 2>&1', $output, $exitCode);
            $outputStr = implode("\n", $output);

            return [
                'success' => $exitCode === 0,
                'data'    => ['source' => 'exec', 'output' => $outputStr, 'exit_code' => $exitCode],
                'message' => $exitCode === 0 ? '' : 'repair terminó con código ' . $exitCode,
            ];
        }

        return [
            'success' => false,
            'data'    => null,
            'message' => 'repair --standalone --dry-run: ni la API CLI ni el binario /usr/local/psa/bin/repair están disponibles.',
        ];
    }
}
