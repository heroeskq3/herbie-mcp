# Servidor MCP · extensión de inversión

> **Aclaratoria:** esta carpeta es una **extensión de Herbie MCP**. Por motivos de seguridad se publica **solo una parte** del servidor: la estructura de conexión MCP y los conectores nuevos creados en el Dojo AI Hackathon 2026. El resto del servidor (expedientes, clientes, escritos, archivos en la nube, administración, cuentas y credenciales) sigue en el repositorio privado.

## Qué se publica

| Ruta | Qué es |
|---|---|
| `mcp/index.php` | Servidor MCP reducido: JSON-RPC 2.0 por HTTP (Streamable HTTP sin estado), token Bearer, límite por cuenta, bitácora y las 12 herramientas de inversión |
| `mcp/oauth.php` | OAuth 2.1 con PKCE y registro dinámico de clientes, para conectar desde Claude y ChatGPT |
| `mcp/metadata.php`, `.well-known/` | Metadatos OAuth (RFC 9728 y RFC 8414) |
| `transparencia_pj_lib.php` | Conector Transparencia PJ: datos abiertos CKAN del Poder Judicial (duración y carga por despacho) |
| `gica_lib.php` | Conector GICA: normas, metodología y oficinas acreditadas del CEGECA |
| `inversion_indicadores_lib.php` | Conectores Estado de la Nación (PEN) y BCCR (API SDDE con token por usuario) |
| `docs/`, `tools/` | Documentación y pruebas de los conectores |
| `config.example.php` | Configuración de ejemplo, sin valores |

## Herramientas MCP

`gica_fuentes`, `gica_buscar`, `gica_leer`, `transparencia_pj_buscar`, `transparencia_pj_leer`, `transparencia_pj_fuentes`, `estado_nacion_fuentes`, `estado_nacion_buscar`, `estado_nacion_leer`, `bccr_fuentes`, `bccr_buscar`, `bccr_indicador`.

Todas son de solo lectura, consultan únicamente hosts oficiales en HTTPS y devuelven fuente, enlace y fecha de consulta. No dan recomendaciones de inversión ni dictámenes jurídicos.

## Dependencias privadas (no publicadas)

- `auth.php`: cuentas, base de datos, bitácora y URLs del sitio.
- `conectores_lib.php`: credenciales por usuario (por ejemplo, el token SDDE del BCCR).
- `drive_lib.php`: extracción de texto nativo de PDF.

Por eso esta carpeta documenta la arquitectura y el código de los conectores, pero no se ejecuta sola.
