<?php
declare(strict_types=1);

/*
 * Servidor de autorización OAuth 2.1 del MCP de "Mis expedientes" (conectores de claude.ai y ChatGPT), por usuario.
 *   /mcp/oauth.php/register   Registro dinámico de clientes (RFC 7591), solo con redirecciones de hosts conocidos.
 *   /mcp/oauth.php/authorize  Exige sesión en la app (contraseña o Google, con captcha y 2FA) y pide autorizar a ESA cuenta.
 *   /mcp/oauth.php/token      Canje del código (PKCE S256) y renovación (refresh token con rotación).
 * Cada código y token queda atado a una sola cuenta (mcp_codes / mcp_tokens); en la base solo se guarda su sha256.
 * Metadatos: /.well-known/oauth-authorization-server y /.well-known/oauth-protected-resource (archivos estáticos).
 */

require dirname(__DIR__) . '/auth.php';

const OAUTH_CODE_TTL = 300;
const OAUTH_ACCESS_TTL = 3600;
const OAUTH_REFRESH_TTL = 30 * 86400;

header('Cache-Control: no-store');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

function oauthIssuer(): string
{
    return rtrim(authUrl(''), '/');
}

function oauthJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function oauthRandom(): string
{
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

/* Solo se aceptan redirecciones de los clientes previstos: claude.ai/claude.com, ChatGPT/OpenAI, Postman y localhost. */
function oauthRedirectAllowed(string $uri): bool
{
    $parts = parse_url($uri);
    if (!$parts || empty($parts['scheme']) || empty($parts['host']) || isset($parts['fragment'])) {
        return false;
    }
    $host = strtolower($parts['host']);
    if (in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true)) {
        return in_array($parts['scheme'], ['http', 'https'], true);
    }
    if ($parts['scheme'] !== 'https') {
        return false;
    }
    foreach (['claude.ai', 'claude.com', 'chatgpt.com', 'openai.com', 'oauth.pstmn.io'] as $domain) {
        if ($host === $domain || substr($host, -strlen('.' . $domain)) === '.' . $domain) {
            return true;
        }
    }
    return false;
}

function oauthClient(string $clientId): ?array
{
    $row = authQuery('SELECT * FROM mcp_clients WHERE client_id = ?', [$clientId])->fetch();
    if (!$row) {
        return null;
    }
    $row['redirect_uris'] = json_decode((string) $row['redirect_uris'], true) ?: [];
    return $row;
}

/* ---------------------------------------------------------------- Pantalla de autorización */

function oauthPage(array $client, array $params, array $user, string $error = ''): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: DENY');
    /* form-action también rige la redirección que sigue al POST: los clientes locales (mcp-remote, Claude Desktop…)
       esperan el código en http://localhost:PUERTO, y sin esto el navegador bloquea la vuelta en silencio. */
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; form-action 'self' https: http://localhost:* http://127.0.0.1:*; frame-ancestors 'none'");
    $e = static function ($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
    /* La página se sirve como mcp/oauth.php/authorize: las hojas de estilo se piden desde la raíz del sitio. */
    $root = rtrim(dirname((string) $_SERVER['SCRIPT_NAME'], 2), '/') . '/';
    $css = static function (string $file) use ($root, $e): string {
        return '<link rel="stylesheet" href="' . $e($root . 'assets/css/' . $file . '?v=' . (int) @filemtime(dirname(__DIR__) . '/assets/css/' . $file)) . '">';
    };
    $host = parse_url($params['redirect_uri'], PHP_URL_HOST);
    $hidden = authCsrfField();
    foreach (['response_type', 'client_id', 'redirect_uri', 'code_challenge', 'code_challenge_method', 'state', 'scope', 'resource'] as $name) {
        $hidden .= '<input type="hidden" name="' . $name . '" value="' . $e($params[$name] ?? '') . '">';
    }
    [$prefix, $name] = authDisplayName($user);
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>Autorizar acceso · ' . APP_NAME . '</title>'
        . '<meta name="color-scheme" content="light dark"><script src="' . $e($root . 'assets/js/theme.js?v=1') . '"></script>' . $css('tokens.css') . $css('oauth.css') . '</head><body><!--email_off--><main class="card"><header><h1 class="brand-h1">' . APP_NAME . '</h1></header>'
        . '<form method="post"><h2 class="page-title">Autorizar acceso por MCP</h2>' . $hidden
        . '<p class="who"><strong>' . $e($client['client_name'] ?: 'Una aplicación') . '</strong> quiere acceder a los expedientes de <strong>' . $e(trim("$prefix $name")) . '</strong> (' . $e($user['email']) . ')'
            . ((int) ($user['mcp_write'] ?? 1) ? ': leerlos y, con tu confirmación, crear expedientes, agregar documentos y manejar tus plantillas y tu calendario. Nunca borra expedientes ni documentos.' : ', solo lectura.') . '<small>Volverá a ' . $e($host) . '</small></p>'
        . ($error !== '' ? '<p class="error" role="alert">' . $e($error) . '</p>' : '')
        . '<div class="actions"><button type="submit" name="decision" value="allow" class="allow">Autorizar</button><button type="submit" name="decision" value="deny" class="deny">Cancelar</button></div>'
        . '<p class="note">Solo verá los expedientes de esta cuenta, nunca los de otras. Podés desconectarlo cuando quieras desde Seguridad de la cuenta.'
        . ' ¿No sos vos? <a href="' . $e(authUrl('cuenta.php?a=seguridad')) . '">Cambiá de cuenta</a>.</p>'
        . '</form></main>' . authFooterHtml() . '<!--/email_off--></body></html>';
    exit;
}

function oauthRedirect(string $uri, array $query): void
{
    header('Location: ' . $uri . (strpos($uri, '?') === false ? '?' : '&') . http_build_query($query));
    http_response_code(302);
    exit;
}

/* ---------------------------------------------------------------- Endpoints */

/* Registro de conectores: a lo sumo OAUTH_REGISTER_PER_DAY por IP al día (cuentan para el bloqueo por abuso); los que
   nunca se autorizaron se borran al día siguiente. */
const OAUTH_REGISTER_PER_DAY = 5;

function oauthRegister(): void
{
    authAnonGuard('mcp-registro');
    $today = (int) authQuery('SELECT COUNT(*) FROM auth_log WHERE event = ? AND ip = ? AND created_at > ?', ['mcp-registro', authClientIp(), authAt(-86400)])->fetchColumn();
    authRecordFailure('anon', authClientIp());
    if ($today >= OAUTH_REGISTER_PER_DAY) {
        authLogNoSession('mcp-registro-limite', 'más de ' . OAUTH_REGISTER_PER_DAY . ' registros de conector en un día');
        oauthJson(429, ['error' => 'too_many_requests', 'error_description' => 'Demasiados registros de conectores desde esta dirección. Probá mañana.']);
    }
    authQuery('DELETE FROM mcp_clients WHERE created_at < ? AND client_id NOT IN (SELECT client_id FROM mcp_tokens) AND client_id NOT IN (SELECT client_id FROM mcp_codes)', [authAt(-86400)]);
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        oauthJson(400, ['error' => 'invalid_client_metadata', 'error_description' => 'Se esperaba JSON.']);
    }
    $uris = array_values(array_filter((array) ($input['redirect_uris'] ?? []), 'is_string'));
    if (!$uris || count($uris) > 10) {
        oauthJson(400, ['error' => 'invalid_redirect_uri', 'error_description' => 'Faltan redirect_uris.']);
    }
    foreach ($uris as $uri) {
        if (!oauthRedirectAllowed($uri)) {
            authLog(null, 'mcp-registro', 'redirección rechazada: ' . $uri, 'aviso');
            oauthJson(400, ['error' => 'invalid_redirect_uri', 'error_description' => "Redirección no permitida: $uri"]);
        }
    }
    $method = (string) ($input['token_endpoint_auth_method'] ?? 'none');
    if (!in_array($method, ['none', 'client_secret_post', 'client_secret_basic'], true)) {
        $method = 'none';
    }
    $client = [
        'client_id' => 'mcp_' . oauthRandom(),
        'client_id_issued_at' => time(),
        'client_name' => mb_substr(trim((string) ($input['client_name'] ?? 'Cliente MCP')), 0, 80) ?: 'Cliente MCP',
        'redirect_uris' => $uris,
        'grant_types' => ['authorization_code', 'refresh_token'],
        'response_types' => ['code'],
        'token_endpoint_auth_method' => $method,
        'scope' => 'mcp',
    ];
    $secretHash = null;
    if ($method !== 'none') {
        $client['client_secret'] = oauthRandom();
        $client['client_secret_expires_at'] = 0;
        $secretHash = hash('sha256', $client['client_secret']);
    }
    authQuery('INSERT INTO mcp_clients (client_id, client_name, redirect_uris, auth_method, secret_hash, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$client['client_id'], $client['client_name'], json_encode($uris, JSON_UNESCAPED_SLASHES), $method, $secretHash, authNow()]);
    authLog(null, 'mcp-registro', $client['client_name']);
    oauthJson(201, $client);
}

function oauthAuthorize(): void
{
    $params = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    $params = array_map(static function ($value) { return is_string($value) ? $value : ''; }, $params);
    $client = oauthClient($params['client_id'] ?? '');
    $redirect = $params['redirect_uri'] ?? '';
    if (!$client || !in_array($redirect, $client['redirect_uris'], true)) {
        // Sin cliente o redirección válidos no se redirige a ningún lado (evita reenviar a un sitio ajeno).
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Solicitud de autorización inválida: cliente o redirect_uri desconocidos.');
    }
    $back = static function (array $query) use ($redirect, $params) {
        if (($params['state'] ?? '') !== '') {
            $query['state'] = $params['state'];
        }
        $query['iss'] = oauthIssuer();
        oauthRedirect($redirect, $query);
    };
    if (($params['response_type'] ?? '') !== 'code') {
        $back(['error' => 'unsupported_response_type']);
    }
    if (($params['code_challenge'] ?? '') === '' || ($params['code_challenge_method'] ?? '') !== 'S256') {
        $back(['error' => 'invalid_request', 'error_description' => 'PKCE con S256 es obligatorio.']);
    }
    $resource = $params['resource'] ?? '';
    if ($resource !== '' && strpos(rtrim($resource, '/'), oauthIssuer()) !== 0) {
        $back(['error' => 'invalid_target']);
    }
    /* Sin sesión en la app: a ingresar (con todo lo de la app: captcha, límites, 2FA, Google) y volver aquí. */
    $user = authCurrentUser();
    if (!$user) {
        $here = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $here = strtok($here, '?') . '?' . http_build_query(array_intersect_key($params, array_flip(['response_type', 'client_id', 'redirect_uri', 'code_challenge', 'code_challenge_method', 'state', 'scope', 'resource'])));
        }
        header('Location: ' . authUrl('cuenta.php?a=ingresar&sig=' . rawurlencode($here)));
        http_response_code(302);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        oauthPage($client, $params, $user);
    }
    if (!authCsrfValid()) {
        oauthPage($client, $params, $user, 'La página venció. Volvé a pulsar Autorizar.');
    }
    if (($params['decision'] ?? '') !== 'allow') {
        authLog((int) $user['id'], 'mcp-autorizar', 'cancelado: ' . $client['client_name']);
        $back(['error' => 'access_denied']);
    }
    $code = oauthRandom();
    authQuery('INSERT INTO mcp_codes (code_hash, client_id, user_id, redirect_uri, challenge, resource, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [hash('sha256', $code), $client['client_id'], $user['id'], $redirect, $params['code_challenge'], $resource, authAt(OAUTH_CODE_TTL)]);
    authLog((int) $user['id'], 'mcp-autorizar', $client['client_name']);
    $back(['code' => $code]);
}

/* Entrega un par de tokens atados a la cuenta. */
function oauthIssue(array $client, int $userId, string $resource): array
{
    $access = oauthRandom();
    $refresh = oauthRandom();
    foreach ([[$access, 'access', OAUTH_ACCESS_TTL], [$refresh, 'refresh', OAUTH_REFRESH_TTL]] as [$token, $kind, $ttl]) {
        authQuery('INSERT INTO mcp_tokens (token_hash, kind, client_id, user_id, resource, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [hash('sha256', $token), $kind, $client['client_id'], $userId, $resource, authNow(), authAt($ttl)]);
    }
    return ['access_token' => $access, 'token_type' => 'Bearer', 'expires_in' => OAUTH_ACCESS_TTL, 'refresh_token' => $refresh, 'scope' => 'mcp'];
}

function oauthToken(): void
{
    $input = $_POST;
    if (!$input) {
        $json = json_decode((string) file_get_contents('php://input'), true);
        $input = is_array($json) ? $json : [];
    }
    $clientId = (string) ($input['client_id'] ?? '');
    $secret = (string) ($input['client_secret'] ?? '');
    $basic = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Basic\s+(.+)$/i', $basic, $match)) {
        $pair = explode(':', (string) base64_decode($match[1]), 2);
        $clientId = urldecode($pair[0]);
        $secret = urldecode($pair[1] ?? '');
    }
    $client = oauthClient($clientId);
    if (!$client || (!empty($client['secret_hash']) && !hash_equals($client['secret_hash'], hash('sha256', $secret)))) {
        authLog(null, 'mcp-token', 'cliente inválido', 'aviso');
        oauthJson(401, ['error' => 'invalid_client']);
    }
    $grant = (string) ($input['grant_type'] ?? '');
    authQuery('DELETE FROM mcp_codes WHERE expires_at < ?', [authNow()]);
    authQuery('DELETE FROM mcp_tokens WHERE expires_at < ?', [authNow()]);
    if ($grant === 'authorization_code') {
        $key = hash('sha256', (string) ($input['code'] ?? ''));
        $code = authQuery('SELECT * FROM mcp_codes WHERE code_hash = ?', [$key])->fetch();
        authQuery('DELETE FROM mcp_codes WHERE code_hash = ?', [$key]);   // un solo uso, aun si falla la verificación
        $verifier = (string) ($input['code_verifier'] ?? '');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        if (!$code || strtotime($code['expires_at'] . ' UTC') < time() || $code['client_id'] !== $clientId
            || $code['redirect_uri'] !== (string) ($input['redirect_uri'] ?? '') || $verifier === '' || !hash_equals($code['challenge'], $challenge)) {
            authLog($code ? (int) $code['user_id'] : null, 'mcp-token', 'código inválido', 'aviso');
            oauthJson(400, ['error' => 'invalid_grant']);
        }
        $user = authUserById((int) $code['user_id']);
        if (!$user || $user['status'] !== 'activo') {
            oauthJson(400, ['error' => 'invalid_grant']);
        }
        authLog((int) $user['id'], 'mcp-token', 'conectado: ' . $client['client_name']);
        oauthJson(200, oauthIssue($client, (int) $user['id'], (string) $code['resource']));
    }
    if ($grant === 'refresh_token') {
        $key = hash('sha256', (string) ($input['refresh_token'] ?? ''));
        $token = authQuery("SELECT * FROM mcp_tokens WHERE token_hash = ? AND kind = 'refresh'", [$key])->fetch();
        $user = $token ? authUserById((int) $token['user_id']) : null;
        $valid = $token && $user && $user['status'] === 'activo' && $token['client_id'] === $clientId && strtotime($token['expires_at'] . ' UTC') >= time()
            && (!$user['password_changed_at'] || strtotime($token['created_at'] . ' UTC') >= strtotime($user['password_changed_at'] . ' UTC'));
        if (!$valid) {
            oauthJson(400, ['error' => 'invalid_grant']);
        }
        authQuery('DELETE FROM mcp_tokens WHERE id = ?', [$token['id']]);   // rotación: el refresh token usado deja de servir
        oauthJson(200, oauthIssue($client, (int) $user['id'], (string) $token['resource']));
    }
    oauthJson(400, ['error' => 'unsupported_grant_type']);
}

/* ---------------------------------------------------------------- Ruteo */

$route = trim((string) ($_SERVER['PATH_INFO'] ?? ''), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
// register y token los llaman los clientes (también desde el navegador, p. ej. MCP Inspector): CORS abierto, sin cookies.
if ($route !== 'authorize') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
if ($route === 'register' && $method === 'POST') {
    oauthRegister();
}
if ($route === 'authorize' && in_array($method, ['GET', 'POST'], true)) {
    oauthAuthorize();
}
if ($route === 'token' && $method === 'POST') {
    oauthToken();
}
oauthJson(404, ['error' => 'not_found']);
