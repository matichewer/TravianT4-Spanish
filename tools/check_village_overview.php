<?php
/**
 * Pestaña Resumen del resumen de aldeas (dorf3.php, Templates/dorf3/1.tpl).
 *
 * Cubre las dos columnas de iconos, que mostraban uno solo por aldea porque sus foreach
 * asignaban en vez de acumular:
 *
 *   A. Construcción: una pala por obra EN CURSO. Con romano (+1 sobre la básica) y Plus
 *      (+1 más) una aldea puede tener tres a la vez y se veía una. Peor: como getJobs()
 *      ordena por `master`, la que sobrevivía al foreach era la ENCOLADA en el maestro de
 *      obras, o sea un edificio que todavía no se estaba construyendo.
 *   B. Tropas: un icono por TIPO de unidad. Cuartel, establo y taller entrenan en
 *      paralelo y se veía uno solo; y varias tandas del mismo tipo son un tipo, no varias.
 *   C. Las suposiciones que la plantilla hace sobre getJobs()/getTraining() siguen siendo
 *      ciertas: si alguien cambia el orden o el filtrado, esto lo avisa.
 *   D. Las CINCO pestañas listan las aldeas en el mismo orden, el de fundación, que es el
 *      del cartel lateral. Cada una llamaba a getProfileVillages() por su cuenta (población
 *      descendente) y la misma lista salía distinta según dónde se mirara.
 *
 * Ejecutar: docker compose exec -T web php /var/www/html/tools/check_village_overview.php
 */

if(PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);
date_default_timezone_set('America/Argentina/Buenos_Aires');
set_include_path($root.PATH_SEPARATOR.$root.'/GameEngine');
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SESSION = array();
include "config/connection.php";
include "config/config.php";
include "Database.php";

global $database;

$failures = array();
function check($ok, $message) {
    global $failures;
    echo ($ok ? '[OK] ' : '[FALLA] ').$message.PHP_EOL;
    if(!$ok) {
        $failures[] = $message;
    }
}
function section($title) {
    echo PHP_EOL.'== '.$title.' =='.PHP_EOL;
}

// Aldea fuera del mapa, para no tocar nada del mundo vivo.
$V = 990101;
function cleanScratch() {
    global $database, $V;
    $database->query("DELETE FROM ".TB_PREFIX."bdata WHERE wid = ".(int)$V);
    $database->query("DELETE FROM ".TB_PREFIX."training WHERE vref = ".(int)$V);
}
register_shutdown_function('cleanScratch');
cleanScratch();

$tpl = file_get_contents($root.'/Templates/dorf3/1.tpl');

// ---------------------------------------------------------------------------
section('A. Construcción: una pala por obra en curso');
// ---------------------------------------------------------------------------

$now = time();
// Dos obras en curso y una encolada en el maestro de obras.
$database->query("INSERT INTO ".TB_PREFIX."bdata (wid,field,type,loopcon,timestamp,master,level) VALUES"
    ." ($V, 1, 1,0,".($now+1800).",0,6),"
    ." ($V,19,15,0,".($now+3600).",0,3),"
    ." ($V,20,10,0,".($now+7200).",1,8)");

$jobs = $database->getJobs($V);
check(count($jobs) === 3, 'getJobs() devuelve las obras en curso y las encoladas (dio '.count($jobs).')');

$inProgress = 0;
foreach($jobs as $job) {
    if((int)$job['master'] === 0) {
        $inProgress++;
    }
}
check($inProgress === 2, 'dos de las tres están en curso: `master` es lo que las distingue');
check((int)$jobs[count($jobs)-1]['master'] === 1,
    'la encolada va última (ORDER BY master): por eso el foreach que asignaba dejaba a la vista un edificio que ni se estaba construyendo');

check(strpos($tpl,'$buiIcons[] =') !== false && strpos($tpl,"implode('',\$buiIcons)") !== false,
    'la plantilla acumula las palas en vez de pisarlas');
check(preg_match('/foreach\(\$jobs as \$b\)\{\s*\$bui = /',$tpl) !== 1,
    'el foreach de obras ya no asigna dentro del bucle');
check(strpos($tpl,"if((int)\$b['master'] === 1) {") !== false,
    'y deja fuera la cola del maestro de obras, que todavía no se construye');

// ---------------------------------------------------------------------------
section('B. Tropas: un icono por tipo de unidad');
// ---------------------------------------------------------------------------

// Dos tandas de legionarios en el cuartel, una de caballería y una de arietes: cuatro
// filas en `training`, tres tipos.
$database->query("INSERT INTO ".TB_PREFIX."training (vref,unit,amt,pop,timestamp,eachtime,timestamp2) VALUES"
    ." ($V,1,10,0,".($now+600).",60,$now),"
    ." ($V,1, 5,0,".($now+900).",60,$now),"
    ." ($V,4, 3,0,".($now+900).",90,$now),"
    ." ($V,7, 2,0,".($now+1200).",120,$now)");

$training = $database->getTraining($V);
check(count($training) === 4, 'getTraining() devuelve una fila por tanda, no por tipo (dio '.count($training).')');

// El agrupado que hace la plantilla, replicado acá para fijar el resultado esperado.
$byUnit = array();
foreach($training as $batch) {
    $unitId = (int)$batch['unit'];
    $byUnit[$unitId] = (isset($byUnit[$unitId]) ? $byUnit[$unitId] : 0) + max(0,(int)$batch['amt']);
}
check(count($byUnit) === 3, 'agrupadas por tipo quedan tres iconos, no cuatro');
check($byUnit[1] === 15, 'las dos tandas de legionarios se suman en el tooltip: 10 + 5 (dio '.$byUnit[1].')');

check(strpos($tpl,'$troAmounts[$unitId] =') !== false && strpos($tpl,"implode('',\$troIcons)") !== false,
    'la plantilla agrupa por tipo y acumula los iconos');
check(preg_match('/foreach\(\$unit as \$c\)\{\s*\$tro = /',$tpl) !== 1,
    'el foreach de entrenamiento ya no asigna dentro del bucle');

// ---------------------------------------------------------------------------
section('C. Nada de esto imprime texto de jugador sin escapar');
// ---------------------------------------------------------------------------

check(substr_count($tpl,"ENT_QUOTES,'UTF-8'") >= 2,
    'los title de las dos columnas se escapan antes de entrar al atributo');
// El nombre de la aldea es la excepción documentada: `vdata.name` se guarda ya escapado
// (Profile.php lo pasa por RemoveXSS) y volver a escaparlo convertiría cada &amp; en
// &amp;amp;. Ver la nota en GameEngine/TroopOverview.php.
check(strpos($tpl,"htmlspecialchars(\$vdata['name']") === false,
    'el nombre de la aldea no se re-escapa: ya viene escapado de la base');

// ---------------------------------------------------------------------------
section('D. Las cinco pestañas comparten el orden de fundación');
// ---------------------------------------------------------------------------

foreach(array('1' => 'Resumen', '2' => 'Recursos', '3' => 'Almacén', '4' => 'Puntos de cultura',
              '5' => 'Tropas', 'noplus' => 'Resumen sin Plus') as $file => $name) {
    $source = file_get_contents($root.'/Templates/dorf3/'.$file.'.tpl');
    check(strpos($source,'villageOverviewVillages($session->uid)') !== false
        && strpos($source,'$database->getProfileVillages($session->uid)') === false,
        'la pestaña '.$name.' toma las aldeas del helper compartido, ya en orden de fundación');
}

check(strpos(file_get_contents($root.'/GameEngine/Database/db_MYSQLi.php'),
    'where owner = $uid order by pop desc') !== false,
    'getProfileVillages() sigue ordenando por población: la usan una treintena de lugares donde ese orden es el que corresponde');
check(strpos(file_get_contents($root.'/Templates/multivillage.tpl'),'getVillagesIDByFoundation(') !== false,
    'el cartel lateral sigue usando el orden de fundación, que es con el que se comparan');

// Lo único que importa de verdad del reordenamiento: ninguna aldea se pierde por el camino.
$rows = array(50 => 'grande', 10 => 'chica', 30 => 'mediana');
check(villageOverviewFoundationOrder($rows, array(10,30,50)) === array(10,30,50),
    'salen en el orden de fundación, no en el de población');
check(villageOverviewFoundationOrder($rows, array(30)) === array(30,50,10),
    'una aldea que falta en la lista de fundación se agrega al final, no desaparece');
check(villageOverviewFoundationOrder($rows, array()) === array(50,10,30),
    'si la consulta de fundación viene vacía se conserva el orden original');
check(villageOverviewFoundationOrder($rows, array(10,10,30,999)) === array(10,30,50),
    'ids repetidos o inexistentes no duplican ni inventan filas');
check(count(villageOverviewFoundationOrder($rows, array(10,30,50))) === count($rows),
    'el orden nunca cambia la cantidad de aldeas');

echo PHP_EOL;
if(empty($failures)) {
    echo 'TODO OK'.PHP_EOL;
    exit(0);
}
echo count($failures).' fallo(s)'.PHP_EOL;
exit(1);
