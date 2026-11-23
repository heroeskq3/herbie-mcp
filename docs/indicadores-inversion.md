# Conectores de indicadores para inversión

Estado documentado: **22 de noviembre de 2026**. Las tarjetas y herramientas están publicadas en Herbie, en **Conectores → Indicadores para inversión**. Este repositorio contiene documentación; la implementación del servidor permanece privada.

## Programa Estado de la Nación (PEN)

Aporta fuentes para estudiar empleo, informalidad, educación y talento, desarrollo territorial, ambiente e institucionalidad. No requiere credenciales.

| Herramienta MCP | Función |
|---|---|
| `estado_nacion_fuentes` | Presenta el catálogo inicial de fuentes y sus límites. |
| `estado_nacion_buscar` | Busca publicaciones indexadas mediante un texto y página. |
| `estado_nacion_leer` | Lee una URL oficial compatible, con paginación. |

Fuentes oficiales: [Programa Estado de la Nación](https://estadonacion.or.cr/), [bases de datos](https://estadonacion.or.cr/base-datos/) y [visualizador estadístico](https://estadisticas.estadonacion.or.cr/).

La lectura admite HTML, PDF con texto nativo, CSV y XLSX. La búsqueda no es exhaustiva dentro de todos los documentos. Los visualizadores JavaScript y los PDF escaneados pueden requerir lectura visual del original por el asistente cliente; el MCP no ejecuta OCR con modelos. Los catálogos de fuentes no son valores actuales de indicadores.

## Banco Central de Costa Rica (BCCR)

Aporta catálogo y series oficiales SDDE para estudiar tipo de cambio, inflación, tasas de interés, actividad económica y sector externo. Son insumos para analizar costos, financiamiento y exposición macroeconómica, no recomendaciones de inversión.

| Herramienta MCP | Función |
|---|---|
| `bccr_fuentes` | Explica las fuentes, acceso y flujo de consulta. |
| `bccr_buscar` | Busca en el catálogo oficial vigente para identificar códigos. |
| `bccr_indicador` | Consulta metadata y observaciones por código, fechas y paginación. |

Fuente y registro: [Indicadores Económicos del BCCR](https://sdd.bccr.fi.cr/es/IndicadoresEconomicos/Inicio/). Contrato de integración: [estándar oficial de la API SDDE](https://gee.bccr.fi.cr/indicadoreseconomicos/Documentos/DocumentosMetodologiasNotasTecnicas/Estandar_API_SDDE.pdf).

El usuario genera su token en Mi Perfil del sitio del BCCR y lo configura en el campo seguro del conector de Herbie. **Nunca debe pegarlo en el chat ni incluirlo en el repositorio.** Cada cuenta usa su propia credencial. Guardar un token no acredita que sea válido: la consulta debe ser aceptada por el proveedor. Se consultan códigos del catálogo vigente, sin asumir códigos heredados.

La herramienta conserva metadata y observaciones originales, incluidos unidades, periodicidad y fechas cuando el proveedor las entrega. No genera valores si falta acceso o la fuente falla. Las fechas de consulta son distintas de las fechas de publicación y del período medido.

## Verificación y límites

- Búsqueda y lectura PEN verificadas contra la fuente oficial.
- Pruebas de formatos XLSX, fechas/rangos, destinos admitidos, token ausente y contrato MCP realizadas.
- Tarjetas y formulario revisados en producción en escritorio y móvil, claro y oscuro.
- **Consulta BCCR autenticada pendiente de una credencial válida.** No se presenta una simulación como dato económico real.
- Nuevas herramientas o instrucciones pueden requerir actualizar la conexión del cliente MCP.

## Ejemplo de solicitud

> Para una fábrica en Costa Rica, identificá indicadores de tipo de cambio, inflación y financiamiento en BCCR y evidencia de empleo y talento del PEN. Contrastá períodos y regiones, citá fuentes y marcá qué información falta antes de formular escenarios.

El asistente debe distinguir datos observados, cálculos propios y escenarios. No debe tratar vacíos como cero, mezclar valores nominales y reales o comparar territorios y frecuencias incompatibles. El cruce normativo se realiza por separado con las fuentes jurídicas correspondientes.

[Ver la orquestación actualizada](orquestacion-mcp.md).
