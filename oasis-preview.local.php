<?php require __DIR__.'/GameEngine/Production.php'; ?>
<!doctype html>
<html lang="es">
<meta charset="utf-8">
<title>Vista previa de oasis</title>
<link rel="stylesheet" href="gpack/travian_Travian_4.0_41/lang/ir/compact.css?asd498">
<link rel="stylesheet" href="gpack/travian_Travian_4.0_41/lang/ir/lang.css?asd423">
<link rel="stylesheet" href="img/travian_basics.css?v=59">
<style>
body{background:white;margin:24px;font:16px Arial,sans-serif;}
.player{max-width:725px;margin:auto;}
table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ccc;padding:8px;}
h1{font-size:20px;}p{margin:16px 0;}
</style>
<div class="player">
<h1>Oasis: vista previa local</h1>
<p>Ejemplo de tu captura: dos oasis de 50% de hierro y otro de 25% de hierro + 25% de cereal.</p>
<table id="villages">
<thead><tr><th>Nombre</th><th>Oasis</th><th>Población</th><th>Ubicación</th></tr></thead>
<tbody><tr><td class="name">[06] Ruinas de la Lechuga</td><td class="oases"><?php echo oasisBonusIcons(8).oasisBonusIcons(8).oasisBonusIcons(9); ?></td><td class="inhabitants">497</td><td class="coords">(40 | -18)</td></tr></tbody>
</table>
<p>Pasá el cursor sobre cada icono para ver su porcentaje. Los dos bonus del oasis mixto quedan juntos.</p>
</div>
</html>
