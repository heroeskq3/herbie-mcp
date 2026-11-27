# Transparencia PJ para decisiones de inversión

Primera versión publicada el 2026-11-20 desde `7cb41ceb9d302a659f8b2009de35a2c185e6d601`.

Objetivo: consultar evidencia histórica oficial por despacho, materia, procedimiento y período para apoyar estimaciones de costos de espera y escenarios de inversión. No predice casos individuales ni convierte una duración histórica en plazo legal.

## Herramientas

- `transparencia_pj_buscar`: consulta CKAN oficial; devuelve conjuntos, recursos, formatos y fechas publicadas. Buscar `duración`, `activos`, `terminados`, `movimiento` o un diccionario. Pagina conjuntos, no omite recursos de un conjunto.
- `transparencia_pj_leer`: recibe `recurso_id` del catálogo. Lee el CSV completo a un temporal privado y devuelve filas originales con filtros por despacho/código, materia, trámite, fase, año y mes. `filas_coincidentes` cuenta registros estadísticos, NO expedientes. Cada fila conserva Cant, DurDias y demás columnas. Paginación por desplazamiento, máximo 100 filas. No agrega datos ni calcula indicadores sin diccionario.
- `transparencia_pj_fuentes`: enlaces oficiales a Planificación, Observatorio y GICA. No consulta ni certifica acreditaciones o sellos.

## Uso

1. Buscar `duración` y seleccionar el conjunto de terminados o activos según la pregunta.
2. Seleccionar recurso CSV del año requerido. Las notas del conjunto pueden declarar períodos distintos a los recursos; mostrar la discrepancia y conservar ambos metadatos.
3. Consultar con código de despacho y materia/procedimiento/mes. Nombres usan coincidencia parcial sin acentos; códigos son exactos y conservan ceros iniciales.
4. Revisar diccionario y población, fase y forma de terminación antes de calcular duración o comparar oficinas. No mezclar asuntos activos y terminados. No sumar existencias mensuales.
5. Para escenarios presupuestarios, separar evidencia histórica de supuestos económicos aportados por el usuario. No estimar percentiles, promedio, duración restante o probabilidad con filas paginadas.

## Límites

Descarga máxima 256 MB y 45 segundos; catálogo 8 MB y 25 segundos. Sin redirecciones; hosts HTTPS exactos de CKAN y almacenamiento oficial de PJ. Temporal eliminado al terminar. Descargas grandes fallan explícitamente; no se presentan lecturas parciales como totales. La consulta vuelve a descargar el recurso: falta caché/versionado para uso intensivo. Si la fuente cambia entre páginas, los resultados pueden cambiar. No garantiza fecha de corte por la fecha de modificación del recurso.

El siguiente desarrollo de análisis presupuestario requiere validar diccionarios oficiales, unidades de DurDias/Cant y reglas de comparabilidad. La consulta actual ofrece datos y procedencia; no implementa aún un modelo financiero, agregaciones, registro de sellos o un buscador de texto completo GICA.

## Verificación

`php tools/test_transparencia_pj.php`: filtros, caracteres CP1252, códigos con ceros, paginación, ausencia de coincidencias, rechazo de filtros inexistentes, CSV irregular y hosts no admitidos.

`php tools/test_transparencia_pj.php --live`: catálogo real y CSV completo de duración de terminados 2026 (89.753.736 bytes). Código 0485, marzo: 298 filas estadísticas coincidentes; se devolvieron las primeras dos, sin atribuirles un promedio ni tratarlas como total de expedientes.

`php tools/test_mcp_contract.php`: 199 comprobaciones aprobadas, incluidos esquemas de las herramientas nuevas. No se ejecutaron herramientas nuevas desde un cliente conectado a producción.

Antes de editar, mcp/index.php de origin/main d33a1d2 se comparó con producción mediante UAPI y reconstrucción UTF-8 de la lectura Latin-1: coincidía. Cambios locales preexistentes en index.php y conectores_lib.php de la copia principal preservados mediante worktree aislado.
