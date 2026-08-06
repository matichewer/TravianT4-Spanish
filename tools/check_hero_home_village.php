<?php
// Regresión de la aldea natal del héroe: que se mude sola cuando el jugador deja de
// tener esa aldea, y que no se mueva en ningún otro caso.
//
//   docker compose exec -T web php /var/www/html/tools/check_hero_home_village.php

require_once dirname(__DIR__).'/GameEngine/Hero.php';

function homeAssert($condition, $message)
{
	if(!$condition){
		fwrite(STDERR, "FAIL: ".$message."\n");
		exit(1);
	}
}

class HeroHomeDatabase
{
	public $hero;
	public $villages;
	public $writes = 0;

	public function __construct($hero, $villages)
	{
		$this->hero = $hero;
		$this->villages = $villages;
	}

	public function getHeroData($uid) { return $this->hero; }

	// El orden importa: getVillagesID() ordena por capital primero.
	public function getVillagesID($uid) { return $this->villages; }

	public function modifyHero2($field, $value, $uid, $mode)
	{
		$this->writes++;
		if($mode == 0){ $this->hero[$field] = $value; } else { $this->hero[$field] += $value; }
		return true;
	}
}

function heroWith($home, $wref, $villages)
{
	return new HeroHomeDatabase(
		array('uid' => 7, 'home' => $home, 'wref' => $wref, 'dead' => 0),
		$villages
	);
}

// --- La natal válida no se toca -----------------------------------------------

$db = heroWith(100, 100, array(100, 101, 102));
homeAssert(reassignHeroHomeVillage($db, 7) === 100, 'mudó una aldea natal que seguía siendo del jugador');
homeAssert($db->writes === 0, 'escribió en el héroe sin necesidad');

// Aunque el héroe esté parado en otra aldea propia.
$db = heroWith(102, 100, array(100, 101, 102));
homeAssert(reassignHeroHomeVillage($db, 7) === 102, 'mudó la natal porque el héroe estaba en otra aldea');
homeAssert($db->writes === 0, 'escribió en el héroe sin necesidad');

// Y aunque esté parado en una aldea ajena (refuerzo, oasis, aventura).
$db = heroWith(101, 900, array(100, 101, 102));
homeAssert(reassignHeroHomeVillage($db, 7) === 101, 'mudó la natal porque el héroe estaba fuera');
homeAssert($db->writes === 0, 'escribió en el héroe sin necesidad');

// --- La natal perdida se muda -------------------------------------------------

// Se la conquistaron: cae en la capital, que getVillagesID() devuelve primero.
$db = heroWith(101, 100, array(100, 102));
homeAssert(reassignHeroHomeVillage($db, 7) === 100, 'no mudó la natal conquistada a la capital');
homeAssert((int)$db->hero['home'] === 100, 'no guardó la nueva natal');
homeAssert($db->writes === 1, 'escribió más de una vez');

// Correrlo de nuevo no vuelve a escribir.
$db->writes = 0;
homeAssert(reassignHeroHomeVillage($db, 7) === 100, 'la segunda pasada devolvió otra natal');
homeAssert($db->writes === 0, 'la segunda pasada volvió a escribir');

// La arrasaron con catapultas y ya no existe en ningún lado.
$db = heroWith(999, 100, array(100, 101));
homeAssert(reassignHeroHomeVillage($db, 7) === 100, 'no mudó la natal de una aldea inexistente');
homeAssert((int)$db->hero['home'] === 100, 'no guardó la nueva natal');

// --- Bordes -------------------------------------------------------------------

// Un héroe sin aldeas no se toca: no hay a dónde mudarlo.
$db = heroWith(101, 101, array());
homeAssert(reassignHeroHomeVillage($db, 7) === 0, 'inventó una natal sin aldeas');
homeAssert($db->writes === 0, 'escribió en un héroe sin aldeas');

// Un héroe viejo sin `home` cae en `wref`, y si ese sigue siendo suyo no se muda.
$db = heroWith(0, 102, array(100, 102));
homeAssert(reassignHeroHomeVillage($db, 7) === 102, 'no respetó el `wref` de un héroe sin `home`');
homeAssert($db->writes === 0, 'mudó un héroe sin `home` que estaba en una aldea propia');

// Pero si el `wref` tampoco es suyo, se muda a la capital.
$db = heroWith(0, 900, array(100, 102));
homeAssert(reassignHeroHomeVillage($db, 7) === 100, 'no mudó un héroe sin `home` y con `wref` ajeno');
homeAssert((int)$db->hero['home'] === 100, 'no guardó la nueva natal');

// Con una sola aldea, la natal es esa.
$db = heroWith(500, 500, array(100));
homeAssert(reassignHeroHomeVillage($db, 7) === 100, 'no mudó la natal habiendo una sola aldea');

// Un uid inválido o una base sin los métodos no rompe nada.
$db = heroWith(100, 100, array(100));
homeAssert(reassignHeroHomeVillage($db, 0) === 0, 'aceptó un uid inválido');
homeAssert(reassignHeroHomeVillage(new stdClass(), 7) === 0, 'aceptó una base sin los métodos');

// Un jugador sin héroe tampoco.
$db = new HeroHomeDatabase(false, array(100));
homeAssert(reassignHeroHomeVillage($db, 7) === 0, 'inventó una natal sin héroe');
homeAssert($db->writes === 0, 'escribió sin héroe');

echo "Hero home village regression: OK\n";
