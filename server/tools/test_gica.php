<?php
declare(strict_types=1);
function authConfig(): array { return []; }
require dirname(__DIR__).'/gica_lib.php';
function check(bool $condition,string $name): void { if (!$condition) { throw new RuntimeException($name); } }
$fixture='<html><body><nav>ruido</nav><main id="sp-main-body"><h1>Oficinas</h1><p>En proceso de actualización</p><img src="/images/acreditaciones.png"><script>instrucciones ajenas</script><a href="https://evil.test/a.pdf">externo</a><a href="/images/norma.pdf">Norma</a></main></body></html>';
$r=gicaHtml($fixture,'https://cegeca.poder-judicial.go.cr/index.php/a');
check($r['en_actualizacion']===true,'Aviso de actualización');check(count($r['documentos'])===2,'Original visual y PDF');check(!str_contains($r['texto'],'instrucciones ajenas')&&!str_contains($r['texto'],'ruido'),'Extracción de contenido');
foreach(['http://cegeca.poder-judicial.go.cr/images/a.pdf','https://evil.test/images/a.pdf','https://cegeca.poder-judicial.go.cr:444/images/a.pdf','https://u@cegeca.poder-judicial.go.cr/images/a.pdf'] as $url) { check(!gicaAllowed($url),'Host o credenciales no admitidos'); }
try { gicaRead('no-existe');throw new LogicException('ID ignorado'); } catch(InvalidArgumentException $e) {}
check(count(gicaCatalog()['fuentes'])===7,'Catálogo');echo "Pruebas GICA offline: OK\n";
if(in_array('--live',$argv,true)) {
 $r=gicaRead('normas');check($r['en_actualizacion']===true,'Aviso en fuente real');
 $r=gicaRead('acreditaciones');check(count(array_filter($r['documentos'],fn($d)=>str_contains($d['url'],'acreditaciones.png')))===1,'Original de oficinas real');
 $r=gicaRead('oficinas_imagen');check($r['requiere_lectura_visual']===true&&$r['texto']==='','Sin OCR ni acreditación inferida');
 $r=gicaSearch('actualizacion','normas');check(count($r['resultados'])===1,'Búsqueda en fuente real');
 echo "Páginas, búsqueda y original visual oficiales: OK\n";
}
