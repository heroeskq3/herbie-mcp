<?php
/*
 * Copie este archivo como config.local.php (local) o como ~/pdj-sync/app-config.php (servidor, fuera de la carpeta
 * pública) y complete los valores. Nunca lo suba al repositorio.
 * app_key: 32 bytes aleatorios en base64 →  php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
 */
return [
    'base_url' => 'http://localhost/herbie-pj/',
    'db_dsn' => 'mysql:host=localhost;dbname=herbie_pj;charset=utf8mb4',
    'db_user' => 'herbie_pj',
    'db_pass' => '',
    'app_key' => '',
    // Otros dominios desde los que se puede abrir la app (además del de base_url): los enlaces y las URLs del MCP
    // usan el dominio real de la visita solo si está en esta lista, p. ej. ['www.ejemplo.com', 'app.ejemplo.com'].
    'allowed_hosts' => [],
    // Carpeta donde vive la carpeta de datos de cada usuario (users.data_dir).
    // En producción se recomienda fuera del webroot: '/ruta/fuera/del/webroot/herbie-data'.
    'data_base' => __DIR__ . '/data',
    // 'sendmail' envía de verdad; 'log' escribe los correos en mail_log (útil en local).
    'mail_mode' => 'log',
    'mail_log' => sys_get_temp_dir() . '/pj-mail.log',
    'mail_from' => 'notificaciones@herbieai.app',
    // Cloudflare Turnstile. Vacíos = sin captcha. Llaves de prueba (siempre aprueban):
    //   sitio 1x00000000000000000000AA · secreta 1x0000000000000000000000000000000AA
    'turnstile_site' => '',
    'turnstile_secret' => '',
    // "Continuar con Google": credencial OAuth de tipo "Aplicación web" en Google Cloud, con la URI de
    // redireccionamiento <base_url>google.php. Vacíos = sin botón de Google.
    'google_client_id' => '',
    'google_client_secret' => '',
    // Conector de OneDrive: app registrada en Microsoft Entra (cuentas de cualquier organización y personales), con la URI de
    // redireccionamiento web <base_url>microsoft.php y permisos delegados de Microsoft Graph Files.ReadWrite.All, User.Read y
    // offline_access. Vacíos = OneDrive queda como «Próximamente».
    'ms_client_id' => '',
    'ms_client_secret' => '',
    // Python con pypdf para leer el texto de las plantillas PDF (MCP). En el servidor: '/usr/bin/python3' y
    // pdf_pythonpath = '<home>/pdj-sync/auto/pylib' (donde está instalado pypdf).
    'pdf_python' => 'python3',
    'pdf_pythonpath' => '',
    // Verificación en dos pasos (lista, apagada por ahora).
    'mfa' => false,
    // En local muestra en pantalla los enlaces que irían por correo.
    'dev' => false,
    // Sincronización con PDJENLINEA. sync_dir: carpeta del motor (por defecto ../pdj-sync).
    'sync_dir' => '',
];
