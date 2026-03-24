<?php

class MailTools
{
    public static function mailQueue(PleskClient $client, array $args = []): array
    {
        $result = $client->cli(['mail', '--get-queue']);
        if (!$result['ok']) {
            return ['success' => false, 'data' => null, 'message' => $result['error']];
        }
        return ['success' => true, 'data' => $result['data'], 'message' => ''];
    }

    public static function listMailboxes(PleskClient $client, array $args): array
    {
        $domain = $args['domain'] ?? '';
        if ($domain === '') {
            return ['success' => false, 'data' => null, 'message' => 'El parámetro "domain" es requerido.'];
        }
        $result = $client->get('/api/v2/mail-domains/' . urlencode($domain) . '/mail-users');
        if (!$result['ok']) {
            return ['success' => false, 'data' => null, 'message' => $result['error']];
        }
        return ['success' => true, 'data' => $result['data'], 'message' => ''];
    }

    public static function mailDomainInfo(PleskClient $client, array $args): array
    {
        $domain = $args['domain'] ?? '';
        if ($domain === '') {
            return ['success' => false, 'data' => null, 'message' => 'El parámetro "domain" es requerido.'];
        }
        $result = $client->get('/api/v2/mail-domains/' . urlencode($domain));
        if (!$result['ok']) {
            return ['success' => false, 'data' => null, 'message' => $result['error']];
        }
        return ['success' => true, 'data' => $result['data'], 'message' => ''];
    }

    public static function clearMailQueue(PleskClient $client, array $args): array
    {
        $confirm = $args['confirm'] ?? false;
        if ($confirm !== true) {
            return [
                'success' => false,
                'data'    => null,
                'message' => 'Debes confirmar con confirm:true para ejecutar esta acción.',
            ];
        }
        $result = $client->cli(['repair', '--mail']);
        if (!$result['ok']) {
            return ['success' => false, 'data' => null, 'message' => $result['error']];
        }
        return ['success' => true, 'data' => $result['data'], 'message' => ''];
    }
}
