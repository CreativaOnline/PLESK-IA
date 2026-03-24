<?php

class DatabaseTools
{
    public static function listDatabases(PleskClient $client, array $args = []): array
    {
        $domain = $args['domain'] ?? '';

        // Strategy 1: /api/v2/databases
        $path = '/api/v2/databases';
        if ($domain !== '') {
            $path .= '?domain=' . urlencode($domain);
        }
        $result = $client->get($path);
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        // Strategy 2: descriptive error
        return [
            'success' => false,
            'data'    => null,
            'message' => 'El endpoint /api/v2/databases no está disponible en esta versión/configuración de Plesk. '
                . 'Verifica que la extensión "REST API" esté habilitada en Plesk > Tools & Settings > API REST '
                . 'y que el usuario tenga permisos suficientes. Error: ' . $result['error'],
        ];
    }

    public static function listDbServers(PleskClient $client, array $args = []): array
    {
        $result = $client->get('/api/v2/db-servers');
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        return [
            'success' => false,
            'data'    => null,
            'message' => 'El endpoint db-servers no está disponible en esta versión/configuración de Plesk. '
                . 'Verifica que la API REST esté habilitada en Plesk > Tools & Settings > API REST. '
                . 'Error: ' . $result['error'],
        ];
    }
}
