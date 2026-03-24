<?php

class DomainsTools
{
    public static function listDomains(PleskClient $client, array $args = []): array
    {
        // Strategy 1: /api/v2/subscriptions
        $result = $client->get('/api/v2/subscriptions');
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        // Strategy 2: /api/v2/domains
        $result2 = $client->get('/api/v2/domains');
        if ($result2['ok']) {
            return ['success' => true, 'data' => $result2['data'], 'message' => ''];
        }

        // Strategy 3: /api/v2/webspaces
        $result3 = $client->get('/api/v2/webspaces');
        if ($result3['ok']) {
            return ['success' => true, 'data' => $result3['data'], 'message' => ''];
        }

        return [
            'success' => false,
            'data'    => null,
            'message' => 'No se pudieron listar los dominios. Endpoints intentados: /api/v2/subscriptions, /api/v2/domains, /api/v2/webspaces. Último error: ' . $result3['error'],
        ];
    }

    public static function getDomain(PleskClient $client, array $args): array
    {
        $domain = $args['domain'] ?? '';
        if ($domain === '') {
            return ['success' => false, 'data' => null, 'message' => 'El parámetro "domain" es requerido.'];
        }

        $encoded = urlencode($domain);

        // Strategy 1: /api/v2/subscriptions?domain=X
        $result = $client->get('/api/v2/subscriptions?domain=' . $encoded);
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        // Strategy 2: /api/v2/domains?name=X
        $result2 = $client->get('/api/v2/domains?name=' . $encoded);
        if ($result2['ok']) {
            return ['success' => true, 'data' => $result2['data'], 'message' => ''];
        }

        // Strategy 3: /api/v2/webspaces?name=X
        $result3 = $client->get('/api/v2/webspaces?name=' . $encoded);
        if ($result3['ok']) {
            return ['success' => true, 'data' => $result3['data'], 'message' => ''];
        }

        return [
            'success' => false,
            'data'    => null,
            'message' => 'No se encontró el dominio "' . $domain . '". Último error: ' . $result3['error'],
        ];
    }

    public static function listSites(PleskClient $client, array $args = []): array
    {
        // Strategy 1: /api/v2/webspaces
        $result = $client->get('/api/v2/webspaces');
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        // Strategy 2: /api/v2/domains
        $result2 = $client->get('/api/v2/domains');
        if ($result2['ok']) {
            return ['success' => true, 'data' => $result2['data'], 'message' => ''];
        }

        return [
            'success' => false,
            'data'    => null,
            'message' => 'No se pudieron listar los sitios. Endpoints intentados: /api/v2/webspaces, /api/v2/domains. Último error: ' . $result2['error'],
        ];
    }
}
