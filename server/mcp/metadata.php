<?php
declare(strict_types=1);

/*
 * Metadatos OAuth del MCP (RFC 9728 y RFC 8414), con el host real de la app. Los sirve /.well-known/ con una regla de
 * .well-known/.htaccess. Son públicos: no tienen datos.
 */
require dirname(__DIR__) . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=3600');
$issuer = rtrim(authUrl(''), '/');
$documents = [
    'protected-resource' => [
        'resource' => authUrl('mcp/'),
        'authorization_servers' => [$issuer],
        'scopes_supported' => ['mcp'],
        'bearer_methods_supported' => ['header'],
        'resource_name' => APP_NAME,
    ],
    'authorization-server' => [
        'issuer' => $issuer,
        'authorization_endpoint' => authUrl('mcp/oauth.php/authorize'),
        'token_endpoint' => authUrl('mcp/oauth.php/token'),
        'registration_endpoint' => authUrl('mcp/oauth.php/register'),
        'scopes_supported' => ['mcp'],
        'response_types_supported' => ['code'],
        'response_modes_supported' => ['query'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
        'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
        'code_challenge_methods_supported' => ['S256'],
        'authorization_response_iss_parameter_supported' => true,
    ],
];
$doc = (string) ($_GET['doc'] ?? '');
if (!isset($documents[$doc])) {
    http_response_code(404);
    exit(json_encode(['error' => 'not_found']));
}
echo json_encode($documents[$doc], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
