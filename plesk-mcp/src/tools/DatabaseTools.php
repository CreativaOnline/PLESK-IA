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
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        $filter = '';
        if ($domain !== '') {
            $filter = '<domain-name>' . htmlspecialchars($domain, ENT_XML1, 'UTF-8') . '</domain-name>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<packet>'
            . '<db>'
            . '<get-db-list>'
            . '<filter>' . $filter . '</filter>'
            . '</get-db-list>'
            . '</db>'
            . '</packet>';

        $xmlResult = $client->postXml('/enterprise/control/agent.php', $xml);
        if ($xmlResult['ok'] && !empty($xmlResult['data'])) {
            $databases = self::parseDbListXml($xmlResult['data']);
            if ($databases !== null) {
                return ['success' => true, 'data' => $databases, 'message' => ''];
            }
        }

        return [
            'success' => false,
            'data'    => null,
            'message' => 'Databases no disponible via API REST ni XML-RPC. '
                . 'Verifica permisos en Plesk > Tools & Settings > Database Servers. '
                . 'Error REST: ' . $result['error'],
        ];
    }

    public static function listDbServers(PleskClient $client, array $args = []): array
    {
        $result = $client->get('/api/v2/db-servers');
        if ($result['ok']) {
            return ['success' => true, 'data' => $result['data'], 'message' => ''];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<packet>'
            . '<db-server>'
            . '<get-list/>'
            . '</db-server>'
            . '</packet>';

        $xmlResult = $client->postXml('/enterprise/control/agent.php', $xml);
        if ($xmlResult['ok'] && !empty($xmlResult['data'])) {
            $servers = self::parseDbServersXml($xmlResult['data']);
            if ($servers !== null) {
                return ['success' => true, 'data' => $servers, 'message' => ''];
            }
        }

        $phpBin = '/opt/plesk/php/8.2/bin/php';
        $script = '$o=[]; exec("plesk bin db --list-servers 2>/dev/null", $o, $c);'
                . '$s=[]; foreach($o as $l){$l=trim($l); if($l!=="") $s[]=["server"=>$l];}'
                . 'echo json_encode(["exit"=>$c,"servers"=>$s]);';
        $cliCmd = 'sudo ' . escapeshellarg($phpBin) . ' -r ' . escapeshellarg($script);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($cliCmd, $descriptors, $pipes);
        if (is_resource($process)) {
            fclose($pipes[0]);
            $cliOutput  = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $cliExit = proc_close($process);

            if ($cliExit === 0 && $cliOutput !== '') {
                $parsed = json_decode($cliOutput, true);
                if (is_array($parsed) && !empty($parsed['servers'])) {
                    return ['success' => true, 'data' => $parsed['servers'], 'message' => ''];
                }
            }
        }

        return [
            'success' => false,
            'data'    => null,
            'message' => 'El endpoint db-servers no está disponible via API REST, XML-RPC ni CLI. '
                . 'Error REST: ' . $result['error'],
        ];
    }

    private static function parseDbListXml(string $rawXml): ?array
    {
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($rawXml);
        libxml_use_internal_errors($prev);

        if ($doc === false) {
            return null;
        }

        $results = $doc->db->{'get-db-list'}->result ?? null;
        if ($results === null) {
            return null;
        }

        $databases = [];

        if (!is_iterable($results)) {
            $results = [$results];
        }

        foreach ($results as $resultNode) {
            $status = (string) ($resultNode->status ?? '');
            if ($status !== 'ok') {
                continue;
            }

            $dbNodes = $resultNode->db ?? $resultNode->children();
            foreach ($dbNodes as $node) {
                if ($node->getName() === 'status' || $node->getName() === 'filter-id') {
                    continue;
                }
                $db = [
                    'id'           => (string) ($node->id ?? ''),
                    'name'         => (string) ($node->name ?? ''),
                    'type'         => (string) ($node->type ?? ''),
                    'server_id'    => (string) ($node->{'server-id'} ?? ''),
                    'default_user' => (string) ($node->{'default-user'} ?? ''),
                    'domain_name'  => (string) ($node->{'domain-name'} ?? ''),
                ];
                if ($db['name'] !== '' || $db['id'] !== '') {
                    $databases[] = $db;
                }
            }
        }

        return $databases;
    }

    private static function parseDbServersXml(string $rawXml): ?array
    {
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($rawXml);
        libxml_use_internal_errors($prev);

        if ($doc === false) {
            return null;
        }

        $results = $doc->{'db-server'}->{'get-list'}->result ?? null;
        if ($results === null) {
            return null;
        }

        $servers = [];

        if (!is_iterable($results)) {
            $results = [$results];
        }

        foreach ($results as $resultNode) {
            $status = (string) ($resultNode->status ?? '');
            if ($status !== 'ok') {
                continue;
            }

            $server = [
                'id'         => (string) ($resultNode->id ?? ''),
                'host'       => (string) ($resultNode->host ?? ''),
                'port'       => (string) ($resultNode->port ?? ''),
                'type'       => (string) ($resultNode->type ?? ''),
                'login'      => (string) ($resultNode->login ?? ''),
                'default_db' => (string) ($resultNode->{'default-db'} ?? ''),
            ];
            if ($server['host'] !== '' || $server['id'] !== '') {
                $servers[] = $server;
            }
        }

        return $servers;
    }
}
