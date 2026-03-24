<?php

class Auth
{
    public static function verify(array $config): bool
    {
        $token = $config['mcp_token'] ?? '';
        if ($token === '') {
            return false;
        }

        // 1. Check Authorization: Bearer TOKEN header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return hash_equals($token, $matches[1]);
        }

        // 2. Fall back to ?token= query param
        $queryToken = $_GET['token'] ?? '';
        if ($queryToken !== '') {
            return hash_equals($token, $queryToken);
        }

        return false;
    }
}
