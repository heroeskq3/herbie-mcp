# Conector GICA

Conector independiente de Transparencia PJ. Fuentes públicas oficiales de CEGECA: catálogo acotado de siete fuentes, páginas de normas, metodología, gestión organizacional, oficinas acreditadas, su imagen original y dos normas históricas de 2015 (Defensa Pública y Oficina Administrativa).

- `gica_fuentes`: catálogo con ids y URLs.
- `gica_buscar`: texto de páginas y títulos de originales; tema normas/acreditaciones/gestion/todo. No busca dentro de PDFs ni imágenes. Devuelve errores por fuente y alcance explícito.
- `gica_leer`: id del catálogo y parte. HTML extraído con DOM; PDF mediante drivePdfText (pypdf existente); imágenes o ausencia de texto entregan original público y requieren lectura visual del agente. No usa IA ni OCR. Texto en partes de 14.000 caracteres.

Tarjeta «GICA», categoría Fuentes jurídicas, consulta sin credenciales. Herramientas incorporadas a investigación legal del chat. No modifica Transparencia PJ.

La página actual de normas dice «En proceso de actualización». Oficinas acreditadas publica una imagen: no es un registro estructurado de vigencias. Las normas de 2015 se rotulan históricas; no se afirma vigencia por estar publicadas. No hay conexión autenticada a indicadores internos ni se certifican sellos actuales. No se deduce falta de acreditación por ausencia en búsquedas. Los originales enlazados desde páginas se devuelven como enlaces; gica_leer admite únicamente los siete ids del catálogo.

Límites: hosts HTTPS exactos cegeca.poder-judicial.go.cr y gica.poder-judicial.go.cr, sin redirecciones, credenciales ni puertos; máximo 12 MB y 12 segundos por descarga. Búsqueda recorre cuatro páginas como máximo; no es una búsqueda web exhaustiva. Consulta sin caché. Fecha de consulta no prueba actualización.

Pruebas: tools/test_gica.php (offline y --live) verifica filtrado de HTML, retiro de scripts/navegación, original visual, actualización, hosts y errores; fuentes reales de normas y oficinas leídas. Dos PDFs descargados y lectura nativa comprobada con Python/pypdf en entorno aislado. Suite de contrato MCP: 202 comprobaciones.
