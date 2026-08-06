<?php
// Reconcilia `hero.home` con las aldeas que el jugador tiene realmente.
//
// Hasta el commit que agregó reassignHeroHomeVillage(), perder la aldea natal (que te
// la conquisten con jefes o que te la arrasen con catapultas) dejaba `hero.home`
// apuntando a una aldea ajena o inexistente. Como los bonos se cobran comparando contra
// ese número, esos héroes quedaron sin bono de recursos y sin bono de entrenamiento, y
// el jugador no tiene forma de darse cuenta salvo mandarlo de apoyo a otra aldea propia
// con el check de aldea natal tildado.
//
// Muda la natal a la capital y, si no hay, a la primera aldea que quede. Es idempotente:
// a un héroe con la natal en orden no lo toca.
//
//   docker compose exec -T web php /var/www/html/tools/fix_hero_home_village.php
//   docker compose exec -T web php /var/www/html/tools/fix_hero_home_village.php --apply
//
// Sin --apply solo informa lo que cambiaría.

require_once dirname(__DIR__).'/GameEngine/Database.php';
require_once dirname(__DIR__).'/GameEngine/Hero.php';

$apply = in_array('--apply', $argv, true);
$heroTable = TB_PREFIX.'hero';

$rows = $database->query_return("SELECT uid, wref, home FROM $heroTable ORDER BY uid");
if(!is_array($rows)){
	fwrite(STDERR, "No se pudo leer la tabla de héroes\n");
	exit(1);
}

$checked = 0;
$orphaned = 0;
$homeless = 0;
foreach($rows as $row){
	$uid = (int)$row['uid'];
	$checked++;

	$villages = $database->getVillagesID($uid);
	if(!is_array($villages) || empty($villages)){
		// Sin aldeas no hay natal posible: se lo deja como está.
		$homeless++;
		continue;
	}
	$villages = array_map('intval', $villages);
	$home = heroHomeVillage($row);
	if(in_array($home, $villages, true)){
		continue;
	}

	$orphaned++;
	printf(
		"uid %-7d natal %-8d -> %-8d%s\n",
		$uid, $home, $villages[0], $apply ? '' : '   (simulacion)'
	);

	if($apply){
		$newHome = reassignHeroHomeVillage($database, $uid);
		if((int)$newHome !== $villages[0]){
			fwrite(STDERR, "No se pudo mudar la aldea natal del héroe $uid\n");
			exit(1);
		}
	}
}

printf(
	"\n%d héroes revisados, %d con la aldea natal perdida%s\n",
	$checked,
	$orphaned,
	($orphaned>0 && !$apply) ? " (volver a correr con --apply para mudarlos)" : ''
);
if($homeless>0){
	printf("%d héroes sin ninguna aldea: se dejaron como estaban\n", $homeless);
}
