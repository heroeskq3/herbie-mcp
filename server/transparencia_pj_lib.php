<?php
declare(strict_types=1);
/* Fuentes públicas del PJ, sin inferencia ni acceso a expedientes privados. */
if (!function_exists('authConfig')) { http_response_code(404); exit; }
const TPJ_API = 'https://datosabiertospj.poder-judicial.go.cr/api/3/action/';
const TPJ_REGLAS = 'Datos históricos publicados, no predicciones ni plazos legales. No confundir antigüedad de activos con duración de terminados, escritos con expedientes, modificación del catálogo con fecha de corte, ni ausencia de sello con falta de acreditación. No calcular promedios de DurDias/Cant sin verificar el diccionario; no sumar existencias de meses distintos. Los resultados paginados no son totales del despacho.';
function tpjAllowed(string $url): bool {
    $p = parse_url($url);
    return ($p['scheme'] ?? '') === 'https' && !isset($p['user']) && !isset($p['pass']) && !isset($p['port'])
        && in_array($p['host'] ?? '', ['datosabiertospj.poder-judicial.go.cr', 'pjcrdatosabiertos.blob.core.windows.net'], true);
}
function tpjGet(string $url, int $max = 8388608): string {
    if (!tpjAllowed($url)) { throw new InvalidArgumentException('Fuente fuera de los hosts oficiales admitidos.'); }
    $body = ''; $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'Herbie-TransparenciaPJ/1.0', CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body, $max): int {
            if (strlen($body) + strlen($chunk) > $max) { return 0; } $body .= $chunk; return strlen($chunk);
        }]);
    $ok = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); unset($ch);
    if ($ok === false || $status !== 200) { throw new RuntimeException('La fuente oficial no respondió correctamente o excedió el límite de lectura.'); }
    return $body;
}
function tpjApi(string $action, array $params): array {
    $d = json_decode(tpjGet(TPJ_API . $action . '?' . http_build_query($params)), true);
    if (!is_array($d) || ($d['success'] ?? false) !== true || !is_array($d['result'] ?? null)) { throw new RuntimeException('Respuesta inválida del catálogo oficial.'); }
    return $d['result'];
}
function tpjSearch(string $text, int $page = 1, int $limit = 5): array {
    $limit = max(1, min(10, $limit)); $page = max(1, $page);
    $d = tpjApi('package_search', ['q' => trim($text), 'rows' => $limit, 'start' => ($page - 1) * $limit]);
    $out = [];
    foreach ($d['results'] ?? [] as $p) {
        $rs = [];
        foreach ($p['resources'] ?? [] as $r) {
            $rs[] = ['id' => $r['id'], 'nombre' => $r['name'] ?? '', 'formato' => $r['format'] ?? '',
                'url' => $r['url'] ?? '', 'modificado_fuente' => $r['last_modified'] ?? null];
        }
        $out[] = ['id' => $p['id'], 'titulo' => $p['title'], 'descripcion' => $p['notes'] ?? '',
            'url' => 'https://datosabiertospj.poder-judicial.go.cr/dataset/' . rawurlencode($p['name']),
            'metadatos_modificados' => $p['metadata_modified'] ?? null, 'metadatos' => $p['extras'] ?? [], 'recursos' => $rs];
    }
    return ['consultado_en' => gmdate('c'), 'fuente' => TPJ_API, 'reglas' => TPJ_REGLAS,
        'total_conjuntos' => $d['count'] ?? 0, 'pagina' => $page, 'conjuntos' => $out];
}
function tpjResource(string $id): array {
    if (!preg_match('/^[a-f0-9-]{36}$/i', $id)) { throw new InvalidArgumentException('Indique el id de recurso del catálogo.'); }
    return tpjApi('resource_show', ['id' => $id]);
}
function tpjNormalize(string $s): string {
    return strtr(mb_strtolower($s, 'UTF-8'), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);
}
/* CSV conserva columnas y valores originales. Sin promedios inferidos ni sumas de muestras. */
function tpjCsv($body, array $filters, int $offset, int $limit): array {
    $f = is_resource($body) ? $body : fopen('php://temp', 'w+');
    if (!is_resource($body)) { fwrite($f, $body); } rewind($f);
    $utf8 = static function ($v): string { $v = (string)$v; return mb_check_encoding($v, 'UTF-8') ? $v : mb_convert_encoding($v, 'UTF-8', 'Windows-1252'); };
    $first = fgets($f); rewind($f); $delimiter = substr_count((string)$first, ';') >= substr_count((string)$first, ',') ? ';' : ',';
    $header = fgetcsv($f, 0, $delimiter, '"', '');
    if (!$header) { fclose($f); throw new RuntimeException('CSV sin encabezado.'); }
    $header = array_map($utf8, $header);
    $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
    $aliases = ['despacho'=>['DescDespacho'], 'codigo_despacho'=>['CodDespacho'], 'materia'=>['DescMateria'],
        'tramite'=>['Procedimiento','DescClaseAsu','ClaseAsunto'], 'fase'=>['Fase','Etapa'],
        'anno'=>['Año'], 'mes'=>['Numero_Mes','NumeroMes','MesNumero']];
    foreach ($filters as $key => $value) {
        if ((string)$value !== '' && (!isset($aliases[$key]) || !array_intersect($aliases[$key], $header))) {
            fclose($f); throw new InvalidArgumentException('La fuente no tiene columna para el filtro: ' . $key);
        }
    }
    $rows = []; $matches = 0; $scanned = 0;
    while (($values = fgetcsv($f, 0, $delimiter, '"', '')) !== false) {
        if ($values === [null]) { continue; }
        if (count($values) !== count($header)) { fclose($f); throw new RuntimeException('CSV con columnas inconsistentes; no se omiten registros silenciosamente.'); }
        ++$scanned; $row = array_combine($header, array_map($utf8, $values));
        foreach ($filters as $key => $value) {
            if ((string)$value === '') { continue; }
            $hit = false;
            foreach ($aliases[$key] as $column) {
                if (!isset($row[$column])) { continue; }
                $hit = in_array($key, ['codigo_despacho','anno','mes'], true)
                    ? (string)$row[$column] === (string)$value
                    : str_contains(tpjNormalize((string)$row[$column]), tpjNormalize((string)$value));
                if ($hit) { break; }
            }
            if (!$hit) { continue 2; }
        }
        if ($matches++ >= $offset && count($rows) < $limit) { $rows[] = $row; }
    }
    fclose($f);
    return ['columnas'=>$header, 'filas'=>$rows, 'filas_coincidentes'=>$matches, 'filas_examinadas'=>$scanned,
        'desplazamiento'=>$offset, 'siguiente_desplazamiento'=>$offset + count($rows) < $matches ? $offset + count($rows) : null,
        'lectura_completa_recurso'=>true];
}
function tpjRead(string $id, array $filters, int $offset = 0, int $limit = 50): array {
    $r = tpjResource($id); $url = (string)($r['url'] ?? '');
    $base = ['consultado_en'=>gmdate('c'), 'recurso'=>$r, 'reglas'=>TPJ_REGLAS];
    if (strtoupper((string)($r['format'] ?? '')) !== 'CSV') { return $base + ['lectura_completa_recurso'=>false, 'aviso'=>'Seleccione la distribución CSV del mismo conjunto para consultar filas.']; }
    // Archivos de duración pueden superar 180 MB: lectura limitada y fallo explícito, nunca una muestra disfrazada de total.
    if (!tpjAllowed($url)) { throw new InvalidArgumentException('URL de recurso no admitida.'); }
    $f = tmpfile();
    if ($f === false) { throw new RuntimeException('No se pudo crear el temporal privado.'); }
    $bytes = 0; $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_FOLLOWLOCATION=>false, CURLOPT_CONNECTTIMEOUT=>8, CURLOPT_TIMEOUT=>45,
        CURLOPT_USERAGENT=>'Herbie-TransparenciaPJ/1.0', CURLOPT_WRITEFUNCTION=>static function ($ch, string $chunk) use ($f, &$bytes): int {
            $bytes += strlen($chunk); if ($bytes > 268435456) { return 0; } return (int)fwrite($f, $chunk);
        }]);
    $ok = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); unset($ch);
    if ($ok === false || $status !== 200) { fclose($f); throw new RuntimeException('Recurso no disponible, mayor de 256 MB o tiempo de descarga agotado; no se entregan resultados parciales.'); }
    try { return $base + tpjCsv($f, $filters, max(0,$offset), max(1,min(100,$limit))); }
    finally { if (is_resource($f)) { fclose($f); } }
}
function tpjSources(): array {
    return ['reglas'=>TPJ_REGLAS, 'fuentes'=>[
        ['nombre'=>'Estadísticas e indicadores', 'url'=>'https://planificacion.poder-judicial.go.cr/index.php/estadisticas-e-indicadores/estadisticas-e-indicadores-en-linea'],
        ['nombre'=>'Observatorio Judicial', 'url'=>'https://observatoriojudicial.poder-judicial.go.cr/'],
        ['nombre'=>'Normas GICA', 'url'=>'https://gica.poder-judicial.go.cr/index.php/informaciongeneral/familia-normas/normas'],
        ['nombre'=>'Centro de Gestión de Calidad', 'url'=>'https://cegeca.poder-judicial.go.cr/']],
        'alcance'=>'Enlaces de referencia; esta herramienta no acredita sellos, vigencia ni cumplimiento de despachos. Use transparencia_pj_buscar para localizar datos y diccionarios oficiales.'];
}
