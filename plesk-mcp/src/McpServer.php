<?php

class McpServer
{
    private PleskClient $client;
    private array $config;

    private const VERSION = '1.0.0';
    private const NAME    = 'Plesk MCP Connector';

    private array $toolRegistry;

    public function __construct(array $config, PleskClient $client)
    {
        $this->config = $config;
        $this->client = $client;

        $this->toolRegistry = [
            'plesk_list_domains'   => ['class' => 'DomainsTools',  'method' => 'listDomains'],
            'plesk_get_domain'     => ['class' => 'DomainsTools',  'method' => 'getDomain'],
            'plesk_list_sites'     => ['class' => 'DomainsTools',  'method' => 'listSites'],
            'plesk_mail_queue'     => ['class' => 'MailTools',     'method' => 'mailQueue'],
            'plesk_list_mailboxes' => ['class' => 'MailTools',     'method' => 'listMailboxes'],
            'plesk_mail_domain_info' => ['class' => 'MailTools',   'method' => 'mailDomainInfo'],
            'plesk_clear_mail_queue' => ['class' => 'MailTools',   'method' => 'clearMailQueue'],
            'plesk_list_databases' => ['class' => 'DatabaseTools', 'method' => 'listDatabases'],
            'plesk_list_db_servers'=> ['class' => 'DatabaseTools', 'method' => 'listDbServers'],
            'plesk_server_info'    => ['class' => 'ServerTools',   'method' => 'serverInfo'],
            'plesk_server_stats'   => ['class' => 'ServerTools',   'method' => 'serverStats'],
            'plesk_list_ip_addresses' => ['class' => 'ServerTools','method' => 'listIpAddresses'],
            'plesk_execute_cli'    => ['class' => 'CliTools',      'method' => 'executeCli'],
            'plesk_scan_maillog'   => ['class' => 'LogTools',      'method' => 'scanMaillog'],
            'plesk_scan_malware'   => ['class' => 'LogTools',      'method' => 'scanMalware'],
            'plesk_read_file'      => ['class' => 'FileTools',     'method' => 'readFile'],
            'plesk_list_dir'       => ['class' => 'FileTools',     'method' => 'listDir'],
        ];
    }

    public function handle(array $request): array
    {
        $method = $request['method'] ?? '';
        $id     = $request['id'] ?? null;
        $params = $request['params'] ?? [];

        switch ($method) {
            case 'initialize':
                return $this->jsonRpcResponse($id, $this->initialize());

            case 'tools/list':
                return $this->jsonRpcResponse($id, ['tools' => $this->getToolDefinitions()]);

            case 'tools/call':
                $toolName = $params['name'] ?? '';
                $toolArgs = $params['arguments'] ?? [];
                return $this->jsonRpcResponse($id, $this->toolsCall($toolName, $toolArgs));

            case 'ping':
                return $this->jsonRpcResponse($id, []);

            default:
                return $this->jsonRpcError($id, -32601, 'Method not found: ' . $method);
        }
    }

    public function getInfo(): array
    {
        return [
            'name'    => self::NAME,
            'version' => self::VERSION,
            'tools'   => count($this->toolRegistry),
            'status'  => 'ok',
        ];
    }

    private function initialize(): array
    {
        return [
            'protocolVersion' => '2024-11-05',
            'serverInfo' => [
                'name'    => self::NAME,
                'version' => self::VERSION,
            ],
            'capabilities' => [
                'tools' => [
                    'listChanged' => false,
                ],
            ],
        ];
    }

    public function getToolDefinitions(): array
    {
        return [
            [
                'name'        => 'plesk_list_domains',
                'description' => 'Lista todos los dominios y suscripciones del servidor',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'plesk_get_domain',
                'description' => 'Obtiene detalles de un dominio específico',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'domain' => ['type' => 'string', 'description' => 'Nombre del dominio'],
                    ],
                    'required' => ['domain'],
                ],
            ],
            [
                'name'        => 'plesk_list_sites',
                'description' => 'Lista todos los sitios web con estado, PHP version y disco usado',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'plesk_mail_queue',
                'description' => 'Estado de la cola de correo del servidor (mensajes encolados, tamaño)',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'plesk_list_mailboxes',
                'description' => 'Lista los buzones de correo de un dominio',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'domain' => ['type' => 'string', 'description' => 'Nombre del dominio'],
                    ],
                    'required' => ['domain'],
                ],
            ],
            [
                'name'        => 'plesk_mail_domain_info',
                'description' => 'Configuración de correo de un dominio (MX, DKIM, SPF)',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'domain' => ['type' => 'string', 'description' => 'Nombre del dominio'],
                    ],
                    'required' => ['domain'],
                ],
            ],
            [
                'name'        => 'plesk_clear_mail_queue',
                'description' => 'ACCIÓN DESTRUCTIVA — Limpia la cola de correo. Requiere confirm:true.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'confirm' => ['type' => 'boolean', 'description' => 'Debe ser true para ejecutar'],
                    ],
                    'required' => ['confirm'],
                ],
            ],
            [
                'name'        => 'plesk_list_databases',
                'description' => 'Lista todas las bases de datos, opcionalmente filtradas por dominio',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'domain' => ['type' => 'string', 'description' => 'Filtrar por dominio (opcional)'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name'        => 'plesk_list_db_servers',
                'description' => 'Lista los servidores de base de datos configurados en Plesk',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'plesk_server_info',
                'description' => 'Información del servidor (versión Plesk, OS, hostname, IPs, licencia)',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'plesk_server_stats',
                'description' => 'Estadísticas del servidor (CPU, RAM, disco)',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'plesk_list_ip_addresses',
                'description' => 'Lista las IPs configuradas en el servidor con su tipo (shared/dedicated)',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'plesk_execute_cli',
                'description' => 'Ejecuta un comando Plesk CLI de la lista blanca. Comandos: mail --get-queue, subscription --list, subscription --info, db --list, statistics --list-domains, ext --list, repair --standalone --dry-run, ip --list',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'command' => ['type' => 'string', 'description' => 'Comando Plesk CLI a ejecutar'],
                    ],
                    'required' => ['command'],
                ],
            ],
            [
                'name'        => 'plesk_scan_maillog',
                'description' => 'Analiza el maillog buscando spam, rebotes y patrones sospechosos',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'lines'  => ['type' => 'integer', 'description' => 'Líneas a analizar (default 500)'],
                        'filter' => ['type' => 'string',  'description' => 'Filtrar por dominio o texto (opcional)'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name'        => 'plesk_scan_malware',
                'description' => 'Escanea archivos PHP de un dominio buscando malware, webshells y código ofuscado',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'domain'    => ['type' => 'string',  'description' => 'Dominio a escanear (vacío = todo el servidor)'],
                        'max_files' => ['type' => 'integer', 'description' => 'Máximo de archivos a escanear (default 5000)'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name'        => 'plesk_read_file',
                'description' => 'Lee el contenido de un archivo del servidor (rutas permitidas: /var/www/vhosts/, /var/log/, /usr/local/psa/var/log/, /etc/postfix/)',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'path'      => ['type' => 'string',  'description' => 'Ruta absoluta del archivo a leer'],
                        'max_bytes' => ['type' => 'integer', 'description' => 'Máximo de bytes a leer (default 100000)'],
                    ],
                    'required' => ['path'],
                ],
            ],
            [
                'name'        => 'plesk_list_dir',
                'description' => 'Lista archivos y subdirectorios de una ruta del servidor (rutas permitidas: /var/www/vhosts/, /var/log/, /usr/local/psa/var/log/, /etc/postfix/)',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'path'    => ['type' => 'string', 'description' => 'Ruta absoluta del directorio a listar'],
                        'pattern' => ['type' => 'string', 'description' => 'Filtro glob, ej. *.log (default: *)'],
                    ],
                    'required' => ['path'],
                ],
            ],
        ];
    }

    private function toolsCall(string $name, array $args): array
    {
        if (!isset($this->toolRegistry[$name])) {
            return [
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'success' => false,
                        'data'    => null,
                        'message' => 'Herramienta no encontrada: ' . $name,
                    ]),
                ]],
                'isError' => true,
            ];
        }

        $entry  = $this->toolRegistry[$name];
        $class  = $entry['class'];
        $method = $entry['method'];

        $result = $class::$method($this->client, $args);

        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($result, JSON_UNESCAPED_UNICODE),
            ]],
            'isError' => !($result['success'] ?? false),
        ];
    }

    private function jsonRpcResponse($id, array $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ];
    }

    private function jsonRpcError($id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
        ];
    }
}
