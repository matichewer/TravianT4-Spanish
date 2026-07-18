<div align="center">
	<ul class="tabs">
		<li><a id="a_title_1" onclick="SetCurrent(1);" class="current" href="#">Inicio</a></li>
		<li><a id="a_title_2" onclick="SetCurrent(2);" href="#">Estadísticas de acceso</a></li>
		<li><a id="a_title_3" onclick="SetCurrent(3);" href="#">Información del servidor</a></li>
	</ul>
</div>

<div id="div_1">
	<table style="width:100%;">
		<tr>
			<td align="center"><a href="index.php?p=News"><img title="Editar noticias" src="images/icon/edit-icon.png"></a></td>
			<td align="center"><img title="Editar contenido" src="images/icon/floppy-icon.png"></td>
			<td align="center"><img title="Administrar comentarios" src="images/icon/fav-b-add-icon.png"></td>
			<td align="center"><img title="Categorías" src="images/icon/documents-or-copy-icon.png"></td>
		</tr>
		<tr>
			<td align="center"><a href="index.php?p=News">Editar noticias</a></td>
			<td align="center">
			<a href="postmgr.php">Archivo de contenido</a></td>
			<td align="center"><a href="comment.php">Administrar comentarios</a></td>
			<td align="center"><a href="cat.php">Categorías</a></td>
		</tr>
		<tr>
			<td align="center" style="height: 8px"></td>
			<td align="center" style="height: 8px"></td>
			<td align="center" style="height: 8px"></td>
			<td align="center" style="height: 8px"></td>
		</tr>
				<tr>
			<td align="center"><img title="Administrar enlaces" longdesc="Administrar enlaces" src="images/icon/web-search-icon.png"></td>
			<td align="center"><img title="Bloques" longdesc="Bloques" src="images/icon/window-b-icon.png"></td>
			<td align="center"><img title="Páginas adicionales" longdesc="Páginas adicionales" src="images/icon/documents-or-copy-icon.png"></td>
			<td align="center"><img title="Administrar miembros" longdesc="Administrar miembros" src="images/icon/group-of-users-icon.png"></td>
		</tr>

		<tr>
			<td align="center"><a href="simplelink.php">Enlaces</a></td>
			<td align="center"><a href="block.php">Bloques</a></td>
			<td align="center"><a href="extra.php">Páginas adicionales</a></td>
			<td align="center"><a href="member.php">Administrar miembros</a></td>
		</tr>
		<tr>
			<td align="center" style="height: 8px"></td>
			<td align="center" style="height: 8px"></td>
			<td align="center" style="height: 8px"></td>
			<td align="center" style="height: 8px"></td>
		</tr>
				<tr>
			<td align="center"><img title="Bandeja de mensajes" longdesc="Bandeja de mensajes" src="images/icon/mail-send-icon.png"></td>
			<td align="center"><img title="Administrar archivos" longdesc="Administrar archivos" src="images/icon/folder-open-icon.png"></td>
			<td align="center"><img title="Lista negra" longdesc="Lista negra " src="images/icon/delete-user-icon.png"></td>
			<td align="center"><img title="Boletín" longdesc="Boletín" src="images/icon/fav-add-icon.png"></td>
		</tr>

		<tr>
			<td align="center"><a href="inbox.php">Mensajes</a></td>
			<td align="center"><a href="uc.php">Administrar archivos</a></td>
			<td align="center"><a href="banned.php">Lista negra</a></td>
			<td align="center"><a href="newsletter.php">Boletín</a></td>
		</tr>
		<tr>
			<td align="center" style="height: 8px"></td>
			<td align="center" style="height: 8px"></td>
			<td align="center" style="height: 8px"></td>
			<td align="center" style="height: 8px"></td>
		</tr>
				<tr>
			<td align="center"><img title="Configuración" longdesc="Configuración" src="images/icon/window-icon.png"></td>
			<td align="center"><img title="Plantilla del sitio" longdesc="Plantilla del sitio" src="images/icon/paint-icon.png"></td>
			<td align="center"><img title="Copia de seguridad" longdesc="Copia de seguridad " src="images/icon/refresh-icon.png"></td>
			<td align="center"><img title="Actualizar" longdesc="Actualizar" src="images/icon/wizard-icon.png"></td>
		</tr>

		<tr>
			<td align="center"><a href="setting.php">Configuración</a></td>
			<td align="center"><a href="template.php">Plantilla del sitio</a></td>
			<td align="center"><a href="backup.php">Copia de seguridad</a></td>
			<td align="center"><a href="update.php">Actualizar</a></td>
		</tr>
		<tr>
			<td align="center" style="height: 8px"></td>
			<td align="center" style="height: 8px"></td>
			<td align="center" style="height: 8px"></td>
			<td align="center" style="height: 8px"></td>
		</tr>

		<tr>
			<td align="center"><a href="ymsgr:sendim?westehran"><img title="Soporte" src="images/icon/help-icon.png"></a></td>
			<td align="center"></td>
			<td align="center"><a href="<?php echo HOMEPAGE; ?>" target="_blank"><img title="Ver sitio" src="images/icon/web-icon.png"></a></td>
			<td align="center"><a href="?action=logout"><img title="Cerrar sesión" src="images/icon/close-icon.png"></a></td>
		</tr>

		<tr>
			<td align="center"><a href="ymsgr:sendim?trafian_ir">Soporte</a></td>
			<td align="center"></td>
			<td align="center"><a href="<?php echo HOMEPAGE; ?>" target="_blank">Ver servidor</a></td>
			<td align="center"><a href="?action=logout">Cerrar sesión</a></td>
		</tr>

	</table>
</div>
<div id="div_2" style="display:none;">
    <table id="member" border="1" cellpadding="3">
        <tr>
            <td><b>Log ID</b></td>
            <td><b>Admin</b></td>
            <td><b>LOG</b></td>
            <td><b>Date</b></td>
            <td><b>Acciones</b></td>
        </tr>
    <?php

    $sql = mysql_query("SELECT * FROM ".TB_PREFIX."admin_log ORDER BY id DESC LIMIT 10");
    $query = count($sql);
        if($query>0){
            while($row = mysql_fetch_array($sql)){
                $admid = $row['user'];
                $user = $database->getUserField($admid,"username",0);
                if($user == 'Multihunter') {
                    $admin = '<b>Control Panel</b>';
                } else {
                    $admin = '<a href="admin.php?p=player&uid='.$admid.'">'.$user.'</a>';
                }
                echo '
                <tr id="log'.$row['id'].'">
                    <td>'.$row['id'].'</td>
                    <td>'.$admin.'</td>
                    <td>'.$row['log'].'</td>
                    <td>'. date("d.m.Y H:i:s",$row['time']+3600*2).'</td>
                    <td><a onclick="dellog('.$row['id'].')" href="javascript:void(0)"><img border="0" src="../img/admin/delete.png"></a></td>
                </tr>
                ';
            }
        }
    ?>
    </table>
</div>
<div id="div_3" style="display:none;">
	<?php
    $tribe1 = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE tribe = 1 and id>3");
    $tribe2 = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE tribe = 2 and id>3");
    $tribe3 = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE tribe = 3 and id>3");
    $tribes = array(mysql_num_rows($tribe1),mysql_num_rows($tribe2),mysql_num_rows($tribe3));
    $users = mysql_num_rows(mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id>3"));
    $actives = mysql_num_rows(mysql_query("SELECT * FROM ".TB_PREFIX."active"));
    $onlines = mysql_num_rows(mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE ".time()." - timestamp < 300"));
    $banned = mysql_num_rows(mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE access = 0"));
    $villages = mysql_num_rows(mysql_query("SELECT * FROM ".TB_PREFIX."vdata"));
    $alliances = mysql_num_rows(mysql_query("SELECT * FROM ".TB_PREFIX."alidata"));
    $adventures = mysql_num_rows(mysql_query("SELECT * FROM ".TB_PREFIX."adventure"));
    $auctions = mysql_num_rows(mysql_query("SELECT * FROM ".TB_PREFIX."auction WHERE finish = 0"));
    $notices = mysql_num_rows(mysql_query("SELECT * FROM ".TB_PREFIX."ndata"));
    $movements = mysql_num_rows(mysql_query("SELECT * FROM ".TB_PREFIX."movement WHERE proc = 0"));
    $allvillages = mysql_num_rows(mysql_query("SELECT * FROM ".TB_PREFIX."wdata WHERE oasistype = 0"));
    $alloasis = mysql_num_rows(mysql_query("SELECT * FROM ".TB_PREFIX."wdata WHERE fieldtype = 0"));
    $occoasis = mysql_num_rows(mysql_query("SELECT * FROM ".TB_PREFIX."wdata WHERE fieldtype = 0 and occupied!=0"));
    ?><br />
    <table id="server_info" width="170" border="1" bgcolor="#E5E5E5" cellpadding="2">
            <tbody>
                <tr>
                    <td align="center" colspan="2"><b>Información del servidor</b><br /><br /></td>
                </tr>
                <tr>
                    <td>Jugadores registrados:</td>
                    <td><?php echo $users; ?></td>
                </tr>
                <tr>
                    <td>Jugadores activos:</td>
                    <td><?php echo $actives; ?></td>
                </tr>
                <tr>
                    <td>Jugadores conectados:</td>
                    <td><?php echo $onlines; ?></td>
                </tr>
                <tr>
                    <td>Cuentas bloqueadas:</td>
                    <td><?php echo $banned; ?></td>
                </tr>
                <tr>
                    <td>Alianzas:</td>
                    <td><?php echo $alliances; ?></td>
                </tr>
                <tr>
                    <td>Aventuras:</td>
                    <td><?php echo $adventures; ?></td>
                </tr>
                <tr>
                    <td>Subastas:</td>
                    <td><?php echo $auctions; ?></td>
                </tr>
                <tr>
                    <td>Informes:</td>
                    <td><?php echo $notices; ?></td>
                </tr>
                <tr>
                    <td>Movimientos:</td>
                    <td><?php echo $movements; ?></td>
                </tr>
            </tbody>
    </table>
    <br /><br />
    <div style="margin-right:200px;margin-top:-220px;">
    <table id="server_info" width="170" border="1" bgcolor="#E5E5E5" cellpadding="2">
        <thead align="center">
            <tr>
                <td colspan="3" align="center"><b>Información de los jugadores</b><br><br></td>
            </tr>
            <tr>
                <td><b>Tribu</b></td>
                <td><b>Cantidad</b></td>
                <td><b>Porcentaje</b></td>
            </tr>
        </thead>
        <tbody>

            <tr>
                <td>Romanos:</td>
                <td><?php echo $tribes[0]; ?></td>
                <td><?php $percents = 100*($tribes[0] / $users); echo round($percents,1); ?>%</td>
            </tr>
            <tr>
                <td>Germanos:</td>
                <td><?php echo $tribes[1]; ?></td>
                <td><?php $percents = 100*($tribes[1] / $users); echo round($percents,1); ?>%</td>
            </tr>
            <tr>
                <td width="60">Galos:</td>
                <td width="20"><?php echo $tribes[2]; ?></td>
                <td><?php $percents = 100*($tribes[2] / $users); echo round($percents,1); ?>%</td>
            </tr>
        </tbody>
    </table>
    </div>

    <div style="margin-right:400px;margin-top:-104px;">
    <table id="server_info" width="170" border="1" bgcolor="#E5E5E5" cellpadding="2">
        <thead align="center">
            <tr>
                <td colspan="2" align="center"><b>Información del mapa</b><br><br></td>
            </tr>
        </thead>
        <tbody>

            <tr>
                <td>Total de aldeas:</td>
                <td><?php echo $allvillages; ?></td>
            </tr>
            <tr>
                <td>Aldeas conquistadas:</td>
                <td><?php echo $villages; ?></td>
            </tr>
            <tr>
                <td>Total de oasis:</td>
                <td><?php echo $alloasis; ?></td>
            </tr>
            <tr>
                <td>Oasis conquistados:</td>
                <td><?php echo $occoasis; ?></td>
            </tr>
            <tr>
                <td>Capacidad máxima del mapa:</td>
                <td><?php echo WORLD_MAX."x".WORLD_MAX; ?></td>
            </tr>
        </tbody>
    </table>
    </div>
</div>