<?php

class DnsTools
{
    public static function domainDns(PleskClient $client, array $args): array
    {
        $domain = trim($args['domain'] ?? '');

        if ($domain === '') {
            return ['success' => false, 'data' => null,
                    'message' => 'El parámetro "domain" es requerido.'];
        }

        $domain = basename($domain);

        $records = [];

        $types = [
            DNS_A     => 'A',
            DNS_AAAA  => 'AAAA',
            DNS_MX    => 'MX',
            DNS_NS    => 'NS',
            DNS_TXT   => 'TXT',
            DNS_CNAME => 'CNAME',
            DNS_SOA   => 'SOA',
        ];

        foreach ($types as $const => $label) {
            $result = @dns_get_record($domain, $const);
            if (!is_array($result) || empty($result)) {
                continue;
            }

            foreach ($result as $r) {
                $entry = ['type' => $label, 'ttl' => $r['ttl'] ?? null];

                switch ($label) {
                    case 'A':
                        $entry['ip'] = $r['ip'] ?? null;
                        break;
                    case 'AAAA':
                        $entry['ipv6'] = $r['ipv6'] ?? null;
                        break;
                    case 'MX':
                        $entry['priority'] = $r['pri'] ?? null;
                        $entry['target']   = $r['target'] ?? null;
                        break;
                    case 'NS':
                        $entry['target'] = $r['target'] ?? null;
                        break;
                    case 'TXT':
                        $entry['txt'] = $r['txt'] ?? null;
                        break;
                    case 'CNAME':
                        $entry['target'] = $r['target'] ?? null;
                        break;
                    case 'SOA':
                        $entry['mname']   = $r['mname'] ?? null;
                        $entry['rname']   = $r['rname'] ?? null;
                        $entry['serial']  = $r['serial'] ?? null;
                        $entry['refresh'] = $r['refresh'] ?? null;
                        $entry['retry']   = $r['retry'] ?? null;
                        $entry['expire']  = $r['expire'] ?? null;
                        $entry['minimum_ttl'] = $r['minimum-ttl'] ?? null;
                        break;
                }

                $records[] = $entry;
            }
        }

        $hasSPF  = false;
        $hasDKIM = false;
        $hasDMARC = false;
        foreach ($records as $r) {
            if ($r['type'] === 'TXT') {
                $txt = strtolower($r['txt'] ?? '');
                if (strpos($txt, 'v=spf1') !== false) $hasSPF = true;
                if (strpos($txt, 'v=dkim1') !== false) $hasDKIM = true;
                if (strpos($txt, 'v=dmarc1') !== false) $hasDMARC = true;
            }
        }

        if (!$hasDMARC) {
            $dmarc = @dns_get_record('_dmarc.' . $domain, DNS_TXT);
            if (is_array($dmarc)) {
                foreach ($dmarc as $r) {
                    $records[] = ['type' => 'TXT', 'ttl' => $r['ttl'] ?? null, 'txt' => $r['txt'] ?? null, 'host' => '_dmarc.' . $domain];
                    if (stripos($r['txt'] ?? '', 'v=dmarc1') !== false) $hasDMARC = true;
                }
            }
        }

        if (!$hasDKIM) {
            foreach (['default', 'google', 'mail', 'selector1', 'selector2'] as $sel) {
                $dkim = @dns_get_record($sel . '._domainkey.' . $domain, DNS_TXT);
                if (is_array($dkim) && !empty($dkim)) {
                    foreach ($dkim as $r) {
                        $records[] = ['type' => 'TXT', 'ttl' => $r['ttl'] ?? null, 'txt' => $r['txt'] ?? null, 'host' => $sel . '._domainkey.' . $domain];
                        if (stripos($r['txt'] ?? '', 'v=dkim1') !== false) $hasDKIM = true;
                    }
                    break;
                }
            }
        }

        return [
            'success' => true,
            'data'    => [
                'domain'  => $domain,
                'records' => $records,
                'email_security' => [
                    'spf'   => $hasSPF,
                    'dkim'  => $hasDKIM,
                    'dmarc' => $hasDMARC,
                ],
            ],
            'message' => '',
        ];
    }
}
