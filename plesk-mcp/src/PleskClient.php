<?php

class PleskClient
{
    private string $baseUrl;
    private string $user;
    private string $password;
    private bool $sslVerify;

    public function __construct(array $config)
    {
        $this->baseUrl   = rtrim($config['plesk_url'] ?? '', '/');
        $this->user      = $config['plesk_user'] ?? '';
        $this->password  = $config['plesk_password'] ?? '';
        $this->sslVerify = $config['ssl_verify'] ?? false;
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, array $body = []): array
    {
        return $this->request('POST', $path, $body);
    }

    public function put(string $path, array $body = []): array
    {
        return $this->request('PUT', $path, $body);
    }

    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    public function cli(array $params): array
    {
        return $this->post('/api/v2/cli/plesk/call', ['params' => $params]);
    }

    public function postXml(string $path, string $xmlBody): array
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xmlBody,
            CURLOPT_USERPWD        => $this->user . ':' . $this->password,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: text/xml',
                'Accept: text/xml',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            return ['ok' => false, 'data' => null, 'error' => 'cURL error: ' . $error];
        }

        if ($httpCode >= 400) {
            return ['ok' => false, 'data' => (string) $response, 'error' => 'HTTP ' . $httpCode . ': ' . substr((string) $response, 0, 500)];
        }

        return ['ok' => true, 'data' => (string) $response, 'error' => ''];
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_USERPWD        => $this->user . ':' . $this->password,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            return ['ok' => false, 'data' => null, 'error' => 'cURL error: ' . $error];
        }

        $data = json_decode($response, true);
        if ($httpCode >= 400) {
            $msg = 'HTTP ' . $httpCode;
            if (is_array($data) && isset($data['message'])) {
                $msg .= ': ' . $data['message'];
            } elseif (is_array($data) && isset($data['error'])) {
                $msg .= ': ' . $data['error'];
            } elseif (!is_array($data)) {
                $msg .= ': ' . substr((string) $response, 0, 500);
            }
            return ['ok' => false, 'data' => $data, 'error' => $msg];
        }

        return ['ok' => true, 'data' => $data, 'error' => ''];
    }
}
