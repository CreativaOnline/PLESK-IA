<?php

class DomainsTools
{
    public static function listDomains(PleskClient $client, array $args = []): array
    {
        $result = $client->get('/api/v2/subscriptions');
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        $result2 = $client->get('/api/v2/domains');
        if ($result2['ok']) {
            return ['success' => true, 'data' => $result2['data'], 'message' => ''];
        }

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

        $result = $client->get('/api/v2/subscriptions?domain=' . $encoded);
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        $result2 = $client->get('/api/v2/domains?name=' . $encoded);
        if ($result2['ok']) {
            return ['success' => true, 'data' => $result2['data'], 'message' => ''];
        }

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
        $result = $client->get('/api/v2/webspaces');
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

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
