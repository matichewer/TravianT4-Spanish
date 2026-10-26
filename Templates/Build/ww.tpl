<h1 class="titleInHeader">Maravilla del mundo <span class="level"> Nivel <?php echo $village->resarray['f'.$id]; ?></span></h1>

    <div id="build" class="gid40">
    <div class="build_desc">
        <a href="#" onClick="return Travian.Game.iPopup(40,4);" class="build_logo">
        <img class="building big white g40" src="img/x.gif" alt="Maravilla del mundo" title="Maravilla del mundo"></a>
        La Maravilla del Mundo es tan maravillosa como suena. Este edificio se construye para ganar el servidor. Cada nivel de la Maravilla cuesta cientos de miles (incluso millones) de recursos.</div>

<?php include "upgrade.tpl"; ?>
<div class="clear"></div><br />
<form action="GameEngine/Game/WorldWonderName.php" method="POST">
<input type="hidden" name="vref" value="<?php echo $_SESSION['wid']; ?>" />
<?php
$vref = $_SESSION['wid'];
$wwname = $database->getWWName($vref);

if($village->resarray['f99'] < 5){
echo '<p>Puedes elegir el nombre de la Maravilla del mundo cuando alcance el nivel 5.</p>
			<center>Nombre de la Maravilla del mundo <input class="text" name="wwname" id="wwname" disabled="disabled" value="'.$wwname.'" maxlength="20"> 
<button type="submit" value="ok" name="s1" id="btn_train" value="ok" class="startTraining" disabled="disabled">
                    <div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">OK</div></div>
                    </button>
</center>';
} else if($village->resarray['f99'] >= 5) {
echo '<center><br />Nombre de la Maravilla del mundo: <input class="text" name="wwname" id="wwname" value="'.$wwname.'" maxlength="20"> <button type="submit" value="ok" name="s1" id="btn_train" value="ok" class="startTraining">
                    <div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">OK</div></div>
                    </button></center>'; 
} ?>
    </form>
	<?php
    if(isset($_GET['n'])) {
		echo '<br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font color="Red"><b>El nombre de la Maravilla del mundo se cambió correctamente</b></font>';
		  }
		  ?>

</p></div>
