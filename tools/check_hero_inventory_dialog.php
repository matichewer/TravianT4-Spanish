<?php
// Comprueba que la vista de prueba usa el diálogo real y que abrir no consume objetos.
$source = file_get_contents(dirname(__DIR__).'/hero_inventory.php');
$start = strpos($source,'var activate = function(){');
$end = strpos($source,'if(!element)', $start);
$activation = substr($source,$start,$end-$start);
function inventoryDialogAssert($ok,$message){ if(!$ok){ fwrite(STDERR,$message."\n"); exit(1); } }
inventoryDialogAssert(strpos($activation,'openItemDetails(id, amount, btype, type)')!==false,'El clic debe abrir la ficha');
inventoryDialogAssert(strpos($activation,'showItem(')===false && strpos($activation,'submit(')===false,'Abrir la ficha no debe usar el objeto');
inventoryDialogAssert(strpos($source,"['Equipar','Subastar','Liquidar']")!==false,'Faltan acciones');
inventoryDialogAssert(strpos($source,"hash_equals((string)\$session->mchecker,(string)\$_POST['c'])")!==false,'Las ventas requieren token');
inventoryDialogAssert(strpos($source,"\$database->disposeHeroItem((int)\$session->uid,\$itemId,\$amount,'liquidate')")!==false,'Liquidar debe reutilizar la operación existente');
inventoryDialogAssert(strpos($source,"input.addEventListener('input', update)")!==false && strpos($source,"input.addEventListener('change', update)")!==false,'La recompensa debe escuchar eventos nativos de cantidad');
inventoryDialogAssert(strpos($source,"form.setAttribute('action', index===1 ? 'hero_auction.php?action=sell' : 'hero_inventory.php')")!==false,'Subastar debe enviar a Vender');
inventoryDialogAssert(strpos($source,"form.elements.a.value = index===1 ? 'e45' : 'inventoryLiquidate'")!==false,'Subastar debe publicar mediante la operación existente');
inventoryDialogAssert(strpos($source,"window.location.href = 'hero_auction.php?action=sell'")===false,'Subastar no debe limitarse a redireccionar');
ob_start(); include __DIR__.'/preview_hero_inventory.php'; $preview=ob_get_clean();
inventoryDialogAssert(strpos($preview,'openItemDetails: function')!==false && strpos($preview,'<?php')===false,'Vista de prueba inválida');
echo "Hero inventory dialog checks passed.\n";
