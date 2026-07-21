<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       admin.php                                                   ##
##  Developed by:  Dzoki                                                       ##
##  Reworked:      aggenkeech                                                  ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2012. All rights reserved.                ##
##                                                                             ##
#################################################################################

session_start();
include("../GameEngine/Database.php");
include("../GameEngine/Admin/database.php");
include("../config/config.php");
include("../GameEngine/Data/buidata.php");

class timeFormatGenerator
{
	public function getTimeFormat($time) 
	{
		$min = 0;
		$hr = 0;
		$days = 0;
		while ($time >= 60): $time -= 60; $min += 1; endwhile;
		while ($min  >= 60): $min  -= 60; $hr  += 1; endwhile;
		while ($hr   >= 24): $hr   -= 24; $days +=1; endwhile;
		if ($min < 10)
		{
			$min = "0".$min;
		}
		if($time < 10)
		{
			$time = "0".$time;
		}
		return $days ." d ".$hr." h ".$min." min ".$time." s";
	}
};
$timeformat = new timeFormatGenerator;
?>                                             
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<link REL="shortcut icon" HREF="favicon.ico"/>
		<title>Panel de administración</title>
		<link rel=stylesheet type="text/css" href="../img/admin/admin.css">
		<link rel=stylesheet type="text/css" href="../img/admin/acp.css">
		<link rel=stylesheet type="text/css" href="../img/img.css">
		<script src="/mt-full.js?423cb"  type="text/javascript"></script>
		<script src="ajax.js" type="text/javascript"></script>
		<meta http-equiv="content-type" content="text/html; charset=UTF-8">
		<meta http-equiv="imagetoolbar" content="no">
	</head>
	<body>
		<script language="javascript">
			function aktiv() {this.srcElement.className='fl1'; }
			function inaktiv() {event.srcElement.className='fl2'; }

			function del(e,id){
			if(e == 'did'){ var conf = confirm('¿Realmente quieres eliminar la aldea con ID '+id+'?'); }
			if(e == 'unban'){ var conf = confirm('¿Realmente quieres quitar la sanción al jugador '+id+'?'); }
			if(e == 'stopDel'){ var conf = confirm('¿Realmente quieres detener la eliminación del usuario '+id+'?'); }
			if(conf){return true;}else{return false;}
			}
		</script>
		<script type="text/javascript">
			function showStuff(id) {
				document.getElementById(id).style.display = 'block';
			}
			function hideStuff(id) {
				document.getElementById(id).style.display = 'none';
			}
		</script>
		<div id="ltop1">
			<div style="position:relative; width:231px; height:100px; float:left;">
				<img src="../img/x.gif" width="1" height="1">
			</div>
			<img class="fl2" src="../img/admin/x1.gif" width="70" height="100" border="0" onmouseover="this.className='fl1'" onmouseout="this.className='fl2'"><img class="fl2" src="../img/admin/x2.gif" width="70" height="100" border="0" onmouseover="this.className='fl1'" onmouseout="this.className='fl2'"><img class="fl2" src="../img/admin/x3.gif" width="70" height="100" border="0" onmouseover="this.className='fl1'" onmouseout="this.className='fl2'"><img class="fl2" src="../img/admin/x4.gif" width="70" height="100" border="0" onmouseover="this.className='fl1'" onmouseout="this.className='fl2'"><img class="fl2" src="../img/admin/x5.gif" width="70" height="100" border="0" onmouseover="this.className='fl1'" onmouseout="this.className='fl2'"></div>
			<div id="lmidall">
				<div id="lmidlc">
					<div id="lleft" style="width: 160px;">
						<a href="<?php echo HOMEPAGE; ?>"><img src="../img/en/a/travian0.gif" class="logo_plus" width="116" height="60" border="0"></a>
						<table id="navi_table" cellspacing="0" cellpadding="0" style="width: 150px;">
							<tr>
								<td class="menu">
									<?php     
										if($funct->CheckLogin())
										{?>
											<?php 
											if($_SESSION['access'] == ADMIN)
											{ ?>
								<a href="<?php echo HOMEPAGE; ?>">Página principal del servidor</a>
								<a href="admin.php">Inicio del panel</a>
												<br />
								<a href="?action=logout">Cerrar sesión</a>
												<br />
								<a href="#"><b>Mantenimiento</b></a>
								<a href="?p=maintenence">Mantenimiento del servidor</a>
								<a href="?p=delmedal">Eliminar medallas de jugadores</a>
								<a href="?p=delallymedal">Eliminar medallas de alianzas</a>
												<br />
								<a href="#"><b>Información del servidor</b></a>
								<a href="?p=server_info">Información del servidor</a>
								<a href="?p=online">Usuarios conectados</a>
								<a href="?p=notregistered">Jugadores sin activar</a>
												<br />
								<a href="?p=search"><b>Buscar</b></a>
												<br />
								<a href="?p=message">Buscar mensajes</a>
								<a href="?p=message">Buscar informes de batalla</a>
												<br />
								<a href="#"><b>Sanciones</b></a>
								<a href="?p=ban">Sancionar jugadores</a>
								<a href="?p=ban">Quitar sanciones</a>
												<br /><br />
								<a href="#"><b>Oro</b></a>
								<a href="?p=gold">Dar oro gratis a todos</a>
								<a href="?p=maintenenceResetGold">Restablecer oro</a>
												<br />
												<a href="#"><b>Plus</b></a>
								<a href="?p=givePlus">Dar Plus a todos</a>
								<a href="?p=maintenenceResetPlus">Restablecer Plus</a>
												<br />
								<a href="#"><b>Bonificación de recursos</b></a>
								<a href="?p=givePlusRes">Dar bonificación a todos</a>
								<a href="?p=maintenenceResetPlusBonus">Restablecer bonificaciones</a>
												<br />
								<a href="?p=natarnature"><b>Natares y naturaleza</b></a>
								<a href="?p=oasis">Poblar oasis</b>
								<a href="?p=npctribecreatenatar">Crear cuenta natar</b>
								<a href="?p=addArtefacts">Añadir artefactos</a>
								<a href="?p=addWW">Añadir aldeas Maravilla</a>
												<br />
												<a href="#"><b>Admin:</b></a>
								<a href="?p=admin_log"><font color="Red"><b>Registro administrativo</font></b></a>
								<a href="?p=config">Configuración del servidor</a>
												<?php 
											} 
											else if($_SESSION['access'] == MULTIHUNTER)
											{ ?>
								<a href="admin.php">Inicio del panel</a>
								<a href="<?php echo HOMEPAGE; ?>">Página principal</a>
												<a href="#"></a><a href="#"></a>
								<a href="?p=server_info">Información del servidor</a>
								<a href="?p=online">Usuarios conectados</a>
								<a href="?p=search">Buscar</a>
								<a href="?p=message">Mensajes/Informes</a>
								<a href="?p=ban">Sanciones</a>
												<a href="#"></a><a href="#"></a><a href="#"></a>
								<a href="?action=logout">Cerrar sesión</a><?php
											} 
										}
									?>
								</td>
							</tr>
						</table>
					</div>
					<div id="lmid1">
						<div id="lmid3">
							<?php
								if($funct->CheckLogin())
								{            
									if($_POST or $_GET)
									{  
										if($_GET['p'] and $_GET['p']!="search")
										{
											$filename = 'Templates/'.$_GET['p'].'.tpl';
											if(file_exists($filename))
											{
												include($filename);
											}
											else
											{
												include('Templates/404.tpl');
											}
										}
										else
										{
											include('Templates/search.tpl');
										}  
										if($_POST['p'] and $_POST['s'])
										{
											$filename = 'Templates/results_'.$_POST['p'].'.tpl';
											if(file_exists($filename))
											{
												include($filename);
											}
											else
											{
												include('Templates/404.tpl');
											}        
										}
									}
									else
									{
										include('Templates/home.tpl');  
									}
								}
								else
								{           
									include('Templates/login.tpl');
								}    
							?>
						</div>  
					</div>
				</div>  
			<div id="lright1"></div>
			<div id="ce"></div>
		</div>
	</body>
</html>
