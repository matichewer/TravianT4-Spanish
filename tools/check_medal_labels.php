<?php
/**
 * Auditoría de los textos de las medallas semanales.
 *
 * Cubre las dos cosas que se rompieron históricamente: que la palabra inglesa
 * guardada en `points` ('Three', 'twice ', ...) llegue tal cual a la pantalla y
 * que alguna categoría quede sin traducir y se muestre como "Medalla".
 *
 * Ejecutar: docker compose exec -T web php tools/check_medal_labels.php
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

require dirname(__DIR__).'/GameEngine/MedalLabels.php';

// Todas las categorías que Automation::WeeklyMedals() sabe insertar.
$weeklyCategories = array(1, 2, 3, 4, 10);
$streakCategories = array(6, 7, 8, 9, 11, 12, 13, 14, 15, 16);
$allCategories = array_merge($weeklyCategories, array(5), $streakCategories);

// Los valores que el motor guarda en `points` para las medallas bonus.
$streakWords = array('Three', 'Five', 'Ten');
$repeatWords = array('', 'twice ', 'three times ');

$english = array('three', 'five', 'ten', 'twice', 'times', 'attacker', 'defender',
                 'climber', 'robber', 'in a row', 'week', 'top 10 att');

echo "== Etiquetas de medallas ==\n";

// Con medal_top = 1 el servidor solo premia al primero, así que ningún texto
// puede hablar de un top 3 ni de un top 10.
define('MEDAL_TOP', 1);
define('MEDAL_ALLY_TOP', 1);
check(medalPodiumSize() === 1, "medalPodiumSize() debe leer MEDAL_TOP");
check(medalPodiumSize(true) === 1, "medalPodiumSize(true) debe leer MEDAL_ALLY_TOP");

foreach($allCategories as $categorie) {
    $points = in_array($categorie, $streakCategories, true)
        ? $streakWords
        : ($categorie == 5 ? $repeatWords : array('12345'));
    foreach($points as $value) {
        $label = medalCategoryLabel($categorie, $value);
        check($label !== 'Medalla',
            "la categoría ".$categorie." no tiene texto propio");
        check(trim($label) !== '',
            "la categoría ".$categorie." devuelve un texto vacío");
        foreach($english as $word) {
            check(strpos(strtolower($label), $word) === false,
                "la categoría ".$categorie." deja '".$word."' sin traducir: ".$label);
        }
        check(strpos($label, 'top 3') === false && strpos($label, 'top 10') === false,
            "con medal_top=1 la categoría ".$categorie." no debería hablar de un top: ".$label);
    }
}

// La palabra de la racha tiene que convertirse en un número visible.
check(medalStreakTimes('Three') === 3, "'Three' son 3 veces");
check(medalStreakTimes('Five') === 5, "'Five' son 5 veces");
check(medalStreakTimes('Ten') === 10, "'Ten' son 10 veces");
check(medalStreakTimes('twice ') === 2, "'twice ' son 2 veces");
check(medalStreakTimes('three times ') === 3, "'three times ' son 3 veces");
check(medalStreakTimes('') === 1, "un `points` vacío es la primera vez");
check(medalStreakTimes('vaya') === 0, "un valor desconocido no inventa una racha");

check(strpos(medalCategoryLabel(6, 'Three'), '3.ª') !== false,
    "la racha de atacantes debe decir cuántas veces: ".medalCategoryLabel(6, 'Three'));
check(strpos(medalCategoryLabel(11, 'Five'), '5.ª') !== false,
    "la racha de crecimiento debe decir cuántas veces: ".medalCategoryLabel(11, 'Five'));
check(medalCategoryLabel(5, '') !== medalCategoryLabel(5, 'twice '),
    "el bonus repetido de ataque+defensa debe distinguirse del primero");

// Las medallas bonus no tienen puesto ni puntos que mostrar.
foreach($streakCategories as $categorie) {
    check(medalIsBonus($categorie), "la categoría ".$categorie." es una medalla bonus");
}
check(medalIsBonus(5), "la categoría 5 es una medalla bonus");
foreach($weeklyCategories as $categorie) {
    check(!medalIsBonus($categorie), "la categoría ".$categorie." tiene puesto propio");
}

// Ninguna plantilla debe volver a traducir las categorías por su cuenta.
$templates = array(
    'Templates/Profile/profile.tpl',
    'Templates/Profile/medal.php',
    'Templates/Alliance/medal.php',
    'Admin/Templates/playermedals.tpl',
    'Admin/Templates/allymedals.tpl',
    'Admin/Templates/delmedal.tpl',
    'Admin/Templates/delallymedal.tpl',
    'Admin/Templates/editUser.tpl'
);
foreach($templates as $template) {
    $source = file_get_contents(dirname(__DIR__).'/'.$template);
    check($source !== false, $template." no se puede leer");
    check(strpos($source, 'medalCategoryLabel') !== false,
        $template." debe usar medalCategoryLabel()");
    check(!preg_match('/switch\s*\(\s*\$(medal|row)\[.categorie.\]/', $source),
        $template." vuelve a mapear las categorías a mano");
}

echo "\n";
if(count($fails) > 0) {
    echo count($fails)." de ".$checks." comprobaciones fallaron\n";
    exit(1);
}
echo "OK: ".$checks." comprobaciones\n";
exit(0);
