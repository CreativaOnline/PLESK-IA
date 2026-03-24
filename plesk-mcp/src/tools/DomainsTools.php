<?php

class DomainsTools
{
    public static function listDomains(PleskClient $client, array $args = []): array
    {
        $result = $client->get('/api/v2/subscriptions');
        if (!$result['ok']) {
            return ['success' => false, 'data' => null, 'message' => $result['error']];
        }
        return ['success' => true, 'data' => $result['data'], 'message' => ''];
    }

    public static function getDomain(PleskClient $client, array $args): array
    {
        $domain = $args['domain'] ?? '';
        if ($domain === '') {
            return ['success' => false, 'data' => null, 'message' => 'El parámetro "domain" es requerido.'];
        }
        $result = $client->get('/api/v2/subscriptions?domain=' . urlencode($domain));
        if (!$result['ok']) {
            return ['success' => false, 'data' => null, 'message' => $result['error']];
        }
        return ['success' => true, 'data' => $result['data'], 'message' => ''];
    }

    public static function listSites(PleskClient $client, array $args = []): array
    {
        $result = $client->get('/api/v2/webspaces');
        if (!$result['ok']) {
            return ['success' => false, 'data' => null, 'message' => $result['error']];
        }
        return ['success' => true, 'data' => $result['data'], 'message' => ''];
    }
}
