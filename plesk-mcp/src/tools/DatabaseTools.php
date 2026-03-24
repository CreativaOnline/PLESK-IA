<?php

class DatabaseTools
{
    public static function listDatabases(PleskClient $client, array $args = []): array
    {
        $domain = $args['domain'] ?? '';
        $path = '/api/v2/databases';
        if ($domain !== '') {
            $path .= '?domain=' . urlencode($domain);
        }
        $result = $client->get($path);
        if (!$result['ok']) {
            return ['success' => false, 'data' => null, 'message' => $result['error']];
        }
        return ['success' => true, 'data' => $result['data'], 'message' => ''];
    }

    public static function listDbServers(PleskClient $client, array $args = []): array
    {
        $result = $client->get('/api/v2/db-servers');
        if (!$result['ok']) {
            return ['success' => false, 'data' => null, 'message' => $result['error']];
        }
        return ['success' => true, 'data' => $result['data'], 'message' => ''];
    }
}
