<?php
/**
 * La ficha de un artefacto.
 *
 * `$_GET['show']` entraba crudo en `getArtefactDetails()`, que lo concatenaba dentro de
 * un `WHERE id = ...`: era una inyección SQL de libro, comprobada con un UNION desde el
 * navegador. Hoy la consulta castea a entero y acá se valida además que exista.
 */
$artefactId = isset($_GET['show']) && ctype_digit((string)$_GET['show']) ? (int)$_GET['show'] : 0;
$artefact = $artefactId > 0 ? $database->getArtefactDetails($artefactId) : array();

if(!is_array($artefact) || empty($artefact['id'])) {
	echo '<h4 class="round">Artefacto</h4><p class="none">Ese artefacto no existe.</p>';
	return;
}

include("27_rows.tpl");

$type = (int)$artefact['type'];
$size = (int)$artefact['size'];
$ownerName = $database->getUserField((int)$artefact['owner'], 'username', 0);
if($ownerName === '' || $ownerName === false || $ownerName === null) {
	$ownerName = 'Natars';
}
$allianceId = (int)$database->getUserField((int)$artefact['owner'], 'alliance', 0);
$allianceName = $allianceId > 0 ? $database->getAllianceName($allianceId) : '';
$villageName = $database->getVillageField((int)$artefact['vref'], 'name');
$isMine = (int)$artefact['owner'] === (int)$session->uid;
$state = $isMine
	? artefactActivationState($artefact, $database->getActiveArtefactsByOwner($session->uid))
	: null;

$effectText = artefactTypeEffectText($type);
if($type === ARTEFACT_FOOL) {
	$roll = artefactFoolRoll($artefact);
	$effectText .= ' Ahora mismo imita a '.artefactTypeName($roll['type'])
		.($roll['penalty'] ? ', y en contra' : '')
		.'; vuelve a cambiar el '.date('d/m/Y H:i', artefactFoolNextRoll($artefact)).'.';
}
?>
<?php
// La ilustración grande. El gpack sólo trae `img/artefact/type-N.jpg` para algunos
// tipos, así que se dibuja sólo si el archivo existe: antes la clase estaba escrita
// como "artefact.image-6" —con un punto literal, o sea una sola clase inexistente— y
// además fija en 6, así que no se dibujaba nunca ninguna.
$artefactImage = 'gpack/travian_Travian_4.0_41/img/artefact/type-'.$type.'.jpg';
if(is_file($artefactImage)) {
	echo '<img class="artefact image-'.$type.'" src="img/x.gif" alt="">';
}
?>
        <h4 class="round"><?php echo htmlspecialchars(artefactDisplayName($type, $size), ENT_QUOTES, 'UTF-8'); ?></h4>
            <table id="art_details" cellpadding="1" cellspacing="1">
                <tbody>
                    <tr>
                        <td colspan="2" class="desc">
                            <span class="detail"><?php echo htmlspecialchars($effectText, ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Jugador</th>
                        <td><a href="spieler.php?uid=<?php echo (int)$artefact['owner']; ?>"><?php
                            echo htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8'); ?></a></td>
                    </tr>
                    <tr>
                        <th>Aldea</th>
                        <td><a href="karte.php?d=<?php echo (int)$artefact['vref']; ?>&amp;c=<?php
                            echo $generator->getMapCheck((int)$artefact['vref']); ?>"><?php
                            echo htmlspecialchars($villageName === '' ? '[?]' : $villageName, ENT_QUOTES, 'UTF-8'); ?></a></td>
                    </tr>
                    <tr>
                        <th>Alianza</th>
                        <td><?php if($allianceId > 0) { ?><a href="allianz.php?aid=<?php echo $allianceId; ?>"><?php
                            echo htmlspecialchars($allianceName, ENT_QUOTES, 'UTF-8'); ?></a><?php
                            } else { echo '<span class="none">Sin alianza</span>'; } ?></td>
                    </tr>
                    <tr>
                        <th>Alcance</th>
                        <td><?php echo htmlspecialchars(artefactSizeName($size), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th>Efecto</th>
                        <td><b><?php echo htmlspecialchars(artefactEffectValueLabel($artefact), ENT_QUOTES, 'UTF-8'); ?></b></td>
                    </tr>
                    <tr>
                        <th>Tesoro necesario</th>
                        <td>Nivel <b><?php echo artefactTreasuryRequirement($size); ?></b></td>
                    </tr>
                    <tr>
                        <th>Capturado</th>
                        <td><?php echo date("d/m/Y H:i", (int)$artefact['conquered']); ?></td>
                    </tr>
<?php if($state !== null) { ?>
                    <tr>
                        <th>Estado</th>
                        <td><?php
                            echo htmlspecialchars(artefactActivationStateLabel($state), ENT_QUOTES, 'UTF-8');
                            if($state['state'] === 'pending') {
                                echo ' '.$generator->getTimeFormat($state['seconds']);
                            } elseif($state['state'] === 'displaced') {
                                echo ' &mdash; sólo pueden estar activos '.ARTEFACT_MAX_ACTIVE
                                    .' artefactos a la vez, y uno solo de cuenta';
                            }
                        ?></td>
                    </tr>
<?php } ?>
                </tbody>
            </table>
            <p class="none">Para llevarte un artefacto necesitas un Tesoro de nivel
            <?php echo artefactTreasuryRequirement($size); ?> vacío en la aldea desde la que atacas,
            derribar el Tesoro de la aldea que lo guarda y ganar un ataque normal (no un asalto)
            con tu héroe, que además tiene que sobrevivir.</p>
