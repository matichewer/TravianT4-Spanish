<?php
// El envío en sí vive en GameEngine/FarmList.php. La tribu se toma de la sesión
// (autoritativa) y no del POST, y la función comprueba que la lista sea del jugador.
require_once dirname(__DIR__, 2).'/GameEngine/FarmList.php';

farmListSendRaids(
    $database,
    $generator,
    $session->uid,
    (int)$session->tribe,
    isset($_POST['lid']) ? (int)$_POST['lid'] : 0,
    $_POST
);
// Con `exit`: sin él, build.php seguía de largo y dibujaba la Plaza de reuniones entera
// —la lista, objetivo por objetivo— para una respuesta que el navegador descarta al
// seguir la redirección, y enseguida la volvía a dibujar.
header("Location: build.php?id=39&t=99");
exit;
