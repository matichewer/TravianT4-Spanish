<?php
/**
 * Auditoría de las notificaciones semanales de medallas.
 *
 * Ejecutar: docker compose exec -T web php tools/check_weekly_medal_notifications.php
 */

error_reporting(E_ALL);
chdir(dirname(__DIR__));

$checks = 0;
$fails = array();

function check($condition, $message) {
    global $checks, $fails;
    $checks++;
    if($condition) {
        return;
    }
    $fails[] = $message;
    echo "  FAIL  ".$message."\n";
}

define('TRAVIAN_SKIP_AUTOMATION_BOOTSTRAP', true);
define('TB_PREFIX', 's1_');

require dirname(__DIR__).'/GameEngine/Automation.php';

class WeeklyMedalNotificationDatabaseStub {
    public $messages = array();
    public $sendCalls = 0;
    public $queries = array();

    public function query_return($query) {
        $this->queries[] = $query;
        if(strpos($query, 'FROM s1_medal m') !== false) {
            return array(
                array('userid'=>10, 'username'=>'Atacante Uno', 'categorie'=>1, 'plaats'=>1, 'points'=>123456),
                array('userid'=>11, 'username'=>'Defensor Uno', 'categorie'=>2, 'plaats'=>1, 'points'=>98765),
                array('userid'=>12, 'username'=>'Constructor Uno', 'categorie'=>10, 'plaats'=>1, 'points'=>246),
                array('userid'=>13, 'username'=>'Saqueador <script>', 'categorie'=>4, 'plaats'=>1, 'points'=>87320)
            );
        }
        if(strpos($query, 'FROM s1_allimedal am') !== false) {
            return array(
                array('allyid'=>5, 'name'=>'Atacantes Unidos', 'tag'=>'ATQ', 'categorie'=>1, 'plaats'=>1, 'points'=>720300),
                array('allyid'=>6, 'name'=>'Alianza Central', 'tag'=>'DEF', 'categorie'=>2, 'plaats'=>1, 'points'=>500000),
                array('allyid'=>7, 'name'=>'Constructores', 'tag'=>'CRE', 'categorie'=>3, 'plaats'=>1, 'points'=>2500),
                array('allyid'=>8, 'name'=>'Saqueadores', 'tag'=>'SAQ', 'categorie'=>4, 'plaats'=>1, 'points'=>380000)
            );
        }
        if(strpos($query, 'SELECT id FROM s1_users WHERE id > 3') !== false) {
            return array(
                array('id'=>10),
                array('id'=>11),
                array('id'=>12),
                array('id'=>13)
            );
        }
        return array();
    }

    public function sendMessage($target, $owner, $topic, $message, $send, $alliance, $player, $coor, $report) {
        $this->sendCalls++;
        $this->messages[(int)$target] = array(
            'owner'=>(int)$owner,
            'topic'=>$topic,
            'message'=>stripslashes($message)
        );
        return true;
    }
}

$database = new WeeklyMedalNotificationDatabaseStub();
$reflection = new ReflectionClass('Automation');
$automation = $reflection->newInstanceWithoutConstructor();
$notify = $reflection->getMethod('notifyWeeklyMedalResults');
$notify->setAccessible(true);
$notify->invoke($automation, 7);

check($database->sendCalls === 4, 'debe hacer exactamente un envío a cada jugador real');
check(count($database->messages) === 4, 'debe notificar a todos los jugadores del servidor');
check(isset($database->messages[10]), 'debe notificar a un ganador personal');
check(isset($database->messages[11]), 'debe notificar a otro ganador personal');
check(isset($database->messages[12]), 'debe notificar a un jugador sin medalla');
check(isset($database->messages[13]), 'debe notificar también al jugador sin premios ni alianza');
check(strpos($database->queries[0], 'm.plaats = 1') !== false, 'debe consultar sólo el puesto 1 de jugadores');
check(strpos($database->queries[0], 'm.categorie IN (1,2,10,4)') !== false, 'debe consultar las cuatro categorías regulares de jugadores');
check(strpos($database->queries[0], 'FIELD(m.categorie,1,2,10,4)') !== false, 'debe ordenar jugadores por ataque, defensa, crecimiento y saqueo');
check(strpos($database->queries[1], 'am.plaats = 1') !== false, 'debe consultar sólo el puesto 1 de alianzas');
check(strpos($database->queries[1], 'am.categorie IN (1,2,3,4)') !== false, 'debe consultar las cuatro categorías regulares de alianzas');

foreach($database->messages as $target=>$message) {
    check($message['owner'] === 4, 'el remitente para el jugador '.$target.' debe ser Multihunter');
    check($message['topic'] === 'Medallas de la semana 7', 'el asunto debe identificar la semana');
}

$announcement = $database->messages[10]['message'];
foreach($database->messages as $target=>$message) {
    check($message['message'] === $announcement, 'todos deben recibir el mismo resumen global (jugador '.$target.')');
}
check(strpos($announcement, '[b]Jugadores[/b]') !== false, 'el anuncio debe separar las medallas personales');
check(strpos($announcement, '[b]Alianzas[/b]') !== false, 'el anuncio debe separar las medallas de alianza');
check(substr_count($announcement, '[message]') === 1, 'debe agrupar todas las medallas en un solo mensaje');
list($playerSection, $allianceSection) = explode("[b]Alianzas[/b]", $announcement, 2);
check(substr_count($playerSection, "\n• ") === 4, 'la sección Jugadores debe tener exactamente cuatro ganadores');
check(substr_count($allianceSection, "\n• ") === 4, 'la sección Alianzas debe tener exactamente cuatro ganadores');
check(strpos($announcement, '<a href="spieler.php?uid=10">Atacante Uno</a> - Ataque: puesto #1 - 123.456 puntos') !== false, 'debe enlazar al jugador e indicar categoría, puesto y valor con guiones');
check(strpos($announcement, '<a href="allianz.php?aid=6">Alianza Central [DEF]</a> - Defensa: puesto #1 - 500.000 puntos') !== false, 'el nombre y la etiqueta deben enlazar a la alianza real con la categoría simplificada');
check(strpos($announcement, 'de alianza') === false, 'las categorías de la sección Alianzas no deben repetir de alianza');
check(strpos($announcement, 'Racha') === false, 'no debe incluir medallas especiales o de racha');
check(strpos($announcement, '—') === false, 'no debe usar separadores de raya larga');
check(strpos($announcement, 'Saqueador &lt;script&gt;') !== false, 'debe escapar los nombres de jugadores antes de incluirlos');
check(strpos($announcement, 'Saqueador <script>') === false, 'no debe permitir HTML desde nombres de jugadores');

if(count($fails) > 0) {
    echo "\nResultado: ".count($fails)." fallas de ".$checks." comprobaciones.\n";
    exit(1);
}

echo "Resultado: ".$checks." comprobaciones OK.\n";
