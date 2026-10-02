<?php
// Vista de prueba sin sesión ni base de datos. Las acciones se simulan.
$source = file_get_contents(dirname(__DIR__).'/hero_inventory.php');
$start = strpos($source, '        openItemDetails: function');
$end = strpos($source, "\t\tshowItem: function", $start);
$method = substr($source, $start, $end-$start);
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Prueba del inventario</title>
<base href="/">
<link rel="stylesheet" href="gpack/travian_Travian_4.0_41/lang/ir/compact.css">
<link rel="stylesheet" href="gpack/travian_Travian_4.0_41/lang/ir/lang.css">
<link rel="stylesheet" href="img/travian_basics.css">
<script src="crypt.js"></script>
<style>body{background:#a2bb7c;font:14px Arial;padding:30px}.preview{background:white;padding:25px;max-width:600px;margin:auto}.preview button{margin:8px}.heroItemDetails{min-width:260px;max-width:400px}</style>
</head><body>
<div class="preview" id="content"><h1>Inventario: prueba visual</h1>
<p>Estos objetos son ficticios. Ninguna acción modifica el juego.</p>
<button onclick="preview.openItemDetails(1,1,1,1)">Casco</button>
<button onclick="preview.openItemDetails(2,64,9,0)">64 jaulas</button>
<button onclick="preview.openItemDetails(3,9,10,0)">9 pergaminos</button>
<button onclick="preview.openItemDetails(4,26,11,0)">26 ungüentos</button>
<button onclick="preview.openItemDetails(5,20,9,4)">Jaulas en la bolsa</button>
<p><label><input type="checkbox" onchange="preview.heroDead=this.checked ? 1 : 0"> Héroe muerto</label></p>
<p id="previewResult" role="status"></p></div>
<form id="HeroInventory"><input name="a"><input name="id"><input name="amount"></form>
<script>
var preview = {
    alreadyOpen:false, heroDead:0,
    itemDetails:{
        1:{name:'Casco de la Atención',description:'+15% de experiencia obtenida.',icon:1,sellable:true,stackable:false},
        2:{name:'Jaulas',description:'Permiten capturar animales en los oasis.',icon:114,sellable:true,stackable:true},
        3:{name:'Pergaminos',description:'Cada pergamino concede experiencia al héroe.',icon:115,sellable:true,stackable:true},
        4:{name:'Ungüentos',description:'Recuperan la salud del héroe.',icon:116,sellable:true,stackable:true},
        5:{name:'Jaulas',description:'Parte de este objeto está cargada en la bolsa.',icon:114,sellable:false,stackable:true}
    },
<?php echo $method; ?>
    showItem:function(id,amount){ $('previewResult').set('text','Simulación: equipar / usar objeto '+id+', cantidad '+amount); }
};
$('HeroInventory').submit=function(){
    $('previewResult').set('text','Simulación: '+this.elements.a.value+', objeto '+this.elements.id.value+', cantidad '+this.elements.amount.value);
};
</script></body></html>
