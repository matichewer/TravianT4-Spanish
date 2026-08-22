<?php
// Regresión del consumo atómico y del cooldown por cuenta de la obra de arte.
// Usa tablas temporales que ocultan las reales solo dentro de esta conexión.
//
//   docker compose exec -T web php /var/www/html/tools/check_artwork_cooldown.php

require dirname(__DIR__).'/GameEngine/Database.php';

$failures = array();
function artworkAssert($condition,$message){
	global $failures;
	if(!$condition){ $failures[]=$message; }
}
function artworkRow($database,$sql){
	$result=mysqli_query($database->connection,$sql);
	return $result ? mysqli_fetch_assoc($result) : false;
}

$users=TB_PREFIX.'users';
$items=TB_PREFIX.'heroitems';
mysqli_query($database->connection,"CREATE TEMPORARY TABLE $users (id int unsigned NOT NULL, cp int unsigned NOT NULL DEFAULT 0, artwork_last_used int unsigned NOT NULL DEFAULT 0, PRIMARY KEY(id)) ENGINE=MyISAM");
mysqli_query($database->connection,"CREATE TEMPORARY TABLE $items (id int unsigned NOT NULL, uid int unsigned NOT NULL, btype int unsigned NOT NULL, num int unsigned NOT NULL, proc int unsigned NOT NULL DEFAULT 0, PRIMARY KEY(id)) ENGINE=MyISAM");
mysqli_query($database->connection,"INSERT INTO $users (id,cp,artwork_last_used) VALUES (991001,1000,0)");
mysqli_query($database->connection,"INSERT INTO $items (id,uid,btype,num,proc) VALUES (1,991001,15,2,0)");

$first=$database->consumeArtwork(991001,1,1394,200000);
$user=artworkRow($database,"SELECT cp,artwork_last_used FROM $users WHERE id=991001");
$item=artworkRow($database,"SELECT num,proc FROM $items WHERE id=1");
artworkAssert($first['ok']===true && $first['points']===1394,'El primer uso válido fue rechazado.');
artworkAssert((int)$user['cp']===2394 && (int)$user['artwork_last_used']===200000,'El primer uso no acreditó PC y reloj juntos.');
artworkAssert((int)$item['num']===1 && (int)$item['proc']===0,'El primer uso no descontó exactamente una obra.');

$second=$database->consumeArtwork(991001,1,1394,200001);
$user=artworkRow($database,"SELECT cp,artwork_last_used FROM $users WHERE id=991001");
$item=artworkRow($database,"SELECT num,proc FROM $items WHERE id=1");
artworkAssert($second['ok']===false && $second['status']==='cooldown' && $second['remaining']===86399,'El segundo uso no informó el cooldown correcto.');
artworkAssert((int)$user['cp']===2394 && (int)$item['num']===1 && (int)$item['proc']===0,'El uso bloqueado alteró PC u objeto.');

$elapsed=$database->consumeArtwork(991001,1,1394,286400);
$user=artworkRow($database,"SELECT cp,artwork_last_used FROM $users WHERE id=991001");
$item=artworkRow($database,"SELECT num,proc FROM $items WHERE id=1");
artworkAssert($elapsed['ok']===true && (int)$user['cp']===3788,'La obra no se habilitó al cumplirse 24 horas.');
artworkAssert((int)$item['num']===1 && (int)$item['proc']===1,'La última unidad del stack no quedó consumida.');

$invalid=$database->consumeArtwork(991001,999,1394,400000);
artworkAssert($invalid['ok']===false && $invalid['status']==='invalid','Un objeto ajeno o inexistente fue aceptado.');

$inventorySource=file_get_contents(dirname(__DIR__).'/GameEngine/Inventory.php');
artworkAssert(strpos($inventorySource,"\$data['amount']===1")!==false,'Inventory no exige exactamente una obra por petición.');
$databaseSource=file_get_contents(dirname(__DIR__).'/GameEngine/Database/db_MYSQLi.php');
artworkAssert(strpos($databaseSource,"GET_LOCK('")!==false && strpos($databaseSource,"RELEASE_LOCK('")!==false,'El consumo no está serializado por cuenta.');
artworkAssert(strpos($databaseSource,'SET artwork_last_used=$lastUsed')!==false,'El fallo posterior no restaura el reloj reclamado.');
$installerSource=file_get_contents(dirname(__DIR__).'/install/data/sql.sql');
$migrationSource=file_get_contents(__DIR__.'/migrations.sql');
artworkAssert(strpos($installerSource,'`artwork_last_used` int(11) unsigned NOT NULL DEFAULT \'0\'')!==false,'Una instalación nueva no crea el reloj de obras.');
artworkAssert(strpos($migrationSource,'ADD COLUMN IF NOT EXISTS artwork_last_used')!==false,'La migración productiva del reloj no es idempotente.');
$inventoryPage=file_get_contents(dirname(__DIR__).'/hero_inventory.php');
artworkAssert(strpos($inventoryPage,'if(btype == 15){ amount = 1; }')!==false,'La interfaz todavía intenta enviar stacks de obras.');
artworkAssert(strpos($inventoryPage,'Una obra de arte cada 24 horas')!==false,'El diálogo no explica el cooldown.');
$auctionText=file_get_contents(dirname(__DIR__).'/Templates/Auction/alt.tpl');
artworkAssert(strpos($auctionText,'Solo se puede usar una cada 24 horas')!==false,'La descripción de subasta no explica el cooldown.');

// La obra concede un día de producción, y la pasiva no escala con la velocidad del
// mundo: lo único que la velocidad toca es el tope, que ya se lee de
// artworkCulturePointsCap(). Anunciar un "x3" o un 5000 fijo prometía hasta cinco veces
// lo que después acredita Inventory.php.
$btype = 15;
$type = 0;
$name = '';
$title = '';
$item = '';
include dirname(__DIR__).'/Templates/Auction/alt.tpl';
artworkAssert(strpos($title,'velocidad')===false,'La descripción de subasta sigue atando la obra a la velocidad del mundo.');
artworkAssert(
	strpos($title,number_format(artworkCulturePointsCap(),0,',','.'))!==false,
	'La descripción de subasta no anuncia el tope real de la obra.'
);
$inventoryDialog = file_get_contents(dirname(__DIR__).'/hero_inventory.php');
artworkAssert(
	strpos($inventoryDialog,"PC obtenidos (producción diaria de la cuenta, máximo <?php echo number_format(artworkCulturePointsCap(),0,',','.'); ?>)")!==false,
	'El diálogo del inventario no anuncia la producción diaria y el tope real.'
);

if($failures){
	fwrite(STDERR,"Artwork cooldown regression: FAILED\n - ".implode("\n - ",$failures)."\n");
	exit(1);
}
echo "Artwork cooldown regression: OK\n";
