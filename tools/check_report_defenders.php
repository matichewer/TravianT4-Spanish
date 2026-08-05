<?php
// El informe de batalla del defensor tiene que distinguir de quién es cada refuerzo.
// Antes los defensores se guardaban agregados por tribu, así que dos aliados de la misma
// tribu caían en un solo bloque sumado y el bloque decía solo "Refuerzo", sin nombre.
// Acá se comprueba el ida y vuelta completo: codificación, parseo y render, y que el
// atacante siga viendo el desglose por tribu de siempre.

function reportDefendersAssert($condition, $message) {
	if(!$condition) {
		echo "FAIL: ".$message."\n";
		exit(1);
	}
	echo "OK: ".$message."\n";
}

$root = dirname(__DIR__);
chdir($root);

if(!defined('REPORT_DEFENDER')) { define('REPORT_DEFENDER', 'Defensor'); }
if(!defined('REPORT_REINF')) { define('REPORT_REINF', 'Refuerzo'); }
if(!defined('REPORT_FROM_VIL')) { define('REPORT_FROM_VIL', 'de la aldea'); }
if(!defined('REPORT_TROOPS')) { define('REPORT_TROOPS', 'Tropas'); }
if(!defined('REPORT_CASUALTIES')) { define('REPORT_CASUALTIES', 'Bajas'); }
if(!defined('U0')) { define('U0', 'Héroe'); }

class ReportDefendersDatabase {
	public $usernames = array();
	public $villages = array();

	public function getUserField($uid, $field, $mode) {
		if($field === 'username') {
			return isset($this->usernames[(int)$uid]) ? $this->usernames[(int)$uid] : '';
		}
		return (int)$uid;
	}
	public function getVillageField($wref, $field) {
		if($field === 'name') {
			return isset($this->villages[(int)$wref]) ? $this->villages[(int)$wref] : '';
		}
		return 0;
	}
}

class ReportDefendersGenerator {
	public function getMapCheck($wref) {
		return 'check';
	}
}

class ReportDefendersTechnology {
	public function getUnitName($unit) {
		return 'u'.(int)$unit;
	}
}

$OWNER_UID = 7;
$OWNER_WREF = 100;
$ALLY_UID = 9;
$ALLY_WREF = 200;
$ATTACKER_UID = 99;

$database = new ReportDefendersDatabase;
$database->usernames = array($OWNER_UID => 'DuenoDeLaAldea', $ALLY_UID => 'AliadoQueReforzo');
$database->villages = array($OWNER_WREF => 'Fortaleza', $ALLY_WREF => 'AldeaAliada');
$generator = new ReportDefendersGenerator;
$technology = new ReportDefendersTechnology;

// ---------------------------------------------------------------- codificación
// El constructor de Automation corre la limpieza de cuentas inactivas, así que se evita
// el bootstrap del final del archivo y se instancia sin constructor.
if(!defined('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP')) {
	define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
}
require_once $root.'/GameEngine/Automation.php';
$automationReflection = new ReflectionClass('Automation');
$automation = $automationReflection->newInstanceWithoutConstructor();
$newDefenderParty = new ReflectionMethod('Automation', 'newDefenderParty');
$newDefenderParty->setAccessible(true);
$encodeDefenderParties = new ReflectionMethod('Automation', 'encodeDefenderParties');
$encodeDefenderParties->setAccessible(true);

// Dueño teutón: 27 lanceros, de los que mueren 27, y su héroe, que sobrevive.
$ownerParty = $newDefenderParty->invoke($automation, $OWNER_UID, $OWNER_WREF, 2);
$ownerParty['sent'][2] = 27;
$ownerParty['dead'][2] = 27;
$ownerParty['sent'][11] = 1;
// Aliado romano: 10 pretorianos, muere 1.
$allyParty = $newDefenderParty->invoke($automation, $ALLY_UID, $ALLY_WREF, 1);
$allyParty['sent'][2] = 10;
$allyParty['dead'][2] = 1;
// Bando vacío: no tiene que ocupar un bloque en el informe.
$emptyParty = $newDefenderParty->invoke($automation, 12, 300, 3);

$payload = $encodeDefenderParties->invoke($automation, array($ownerParty, $allyParty, $emptyParty));

reportDefendersAssert(
	strpos($payload, ',') === false,
	'el desglose no lleva comas, así que entra en un solo campo del informe CSV'
);
reportDefendersAssert(
	count(explode('|', $payload)) === 2,
	'los bandos sin tropas no ocupan un bloque'
);
reportDefendersAssert(
	count(explode(';', explode('|', $payload)[0])) === 25,
	'cada bando guarda jugador, aldea, tribu y sus 11 enviadas + 11 bajas'
);

// ---------------------------------------------------------------------- parseo
function reportDefendersCsv($ownerUid, $ownerWref, $payload) {
	$fields = array_fill(0, 160, '');
	$fields[30] = $ownerUid;
	$fields[31] = $ownerWref;
	$fields[32] = 'Fortaleza';
	$fields[33] = 2;
	// Bloque agregado por tribu, el de siempre: tribu 1 presente como refuerzo.
	$fields[36] = 1;
	$fields[38] = 10;
	$fields[49] = 1;
	$fields[59] = 1;
	$fields[61] = 27;
	$fields[72] = 27;
	$fields[159] = 'trap-data-v1';
	$csv = implode(',', $fields);
	if($payload !== '') {
		$csv .= ',defenders-v1,'.$payload;
	}

	return $csv;
}

function reportDefendersParse($csv, $viewerUid) {
	global $database, $generator, $technology;
	$session = new stdClass;
	$session->uid = $viewerUid;
	$message = new stdClass;
	$message->readingNotice = array('data' => $csv, 'topic' => 'x', 'time' => 0);
	include dirname(__DIR__).'/Templates/Notice/report_data.tpl';

	return array(
		'dataarray' => $dataarray,
		'parties' => $reportDefenderParties,
		'viewerIsDefender' => $reportViewerIsDefender
	);
}

$parsed = reportDefendersParse(reportDefendersCsv($OWNER_UID, $OWNER_WREF, $payload), $OWNER_UID);

reportDefendersAssert(
	count($parsed['parties']) === 2,
	'el informe se vuelve a leer con los dos bandos que defendieron'
);
reportDefendersAssert(
	$parsed['parties'][0]['uid'] === $OWNER_UID
		&& $parsed['parties'][0]['wref'] === $OWNER_WREF
		&& $parsed['parties'][0]['tribe'] === 2
		&& $parsed['parties'][0]['sent'][2] === 27
		&& $parsed['parties'][0]['dead'][2] === 27
		&& $parsed['parties'][0]['sent'][11] === 1
		&& $parsed['parties'][0]['dead'][11] === 0,
	'el bando del dueño conserva jugador, aldea, tribu, tropas y bajas'
);
reportDefendersAssert(
	$parsed['parties'][1]['uid'] === $ALLY_UID && $parsed['parties'][1]['sent'][2] === 10,
	'el refuerzo conserva su propio jugador y sus propias tropas'
);
reportDefendersAssert(
	$parsed['viewerIsDefender'] === true,
	'el dueño de la aldea se reconoce como defensor'
);

$parsedAttacker = reportDefendersParse(reportDefendersCsv($OWNER_UID, $OWNER_WREF, $payload), $ATTACKER_UID);
reportDefendersAssert(
	$parsedAttacker['viewerIsDefender'] === false,
	'el atacante no se reconoce como defensor'
);

$parsedOld = reportDefendersParse(reportDefendersCsv($OWNER_UID, $OWNER_WREF, ''), $OWNER_UID);
reportDefendersAssert(
	$parsedOld['parties'] === array() && $parsedOld['viewerIsDefender'] === false,
	'un informe viejo sin el marcador se lee sin desglose y sin romperse'
);

// ---------------------------------------------------------------------- render
function reportDefendersRender($parsed) {
	global $database, $generator, $technology;
	$dataarray = $parsed['dataarray'];
	$reportDefenderParties = $parsed['parties'];
	$reportViewerIsDefender = $parsed['viewerIsDefender'];
	$errors = array();
	set_error_handler(function($no, $str, $file, $line) use (&$errors) {
		$errors[] = $str.' ('.$file.':'.$line.')';
		return true;
	});
	ob_start();
	include dirname(__DIR__).'/Templates/Notice/defenders.tpl';
	$html = ob_get_clean();
	restore_error_handler();

	return array('html' => $html, 'errors' => $errors);
}

$rendered = reportDefendersRender($parsed);
reportDefendersAssert(
	$rendered['errors'] === array(),
	'el bloque de defensores se dibuja sin avisos de PHP: '.implode(' | ', $rendered['errors'])
);
reportDefendersAssert(
	strpos($rendered['html'], 'DuenoDeLaAldea') !== false
		&& strpos($rendered['html'], 'AliadoQueReforzo') !== false,
	'el defensor ve el nombre del dueño y el del que mandó el refuerzo'
);
reportDefendersAssert(
	strpos($rendered['html'], 'AldeaAliada') !== false,
	'el refuerzo dice de qué aldea vino'
);
reportDefendersAssert(
	substr_count($rendered['html'], REPORT_REINF.': ') === 1,
	'solo el bloque del refuerzo lleva la etiqueta de refuerzo, no el del dueño'
);
reportDefendersAssert(
	substr_count($rendered['html'], '<table') === 2,
	'se dibuja un bloque por bando defensor'
);

$renderedAttacker = reportDefendersRender($parsedAttacker);
reportDefendersAssert(
	strpos($renderedAttacker['html'], 'AliadoQueReforzo') === false,
	'el atacante no ve quién mandó el refuerzo'
);
reportDefendersAssert(
	strpos($renderedAttacker['html'], REPORT_REINF) !== false,
	'el atacante sigue viendo los bloques agrupados por tribu'
);

$renderedOld = reportDefendersRender($parsedOld);
reportDefendersAssert(
	strpos($renderedOld['html'], REPORT_REINF) !== false,
	'un informe viejo se sigue dibujando con el desglose por tribu'
);

echo "check_report_defenders: todo OK\n";
