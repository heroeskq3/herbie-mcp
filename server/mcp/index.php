<?php
declare(strict_types=1);

/*
 * Herbie MCP · extensión de inversión (versión pública reducida).
 *
 * Esta es una EXTENSIÓN de Herbie MCP. Por motivos de seguridad se publica solo una parte del servidor: el transporte
 * MCP (JSON-RPC 2.0 por HTTP), la autorización por token, el límite de consultas y las herramientas de los conectores
 * de inversión creados en el Dojo AI Hackathon 2026 (Transparencia PJ, GICA, Estado de la Nación y BCCR).
 * Las herramientas de expedientes, clientes, escritos, archivos en la nube y administración siguen en el repositorio
 * privado, igual que auth.php (cuentas, base de datos y bitácora) y conectores_lib.php (credenciales por usuario).
 *
 * Transporte: Streamable HTTP sin estado, JSON-RPC 2.0 por POST con respuesta JSON (sin SSE).
 * Acceso: "Authorization: Bearer <token>", guardado solo como sha256:
 *   - tokens OAuth de oauth.php (conectores de claude.ai y ChatGPT), en la tabla mcp_tokens;
 *   - llaves personales en <PDJ_MCP_DIR>/keys.json, cada una con el "email" de su cuenta.
 * Límites por cuenta: MCP_LIMIT_MINUTE consultas por minuto y MCP_LIMIT_DAY por día. Cada consulta va a la bitácora.
 */

require dirname(__DIR__) . '/auth.php';                       // privado: authQuery, authUserById, authLog, ...
require dirname(__DIR__) . '/conectores_lib.php';             // privado: connectorGet (token BCCR por usuario)
require dirname(__DIR__) . '/drive_lib.php';                  // privado: drivePdfText (texto nativo de PDF)
require dirname(__DIR__) . '/transparencia_pj_lib.php';
require dirname(__DIR__) . '/gica_lib.php';
require dirname(__DIR__) . '/inversion_indicadores_lib.php';

const MCP_SERVER_VERSION = '1.0.0';
const MCP_PROTOCOLS = ['2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05'];
const MCP_LIMIT_MINUTE = 60;
const MCP_LIMIT_DAY = 2000;
define('MCP_SITE_URL', authUrl(''));

$mcpDataDir = getenv('PDJ_MCP_DIR') ?: dirname(__DIR__, 2) . '/pdj-sync/mcp';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function mcpSend(int $status, $payload = null): void
{
    http_response_code($status);
    if ($payload !== null) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

function mcpError($id, int $code, string $message): array
{
    return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
}

/* ------------------------------------------------------------------ Acceso */

function mcpBearer(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = $value;
            }
        }
    }
    if (preg_match('/^Bearer\s+(\S+)$/i', trim((string) $header), $match)) {
        return $match[1];
    }
    return trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
}

/*
 * Quién llama: ['user' => cuenta activa, 'client' => nombre] o null. Compara hashes en tiempo constante. El token se
 * invalida si la cuenta se suspende o si cambió la contraseña después de entregarlo.
 */
function mcpAuthorize(string $dataDir): ?array
{
    $token = mcpBearer();
    if ($token === '') {
        return null;
    }
    $hash = hash('sha256', $token);
    $keys = json_decode((string) @file_get_contents($dataDir . '/keys.json'), true);
    foreach (is_array($keys) ? $keys : [] as $key) {
        if (!empty($key['sha256']) && empty($key['revoked']) && hash_equals((string) $key['sha256'], $hash)) {
            $user = !empty($key['email']) ? authUserByEmail((string) $key['email']) : null;
            return $user && $user['status'] === 'activo' ? ['user' => $user, 'client' => 'llave:' . ($key['name'] ?? 'sin-nombre')] : null;
        }
    }
    $grant = authQuery("SELECT * FROM mcp_tokens WHERE token_hash = ? AND kind = 'access'", [$hash])->fetch();
    if (!$grant || strtotime($grant['expires_at'] . ' UTC') < time()) {
        return null;
    }
    $user = authUserById((int) $grant['user_id']);
    if (!$user || $user['status'] !== 'activo'
        || ($user['password_changed_at'] && strtotime($grant['created_at'] . ' UTC') < strtotime($user['password_changed_at'] . ' UTC'))) {
        return null;
    }
    authQuery('UPDATE mcp_tokens SET last_used_at = ? WHERE id = ?', [authNow(), $grant['id']]);
    $client = authQuery('SELECT client_name FROM mcp_clients WHERE client_id = ?', [$grant['client_id']])->fetchColumn();
    return ['user' => $user, 'client' => 'oauth:' . ($client ?: $grant['client_id'])];
}

/* Límite por cuenta: devuelve un mensaje si se pasó, o ''. */
function mcpRateLimit(array $user): string
{
    $id = (string) $user['id'];
    $minute = (int) authQuery("SELECT COUNT(*) FROM auth_attempts WHERE scope = 'mcp' AND ident = ? AND created_at > ?", [$id, authAt(-60)])->fetchColumn();
    if ($minute >= MCP_LIMIT_MINUTE) {
        return 'Demasiadas consultas en un minuto (máximo ' . MCP_LIMIT_MINUTE . '). Esperá un momento.';
    }
    $day = (int) authQuery("SELECT COUNT(*) FROM auth_attempts WHERE scope = 'mcp' AND ident = ? AND created_at > ?", [$id, authAt(-86400)])->fetchColumn();
    if ($day >= MCP_LIMIT_DAY) {
        return 'Se alcanzó el máximo de ' . MCP_LIMIT_DAY . ' consultas por día.';
    }
    authRecordFailure('mcp', $id);
    return '';
}

/* ------------------------------------------------------------------ Herramientas */

function mcpTools(): array
{
    $ro = ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => true];
    return array_merge([
        ['name'=>'gica_fuentes', 'title'=>'Fuentes oficiales GICA y CEGECA', 'annotations'=>$ro,
            'description'=>'Catálogo acotado de fuentes oficiales de normas GICA, metodología y oficinas acreditadas, con ids para gica_leer. No consulta indicadores internos ni certifica vigencia de sellos.',
            'inputSchema'=>['type'=>'object','properties'=>new stdClass()]],
        ['name'=>'gica_buscar', 'title'=>'Buscar en GICA', 'annotations'=>$ro,
            'description'=>'Busca en texto de páginas oficiales CEGECA del catálogo y títulos de originales. No busca dentro de PDFs ni imágenes. Use gica_leer para contenido. Una publicación histórica no acredita un sello vigente.',
            'inputSchema'=>['type'=>'object','properties'=>['texto'=>['type'=>'string'],'tema'=>['type'=>'string','enum'=>['todo','normas','acreditaciones','gestion']]]]],
        ['name'=>'gica_leer', 'title'=>'Leer fuente GICA', 'annotations'=>$ro,
            'description'=>'Lee una fuente por id de gica_fuentes/gica_buscar. HTML y PDF con texto nativo paginado; imágenes y escaneos requieren lectura visual por el agente, sin OCR del MCP. Devuelve original oficial. Normas 2015 históricas; no afirmar vigencia ni acreditación actual sin corroborar.',
            'inputSchema'=>['type'=>'object','required'=>['id'],'properties'=>['id'=>['type'=>'string'],'parte'=>['type'=>'integer','minimum'=>1]]]],
        ['name'=>'transparencia_pj_buscar', 'title'=>'Buscar estadísticas públicas del Poder Judicial', 'annotations'=>$ro,
            'description'=>'Busca conjuntos y recursos oficiales CKAN: duración de expedientes terminados, antigüedad de activos, carga y diccionarios. Para decisiones de inversión y presupuesto, filtre después por despacho, materia, trámite y período con transparencia_pj_leer. No predice duración de casos particulares.',
            'inputSchema'=>['type'=>'object','properties'=>['texto'=>['type'=>'string'], 'pagina'=>['type'=>'integer','minimum'=>1], 'limite'=>['type'=>'integer','minimum'=>1,'maximum'=>10]]]],
        ['name'=>'transparencia_pj_leer', 'title'=>'Consultar datos públicos por despacho y trámite', 'annotations'=>$ro,
            'description'=>'Lee una distribución CSV oficial por recurso_id obtenido de transparencia_pj_buscar. Devuelve filas originales filtradas y paginadas, metadatos y fecha de consulta. Verifique diccionario, Cant y DurDias antes de calcular promedios. No sumar existencias de varios meses.',
            'inputSchema'=>['type'=>'object','required'=>['recurso_id'],'properties'=>[
                'recurso_id'=>['type'=>'string'], 'despacho'=>['type'=>'string'], 'codigo_despacho'=>['type'=>'string'],
                'materia'=>['type'=>'string'], 'tramite'=>['type'=>'string'], 'fase'=>['type'=>'string'],
                'anno'=>['type'=>'string'], 'mes'=>['type'=>'string'], 'desplazamiento'=>['type'=>'integer','minimum'=>0], 'limite'=>['type'=>'integer','minimum'=>1,'maximum'=>100]]]],
        ['name'=>'transparencia_pj_fuentes', 'title'=>'Fuentes de indicadores y calidad judicial', 'annotations'=>$ro,
            'description'=>'Enlaces oficiales al Observatorio, Planificación y normas GICA. No certifica sellos ni consulta su vigencia.',
            'inputSchema'=>['type'=>'object','properties'=>new stdClass()]],
    ], invTools());
}

function mcpCallTool(string $name, array $args, array $user): array
{
    switch ($name) {
        case 'gica_fuentes':
            return gicaCatalog();
        case 'gica_buscar':
            return gicaSearch((string)($args['texto'] ?? ''), (string)($args['tema'] ?? 'todo'));
        case 'gica_leer':
            return gicaRead((string)($args['id'] ?? ''), (int)($args['parte'] ?? 1));
        case 'transparencia_pj_buscar':
            return tpjSearch((string)($args['texto'] ?? ''), (int)($args['pagina'] ?? 1), (int)($args['limite'] ?? 5));
        case 'transparencia_pj_leer':
            return tpjRead((string)($args['recurso_id'] ?? ''), array_intersect_key($args, array_flip(['despacho','codigo_despacho','materia','tramite','fase','anno','mes'])), (int)($args['desplazamiento'] ?? 0), (int)($args['limite'] ?? 50));
        case 'transparencia_pj_fuentes':
            return tpjSources();
        case 'estado_nacion_fuentes':
            return invPenCatalog();
        case 'estado_nacion_buscar':
            return invPenSearch((string)($args['texto'] ?? ''), (int)($args['pagina'] ?? 1));
        case 'estado_nacion_leer':
            return invPenRead((string)($args['url'] ?? ''), (int)($args['parte'] ?? 1));
        case 'bccr_fuentes':
            return invBccrCatalog();
        case 'bccr_buscar':
            return invBccrSearch($user, (string)($args['texto'] ?? ''), (int)($args['desplazamiento'] ?? 0));
        case 'bccr_indicador':
            return invBccrIndicator($user, (int)($args['codigo'] ?? 0), (string)($args['desde'] ?? ''), (string)($args['hasta'] ?? ''), (int)($args['desplazamiento'] ?? 0), (int)($args['limite'] ?? 100));
    }
    throw new DomainException("Herramienta desconocida: $name");
}

/* ------------------------------------------------------------------ Protocolo */

function mcpInstructions(): string
{
    return 'Herbie MCP · extensión de inversión. Datos públicos oficiales de Costa Rica con fuente, enlace y fecha de consulta. '
        . 'No da recomendaciones de inversión ni dictámenes jurídicos. No invente valores; distinga dato oficial, cálculo y escenario.';
}

function mcpHandle(array $message, array $caller)
{
    $id = $message['id'] ?? null;
    $method = (string) ($message['method'] ?? '');
    if (!array_key_exists('id', $message)) {
        return null;   // notificación (p. ej. notifications/initialized): no lleva respuesta
    }
    switch ($method) {
        case 'initialize':
            $requested = (string) ($message['params']['protocolVersion'] ?? '');
            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                'protocolVersion' => in_array($requested, MCP_PROTOCOLS, true) ? $requested : MCP_PROTOCOLS[0],
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'herbie-mcp-inversion', 'title' => 'Herbie MCP', 'version' => MCP_SERVER_VERSION, 'websiteUrl' => MCP_SITE_URL],
                'instructions' => mcpInstructions(),
            ]];
        case 'ping':
            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => new stdClass()];
        case 'tools/list':
            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['tools' => mcpTools()]];
        case 'tools/call':
            $name = (string) ($message['params']['name'] ?? '');
            $args = is_array($message['params']['arguments'] ?? null) ? $message['params']['arguments'] : [];
            $limited = mcpRateLimit($caller['user']);
            $log = static function (string $outcome) use ($caller, $name): void {
                authLog((int) $caller['user']['id'], 'mcp-' . mb_substr($name, 0, 30), $caller['client'] . ' · ' . $outcome, $outcome === 'ok' ? 'info' : 'aviso');
            };
            if ($limited !== '') {
                $log('límite');
                return ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['content' => [['type' => 'text', 'text' => $limited]], 'isError' => true]];
            }
            try {
                $data = mcpCallTool($name, $args, $caller['user']);
                $log('ok');
                return ['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                    'content' => [['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
                    'structuredContent' => $data, 'isError' => false]];
            } catch (DomainException $exc) {
                $log('error: desconocida');
                return mcpError($id, -32602, $exc->getMessage());
            } catch (Throwable $exc) {
                $log('error');
                return ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['content' => [['type' => 'text', 'text' => $exc->getMessage()]], 'isError' => true]];
            }
        case 'resources/list':
            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['resources' => []]];
        case 'prompts/list':
            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['prompts' => []]];
    }
    return mcpError($id, -32601, "Método no soportado: $method");
}

/* ------------------------------------------------------------------ Entrada */

// Clientes web (MCP Inspector): CORS abierto; la autorización va en el encabezado, no en cookies.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Mcp-Protocol-Version, Mcp-Session-Id, X-API-Key');
header('Access-Control-Expose-Headers: WWW-Authenticate');
$httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($httpMethod === 'OPTIONS') {
    header('Allow: POST, OPTIONS');
    mcpSend(204);
}
$caller = mcpAuthorize($mcpDataDir);
if ($caller === null) {
    // Indica dónde autorizarse (RFC 9728): así los conectores de claude.ai y ChatGPT descubren el OAuth.
    header('WWW-Authenticate: Bearer realm="herbie-mcp", resource_metadata="' . authOrigin() . '/.well-known/oauth-protected-resource"');
    mcpSend(401, mcpError(null, -32001, 'Falta una llave válida: Authorization: Bearer <llave>.'));
}
if ($httpMethod !== 'POST') {
    header('Allow: POST, OPTIONS');
    mcpSend(405, mcpError(null, -32000, 'Use POST con JSON-RPC 2.0.'));
}
$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    mcpSend(400, mcpError(null, -32700, 'JSON inválido.'));
}
$isBatch = array_keys($body) === range(0, count($body) - 1);
$responses = [];
foreach ($isBatch ? $body : [$body] as $message) {
    $response = is_array($message) ? mcpHandle($message, $caller) : mcpError(null, -32600, 'Solicitud inválida.');
    if ($response !== null) {
        $responses[] = $response;
    }
}
if (!$responses) {
    mcpSend(202);
}
mcpSend(200, $isBatch ? $responses : $responses[0]);
