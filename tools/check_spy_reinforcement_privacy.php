<?php
// El informe de espionaje muestra cuánta defensa ajena hay en la aldea espiada, pero no
// de quién es: los refuerzos se suman por tribu y no dicen jugador ni aldea de origen.
// Acá se comprueba el ida y vuelta: lo que guarda Automation, la suma por tribu y el
// render final, incluidos los animales enjaulados (refuerzo de la naturaleza) y las
// tropas propias de la aldea, que sí se siguen viendo con nombre.

function spyPrivacyAssert($condition, $message) {
	if(!$condition) {
		echo "FAIL: ".$message."\n";
		exit(1);
	}
	echo "OK: ".$message."\n";
}

$root = dirname(__DIR__);
chdir($root);

$spyPrivacyConstants = array(
	'REPORT_SUBJECT' => 'Asunto',
	'REPORT_SENT' => 'Enviado',
	'REPORT_AT' => 'a las',
	'REPORT_ATTACKER' => 'Atacante',
	'REPORT_DEFENDER' => 'Defensor',
	'REPORT_REINF' => 'Refuerzo',
	'REPORT_NATURE_REINF' => 'Naturaleza (animales capturados)',
	'REPORT_FROM_VIL' => 'de la aldea',
	'REPORT_TROOPS' => 'Tropas',
	'REPORT_CASUALTIES' => 'Bajas',
	'REPORT_PRISONERS' => 'Prisioneros',
	'REPORT_INFORMATION' => 'informacion',
	'REPORT_BOUNTY' => 'Recursos',
	'REPORT_DEL_BTN' => 'Borrar',
	'REPORT_DEL_QST' => 'Borrar?',
	'REPORT_WARSIM' => 'Simulador',
	'REPORT_ATK_AGAIN' => 'Atacar de nuevo',
	'WOOD' => 'Madera',
	'CLAY' => 'Barro',
	'IRON' => 'Hierro',
	'CROP' => 'Cereal',
	'U0' => 'Heroe',
	'TRIBE1' => 'Romanos',
	'TRIBE2' => 'Germanos',
	'TRIBE3' => 'Galos',
	'TRIBE4' => 'Naturaleza',
	'TRIBE5' => 'Natares'
);
foreach($spyPrivacyConstants as $spyPrivacyName => $spyPrivacyValue) {
	if(!defined($spyPrivacyName)) {
		define($spyPrivacyName, $spyPrivacyValue);
	}
}

class SpyPrivacyDatabase {
	public $usernames = array();
	public $villages = array();
	public $owners = array();
	public $tribes = array();

	public function getUserField($uid, $field, $mode) {
		if($field === 'username') {
			return isset($this->usernames[(int)$uid]) ? $this->usernames[(int)$uid] : '';
		}
		if($field === 'tribe') {
			return isset($this->tribes[(int)$uid]) ? $this->tribes[(int)$uid] : 0;
		}
		return (int)$uid;
	}
	public function getVillageField($wref, $field) {
		if($field === 'name') {
			return isset($this->villages[(int)$wref]) ? $this->villages[(int)$wref] : '';
		}
		if($field === 'owner') {
			return isset($this->owners[(int)$wref]) ? $this->owners[(int)$wref] : 0;
		}
		return 0;
	}
}

class SpyPrivacyGenerator {
	public function getMapCheck($wref) {
		return 'check';
	}
	public function procMtime($time) {
		return array('01.01.26', '00:00:00');
	}
}

class SpyPrivacyTechnology {
	public $unarray = array();
	public function getUnitName($unit) {
		return 'u'.(int)$unit;
	}
}

$ATTACKER_UID = 3;
$ATTACKER_WREF = 10;
$DEFENDER_UID = 7;
$DEFENDER_WREF = 100;
$ROMAN_ALLY_UID = 21;
$ROMAN_ALLY_WREF = 210;
$OTHER_ROMAN_ALLY_UID = 22;
$OTHER_ROMAN_ALLY_WREF = 220;
$TEUTON_ALLY_UID = 23;
$TEUTON_ALLY_WREF = 230;

$database = new SpyPrivacyDatabase;
$database->usernames = array(
	$ATTACKER_UID => 'ElEspia',
	$DEFENDER_UID => 'ElEspiado',
	$ROMAN_ALLY_UID => 'AliadoRomanoUno',
	$OTHER_ROMAN_ALLY_UID => 'AliadoRomanoDos',
	$TEUTON_ALLY_UID => 'AliadoGermano'
);
$database->villages = array(
	$ATTACKER_WREF => 'AldeaEspia',
	$DEFENDER_WREF => 'AldeaEspiada',
	$ROMAN_ALLY_WREF => 'AldeaRomanaUno',
	$OTHER_ROMAN_ALLY_WREF => 'AldeaRomanaDos',
	$TEUTON_ALLY_WREF => 'AldeaGermana'
);
$database->owners = array(
	$ROMAN_ALLY_WREF => $ROMAN_ALLY_UID,
	$OTHER_ROMAN_ALLY_WREF => $OTHER_ROMAN_ALLY_UID,
	$TEUTON_ALLY_WREF => $TEUTON_ALLY_UID
);
$database->tribes = array(
	$ROMAN_ALLY_UID => 1,
	$OTHER_ROMAN_ALLY_UID => 1,
	$TEUTON_ALLY_UID => 2
);
$generator = new SpyPrivacyGenerator;
$technology = new SpyPrivacyTechnology;

// ------------------------------------------------------------ lo que se guarda
// El constructor de Automation corre la limpieza de cuentas inactivas, así que se evita
// el bootstrap del final del archivo y se instancia sin constructor.
if(!defined('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP')) {
	define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
}
require_once $root.'/GameEngine/Automation.php';
$automation = (new ReflectionClass('Automation'))->newInstanceWithoutConstructor();
$buildSnapshot = new ReflectionMethod('Automation', 'buildSpyReinforcementSnapshot');
$buildSnapshot->setAccessible(true);

function spyPrivacyEnforcement($from, $units, $hero = 0) {
	$enforcement = array('from' => $from, 'hero' => $hero);
	for($unit = 1; $unit <= 50; $unit++) {
		$enforcement['u'.$unit] = isset($units[$unit]) ? $units[$unit] : 0;
	}

	return $enforcement;
}

$romanReinforcement = $buildSnapshot->invoke(
	$automation,
	spyPrivacyEnforcement($ROMAN_ALLY_WREF, array(1 => 10), 1)
);
$otherRomanReinforcement = $buildSnapshot->invoke(
	$automation,
	spyPrivacyEnforcement($OTHER_ROMAN_ALLY_WREF, array(1 => 7, 3 => 2), 1)
);
$teutonReinforcement = $buildSnapshot->invoke(
	$automation,
	spyPrivacyEnforcement($TEUTON_ALLY_WREF, array(11 => 3))
);
// Los animales enjaulados defienden como un refuerzo sin aldea de origen (`from = 0`).
$natureReinforcement = $buildSnapshot->invoke(
	$automation,
	spyPrivacyEnforcement(0, array(31 => 4))
);

spyPrivacyAssert(
	$romanReinforcement !== null && $teutonReinforcement !== null && $natureReinforcement !== null,
	'cada refuerzo con tropas deja su registro en el informe'
);
foreach(array($romanReinforcement, $teutonReinforcement, $natureReinforcement) as $storedReinforcement) {
	spyPrivacyAssert(
		!isset($storedReinforcement['from']) && !isset($storedReinforcement['owner']),
		'el informe no guarda la aldea ni el jugador que mandó el refuerzo'
	);
}
spyPrivacyAssert(
	(int)$romanReinforcement['tribe'] === 1 && (int)$teutonReinforcement['tribe'] === 2,
	'cada refuerzo guarda la tribu de quien lo mandó'
);
spyPrivacyAssert(
	(int)$natureReinforcement['tribe'] === 4 && !empty($natureReinforcement['nature']),
	'los animales enjaulados quedan como refuerzo de la naturaleza'
);
spyPrivacyAssert(
	$buildSnapshot->invoke($automation, spyPrivacyEnforcement($ROMAN_ALLY_WREF, array())) === null,
	'un refuerzo sin tropas no ocupa un bloque'
);

$spyReinforcementPayload = base64_encode(json_encode(array(
	$romanReinforcement,
	$otherRomanReinforcement,
	$teutonReinforcement,
	$natureReinforcement
)));
spyPrivacyAssert(
	strpos(base64_decode($spyReinforcementPayload), 'AliadoRomanoUno') === false,
	'el dato crudo del informe no lleva el nombre de ningún aliado'
);

// ----------------------------------------------------------------- el informe
// La aldea espiada es gala: 5 falanges propias, y encima los refuerzos de arriba.
// El informe guarda la defensa total por tribu, que es lo que el render tiene que
// repartir entre las tropas propias y los bloques de refuerzo.
$reportFields = array_fill(0, 159, '');
$reportFields[0] = $ATTACKER_UID;
$reportFields[1] = $ATTACKER_WREF;
$reportFields[2] = 1;
for($reportIndex = 3; $reportIndex <= 29; $reportIndex++) {
	$reportFields[$reportIndex] = 0;
}
$reportFields[6] = 30; // 30 exploradores romanos enviados
$reportFields[30] = $DEFENDER_UID;
$reportFields[31] = $DEFENDER_WREF;
$reportFields[32] = 'AldeaEspiada';
$reportFields[33] = 3;
for($reportIndex = 36; $reportIndex <= 150; $reportIndex++) {
	$reportFields[$reportIndex] = 0;
}
// Romanos: 10 + 7 legionarios, 2 equites imperatoris y 2 héroes, todo de refuerzo.
$reportFields[36] = 1;
$reportFields[37] = 17;
$reportFields[39] = 2;
$reportFields[47] = 2;
// Germanos: 3 macearos de refuerzo.
$reportFields[59] = 1;
$reportFields[60] = 3;
// Galos: las 5 falanges propias de la aldea espiada.
$reportFields[82] = 1;
$reportFields[83] = 5;
// Naturaleza: 4 ratas enjauladas.
$reportFields[105] = 1;
$reportFields[106] = 4;
$reportFields[157] = 'spy-picture';
$reportFields[158] = 'La aldea tiene 0 de tesoros';
$reportCsv = implode(',', $reportFields).',trap-data-v1,0,0,0,0,0,0,0,0,0,0,0,spyref:'.$spyReinforcementPayload;

function spyPrivacyRender($reportCsv) {
	global $database, $generator, $technology;
	$_GET['id'] = 1;
	$session = new stdClass;
	$session->uid = 3;
	$session->plus = 0;
	$message = new stdClass;
	$message->readingNotice = array('data' => $reportCsv, 'topic' => 'espionaje', 'time' => 0);
	$errors = array();
	set_error_handler(function($no, $str, $file, $line) use (&$errors) {
		$errors[] = $str.' ('.$file.':'.$line.')';
		return true;
	});
	ob_start();
	include dirname(__DIR__).'/Templates/Notice/0.tpl';
	$html = ob_get_clean();
	restore_error_handler();

	return array('html' => $html, 'errors' => $errors);
}

$rendered = spyPrivacyRender($reportCsv);
spyPrivacyAssert(
	$rendered['errors'] === array(),
	'el informe de espionaje se dibuja sin avisos de PHP: '.implode(' | ', $rendered['errors'])
);

foreach(array('AliadoRomanoUno', 'AliadoRomanoDos', 'AliadoGermano') as $hiddenName) {
	spyPrivacyAssert(
		strpos($rendered['html'], $hiddenName) === false,
		'el espía no ve el nombre de quien mandó el refuerzo ('.$hiddenName.')'
	);
}
foreach(array('AldeaRomanaUno', 'AldeaRomanaDos', 'AldeaGermana') as $hiddenVillage) {
	spyPrivacyAssert(
		strpos($rendered['html'], $hiddenVillage) === false,
		'el espía no ve de qué aldea vino el refuerzo ('.$hiddenVillage.')'
	);
}
foreach(array($ROMAN_ALLY_UID, $OTHER_ROMAN_ALLY_UID, $TEUTON_ALLY_UID) as $hiddenUid) {
	spyPrivacyAssert(
		strpos($rendered['html'], 'spieler.php?uid='.$hiddenUid) === false,
		'el informe no enlaza al perfil de quien mandó el refuerzo (uid '.$hiddenUid.')'
	);
}
foreach(array($ROMAN_ALLY_WREF, $OTHER_ROMAN_ALLY_WREF, $TEUTON_ALLY_WREF) as $hiddenWref) {
	spyPrivacyAssert(
		strpos($rendered['html'], 'karte.php?d='.$hiddenWref) === false,
		'el informe no enlaza a la aldea que mandó el refuerzo ('.$hiddenWref.')'
	);
}

spyPrivacyAssert(
	substr_count($rendered['html'], '>'.TRIBE1.'<') === 1
		&& substr_count($rendered['html'], '>'.TRIBE2.'<') === 1
		&& substr_count($rendered['html'], '>'.REPORT_NATURE_REINF.'<') === 1,
	'hay un solo bloque de refuerzo por tribu, más el de la naturaleza'
);
spyPrivacyAssert(
	strpos($rendered['html'], '>'.TRIBE3.'<') === false,
	'sin refuerzos galos no aparece un bloque galo vacío'
);

function spyPrivacyBlock($html, $label) {
	$blocks = explode('<table', $html);
	foreach($blocks as $block) {
		if(strpos($block, '>'.$label.'<') !== false) {
			return $block;
		}
	}

	return '';
}

function spyPrivacyAmounts($block) {
	preg_match_all('#<td class="unit[^"]*">(\d+)</td>#', $block, $matches);

	return array_map('intval', $matches[1]);
}

$romanBlock = spyPrivacyBlock($rendered['html'], TRIBE1);
$teutonBlock = spyPrivacyBlock($rendered['html'], TRIBE2);
$natureBlock = spyPrivacyBlock($rendered['html'], REPORT_NATURE_REINF);
$defenderBlock = spyPrivacyBlock($rendered['html'], REPORT_DEFENDER);

spyPrivacyAssert(
	spyPrivacyAmounts($romanBlock) === array(17, 0, 2, 0, 0, 0, 0, 0, 0, 0, 2),
	'el bloque romano suma los refuerzos de los dos aliados romanos, héroes incluidos'
);
spyPrivacyAssert(
	spyPrivacyAmounts($teutonBlock) === array(3, 0, 0, 0, 0, 0, 0, 0, 0, 0),
	'el bloque germano muestra el total de refuerzos germanos'
);
spyPrivacyAssert(
	spyPrivacyAmounts($natureBlock) === array(4, 0, 0, 0, 0, 0, 0, 0, 0, 0),
	'los animales capturados aparecen como refuerzo de la naturaleza'
);
spyPrivacyAssert(
	strpos($defenderBlock, 'ElEspiado') !== false && strpos($defenderBlock, 'AldeaEspiada') !== false,
	'la aldea espiada sigue mostrando a su dueño'
);
spyPrivacyAssert(
	spyPrivacyAmounts($defenderBlock) === array_merge(array(5), array_fill(0, 19, 0)),
	'las tropas propias de la aldea espiada se ven sin los refuerzos sumados encima'
);

// ------------------------------------------------- informe viejo, sin refuerzos
$oldReportCsv = implode(',', $reportFields).',trap-data-v1,0,0,0,0,0,0,0,0,0,0,0';
$renderedOld = spyPrivacyRender($oldReportCsv);
spyPrivacyAssert(
	$renderedOld['errors'] === array(),
	'un informe viejo sin refuerzos se dibuja sin avisos de PHP: '.implode(' | ', $renderedOld['errors'])
);
spyPrivacyAssert(
	strpos($renderedOld['html'], REPORT_REINF) !== false,
	'un informe viejo se sigue dibujando con el desglose por tribu de siempre'
);

echo "check_spy_reinforcement_privacy: todo OK\n";

?>
