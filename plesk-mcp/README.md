# Plesk MCP Connector

Conector MCP (Model Context Protocol) para servidores Plesk. Expone las funcionalidades de administración de Plesk como herramientas MCP accesibles via JSON-RPC 2.0, permitiendo que asistentes de IA gestionen servidores Plesk de forma segura.

## Herramientas disponibles

| Tool | Descripcion |
|------|-------------|
| `plesk_list_domains` | Lista todos los dominios y suscripciones del servidor |
| `plesk_get_domain` | Obtiene detalles de un dominio especifico |
| `plesk_list_sites` | Lista sitios web con estado, PHP version y disco |
| `plesk_mail_queue` | Estado de la cola de correo (mensajes encolados, tamano) |
| `plesk_list_mailboxes` | Lista buzones de correo de un dominio |
| `plesk_mail_domain_info` | Configuracion de correo (MX, DKIM, SPF) |
| `plesk_clear_mail_queue` | Limpia la cola de correo (requiere confirmacion) |
| `plesk_list_databases` | Lista bases de datos, filtrable por dominio |
| `plesk_list_db_servers` | Lista servidores de base de datos configurados |
| `plesk_server_info` | Informacion del servidor (version, OS, hostname, IPs) |
| `plesk_server_stats` | Estadisticas del servidor (CPU, RAM, disco) |
| `plesk_list_ip_addresses` | IPs configuradas con tipo (shared/dedicated) |
| `plesk_execute_cli` | Ejecuta comandos Plesk CLI (whitelist) |
| `plesk_scan_maillog` | Analiza maillog buscando spam y patrones sospechosos |
| `plesk_scan_malware` | Escanea archivos PHP buscando malware y webshells |
| `plesk_read_log` | Lee logs del servidor con filtro grep, devuelve líneas como array |
| `plesk_read_file` | Lee archivos del servidor (rutas permitidas) |
| `plesk_list_dir` | Lista directorios del servidor (rutas permitidas) |
| `plesk_wpcli` | Ejecuta comandos WP-CLI de solo lectura en dominios WordPress |

## Requisitos

- Plesk Obsidian 18.x o superior
- PHP 8.0+ (se usa `/opt/plesk/php/8.2/bin/php` para los helpers)
- cURL extension habilitada en PHP
- Acceso a la API REST de Plesk (puerto 8443)
- WP-CLI instalado en el servidor (para `plesk_wpcli`)

## Estructura del proyecto

```
plesk-mcp/
├── index.php                      Entry point (JSON-RPC 2.0)
├── config.php                     Configuracion (credenciales via env vars)
├── README.md
├── bin/
│   ├── file_helper.php            Helper sudo: lectura de archivos
│   ├── listdir_helper.php         Helper sudo: listado de directorios
│   ├── mail_queue_helper.php      Helper sudo: cola de correo y stats
│   ├── maillog_helper.php         Helper sudo: analisis de maillog
│   ├── malware_helper.php         Helper sudo: escaneo de malware
│   ├── readlog_helper.php         Helper sudo: lectura de logs con grep
│   └── wpcli_helper.php           Helper sudo: ejecucion de WP-CLI
└── src/
    ├── Auth.php                   Autenticacion por Bearer token
    ├── McpServer.php              Registro de tools y router JSON-RPC
    ├── PleskClient.php            Cliente HTTP hacia la API de Plesk
    └── tools/
        ├── CliTools.php           Comandos Plesk CLI (whitelist)
        ├── DatabaseTools.php      Bases de datos y servidores DB
        ├── DomainsTools.php       Dominios y suscripciones
        ├── FileTools.php          Lectura de archivos y listado de directorios
        ├── LogTools.php           Escaneo de maillog y malware
        ├── MailTools.php          Cola de correo y buzones
        ├── ServerTools.php        Info y estadisticas del servidor
        └── WpCliTools.php         Ejecucion de WP-CLI
```

## Instalacion paso a paso en Plesk

### 1. Crear el subdominio

En Plesk, crear un subdominio para el conector (ej: `plesk.tudominio.com`) apuntando al directorio donde se desplegara el proyecto.

### 2. Subir los archivos

Subir el contenido de `plesk-mcp/` al document root del subdominio:

```
/var/www/vhosts/tudominio.com/plesk.tudominio.com/plesk-mcp/
```

### 3. Configurar variables de entorno

El archivo `config.php` lee las credenciales desde variables de entorno. Configurarlas en el entorno del servidor web.

En Plesk, ir a **Dominios > plesk.tudominio.com > PHP Settings > Additional directives** y anadir:

```
env[PLESK_USER]=admin
env[PLESK_PASSWORD]=tu_password_de_plesk
env[MCP_TOKEN]=un_token_secreto_largo_y_aleatorio
```

Alternativamente, crear un archivo `.env` y cargarlo desde la configuracion de PHP-FPM, o definir las variables en el pool de PHP-FPM directamente.

**Importante:** El `MCP_TOKEN` es el token que usaran los clientes MCP para autenticarse. Generalo con:

```bash
openssl rand -hex 32
```

### 4. Configurar la API REST de Plesk

Asegurarse de que la API REST de Plesk esta habilitada:

1. Entrar al panel de Plesk como administrador
2. Ir a **Tools & Settings > Remote API**
3. Verificar que la API REST esta habilitada
4. El usuario configurado en `PLESK_USER` debe tener permisos de administrador

### 5. Configurar permisos sudo

Los helpers necesitan ejecutarse con privilegios elevados para acceder a logs, archivos de otros usuarios y ejecutar WP-CLI. Crear el archivo `/etc/sudoers.d/plesk-mcp`:

```bash
visudo -f /etc/sudoers.d/plesk-mcp
```

Contenido (reemplazar la ruta al directorio real del proyecto):

```
RUTA=/var/www/vhosts/tudominio.com/plesk.tudominio.com/plesk-mcp

tu_usuario_sistema ALL=(root) NOPASSWD: /opt/plesk/php/8.2/bin/php RUTA/bin/mail_queue_helper.php *
tu_usuario_sistema ALL=(root) NOPASSWD: /opt/plesk/php/8.2/bin/php RUTA/bin/maillog_helper.php *
tu_usuario_sistema ALL=(root) NOPASSWD: /opt/plesk/php/8.2/bin/php RUTA/bin/malware_helper.php *
tu_usuario_sistema ALL=(root) NOPASSWD: /opt/plesk/php/8.2/bin/php RUTA/bin/file_helper.php *
tu_usuario_sistema ALL=(root) NOPASSWD: /opt/plesk/php/8.2/bin/php RUTA/bin/listdir_helper.php *
tu_usuario_sistema ALL=(root) NOPASSWD: /opt/plesk/php/8.2/bin/php RUTA/bin/readlog_helper.php *
tu_usuario_sistema ALL=(root) NOPASSWD: /opt/plesk/php/8.2/bin/php RUTA/bin/wpcli_helper.php *
```

**Notas:**
- `tu_usuario_sistema` es el usuario del sistema bajo el cual corre PHP-FPM para ese subdominio (puedes verlo en Plesk > Dominios > tu subdominio > PHP Settings)
- El `*` al final de cada linea es **imprescindible** para que sudo acepte los argumentos que pasan las tools
- No usar variables en el fichero real de sudoers, poner las rutas completas

Ejemplo real:

```
soportewebandweb ALL=(root) NOPASSWD: /opt/plesk/php/8.2/bin/php /var/www/vhosts/websoluciones.es/plesk.websoluciones.es/plesk-mcp/bin/mail_queue_helper.php *
soportewebandweb ALL=(root) NOPASSWD: /opt/plesk/php/8.2/bin/php /var/www/vhosts/websoluciones.es/plesk.websoluciones.es/plesk-mcp/bin/maillog_helper.php *
soportewebandweb ALL=(root) NOPASSWD: /opt/plesk/php/8.2/bin/php /var/www/vhosts/websoluciones.es/plesk.websoluciones.es/plesk-mcp/bin/malware_helper.php *
soportewebandweb ALL=(root) NOPASSWD: /opt/plesk/php/8.2/bin/php /var/www/vhosts/websoluciones.es/plesk.websoluciones.es/plesk-mcp/bin/file_helper.php *
soportewebandweb ALL=(root) NOPASSWD: /opt/plesk/php/8.2/bin/php /var/www/vhosts/websoluciones.es/plesk.websoluciones.es/plesk-mcp/bin/listdir_helper.php *
soportewebandweb ALL=(root) NOPASSWD: /opt/plesk/php/8.2/bin/php /var/www/vhosts/websoluciones.es/plesk.websoluciones.es/plesk-mcp/bin/readlog_helper.php *
soportewebandweb ALL=(root) NOPASSWD: /opt/plesk/php/8.2/bin/php /var/www/vhosts/websoluciones.es/plesk.websoluciones.es/plesk-mcp/bin/wpcli_helper.php *
```

### 6. Instalar WP-CLI (opcional, para plesk_wpcli)

Si WP-CLI no esta instalado:

```bash
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
mv wp-cli.phar /usr/local/bin/wp
```

Verificar:

```bash
wp --info
```

### 7. Configurar permisos de archivos

```bash
chown -R tu_usuario_sistema:psacln /var/www/vhosts/tudominio.com/plesk.tudominio.com/plesk-mcp/
chmod -R 750 /var/www/vhosts/tudominio.com/plesk.tudominio.com/plesk-mcp/
chmod 640 /var/www/vhosts/tudominio.com/plesk.tudominio.com/plesk-mcp/config.php
```

### 8. Configurar el virtualhost

En Plesk, ir a **Dominios > plesk.tudominio.com > Apache & nginx Settings** y anadir la directiva para que el document root apunte al directorio `plesk-mcp/`:

```apache
DocumentRoot /var/www/vhosts/tudominio.com/plesk.tudominio.com/plesk-mcp
```

O configurar un alias/rewrite segun tu estructura.

### 9. Verificar la instalacion

```bash
curl https://plesk.tudominio.com/
```

Deberia devolver:

```json
{"name":"Plesk MCP Connector","version":"1.0.0","tools":19,"status":"ok"}
```

### 10. Conectar desde un cliente MCP

Configurar el cliente MCP (Claude Desktop, etc.) con la URL del endpoint y el token:

```json
{
  "mcpServers": {
    "plesk": {
      "url": "https://plesk.tudominio.com/",
      "headers": {
        "Authorization": "Bearer TU_MCP_TOKEN_AQUI"
      }
    }
  }
}
```

## Autenticacion

Todas las llamadas POST requieren autenticacion mediante:

- **Header:** `Authorization: Bearer TU_TOKEN`
- **Query param (alternativo):** `?token=TU_TOKEN`

Las llamadas GET (info basica) no requieren autenticacion.

## Protocolo

El conector implementa JSON-RPC 2.0 sobre HTTP POST. Metodos soportados:

- `initialize` - Inicializacion del protocolo MCP
- `tools/list` - Lista las herramientas disponibles
- `tools/call` - Ejecuta una herramienta
- `ping` - Health check

Ejemplo de llamada:

```bash
curl -X POST https://plesk.tudominio.com/ \
  -H "Authorization: Bearer TU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/call",
    "params": {
      "name": "plesk_server_info",
      "arguments": {}
    }
  }'
```

## Seguridad

- Autenticacion obligatoria por token en todas las operaciones
- Whitelist de rutas para lectura de archivos y directorios (`/var/www/vhosts/`, `/var/log/`, `/usr/local/psa/var/log/`, `/etc/postfix/`)
- Whitelist de comandos para Plesk CLI y WP-CLI (solo lectura)
- Validacion con `realpath()` para prevenir path traversal
- `basename()` en dominios para evitar inyeccion de rutas
- `escapeshellarg()` en todos los argumentos pasados a comandos del sistema
- Los helpers ejecutan la logica privilegiada; las tools solo delegan via `sudo`
- Limpieza de cola de correo requiere confirmacion explicita (`confirm: true`)

## Creditos

Desarrollado por **Jordi Torres**

## Licencia

Todos los derechos reservados. Jordi Torres.
