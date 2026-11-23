<?php
declare(strict_types=1);
function authConfig(): array { return []; }
require dirname(__DIR__) . '/transparencia_pj_lib.php';
function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
$csv = "CodDespacho;DescDespacho;DescMateria;Año;NumeroMes;Procedimiento;Cant;DurDias\n0042;JUZGADO SAN JOSÉ;Civil;2026;9;ORDINARIO;2;150\n0042;JUZGADO SAN JOSÉ;Civil;2026;9;ORDINARIO;1;20\n0043;OTRO;Penal;2026;8;ABREVIADO;1;300\n";
$r = tpjCsv(mb_convert_encoding($csv,'Windows-1252','UTF-8'), ['despacho'=>'san jose','codigo_despacho'=>'0042','mes'=>'9'],0,1);
check($r['filas_coincidentes']===2 && count($r['filas'])===1 && $r['siguiente_desplazamiento']===1,'Filtro y paginación');
check($r['filas'][0]['CodDespacho']==='0042' && $r['filas'][0]['DurDias']==='150','Valores originales');
check($r['columnas'][3]==='Año','Codificación');
$r = tpjCsv($csv,['tramite'=>'ordinario','materia'=>'civil'],1,1);
check($r['filas'][0]['DurDias']==='20' && $r['siguiente_desplazamiento']===null,'Segunda página');
$r = tpjCsv($csv,['despacho'=>'inexistente'],0,5); check($r['filas_coincidentes']===0,'Ausencia no es cero expedientes');
try { tpjCsv($csv,['fase'=>'inicial'],0,5); throw new LogicException('Filtro ignorado'); } catch (InvalidArgumentException $e) {}
try { tpjCsv("A;B\n1;2;3\n",[],0,5); throw new LogicException('CSV inválido aceptado'); } catch (RuntimeException $e) { check(!($e instanceof LogicException),'Malformed'); }
foreach (['http://datosabiertospj.poder-judicial.go.cr/a','https://evil.test/a','https://datosabiertospj.poder-judicial.go.cr@evil.test/a','https://datosabiertospj.poder-judicial.go.cr:444/a'] as $u) { check(!tpjAllowed($u),'Host no admitido'); }
check(tpjAllowed('https://pjcrdatosabiertos.blob.core.windows.net/a'),'Blob oficial');
echo "Pruebas de filtros, codificación, paginación, integridad y hosts: OK\n";
if (in_array('--live',$argv,true)) {
 $r=tpjSearch('duración',1,3); check(count($r['conjuntos'])>0,'Catálogo en vivo');
 $r=tpjRead('206f8a28-4abd-4144-a828-4533c9118357',['codigo_despacho'=>'0485','mes'=>'3'],0,2);
 check(count($r['filas'])===2 && $r['lectura_completa_recurso']===true,'CSV oficial en vivo');
 echo json_encode(['filas_coincidentes'=>$r['filas_coincidentes'],'primera_fila'=>$r['filas'][0]], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),"\n";
}
