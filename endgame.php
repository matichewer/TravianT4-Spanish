<?php
/**
 * Ayuda del final de partida: Tesoro, artefactos, planos y Maravilla del Mundo.
 *
 * Está escrita para el jugador, no para el que mantiene el servidor, pero **todos los
 * números salen del motor** (`GameEngine/Artefact.php`, `buidata`, `SPEED`) en vez de estar
 * escritos en el texto. Es a propósito: una página de ayuda con los valores copiados a mano
 * es una página que miente en cuanto alguien toca una tabla, y de ese tipo de mentira ya
 * hubo varias acá — la ficha del Tesoro anunciaba "24 horas" en un mundo x3 donde son 12.
 * Si mañana cambia un valor oficial o la velocidad del servidor, esta página cambia sola.
 */

include("GameEngine/Village.php");

$endgameDelayHours = round(artefactActivationDelay(SPEED) / 3600);
$endgameCatalog = artefactTypeCatalog();
$endgameSizes = array(
    ARTEFACT_SIZE_SMALL  => 'Pequeño',
    ARTEFACT_SIZE_LARGE  => 'Grande',
    ARTEFACT_SIZE_UNIQUE => 'Único'
);
$endgameWonderMaxLevel = isset($GLOBALS['bid40']) ? max(array_keys($GLOBALS['bid40'])) : 100;
$endgameTreasuryCost = isset($GLOBALS['bid27'][10]) ? $GLOBALS['bid27'][10] : null;

/** El valor de un artefacto tal como lo muestra su ficha, o una raya si ese tamaño no existe. */
function endgameValue($type, $size)
{
    if ($type === ARTEFACT_STORAGE && $size === ARTEFACT_SIZE_UNIQUE) {
        return '&mdash;';
    }
    $row = array('id' => 0, 'type' => $type, 'size' => $size, 'conquered' => 0);
    return htmlspecialchars(artefactEffectValueLabel($row), ENT_QUOTES, 'UTF-8');
}

$endgameSections = array(
    'que-es'      => 'De qué se trata el final',
    'tesoro'      => 'El Tesoro',
    'artefactos'  => 'Los artefactos',
    'activacion'  => 'Cuándo hace efecto',
    'conseguir'   => 'Cómo conseguir uno',
    'aldeas'      => 'Las aldeas que los guardan',
    'planos'      => 'Los planos',
    'maravilla'   => 'La Maravilla del Mundo'
);

include "Templates/html.tpl";
?>
<body class="v35 webkit chrome troopStatsClean buildingStatsClean">
	<div id="wrapper">
		<img id="staticElements" src="img/x.gif" alt="" />
		<div class="troopStatsHeader">
			<a id="logo" href="<?php echo HOMEPAGE; ?>" target="_blank" title="<?php echo SERVER_NAME; ?>"></a>
			<p class="troopStatsBack"><a href="help.php">&laquo; Volver a la ayuda</a></p>
		</div>
		<div class="bodyWrapper">
			<img style="filter:chroma();" src="img/x.gif" id="msfilter" alt="" />
			<div id="mid">
				<div id="contentOuterContainer">
					<div class="contentTitle">&nbsp;</div>
					<div class="contentContainer">
						<div id="content" class="universal troopStatsPage buildingStatsPage">
							<h1 class="titleInHeader">El final de la partida</h1>
							<p class="troopStatsIntro">Los artefactos, el Tesoro y la Maravilla del Mundo: todo lo que decide quién gana el servidor. Los números de esta página son los de <b><?php echo SERVER_NAME; ?></b>, no los de un servidor cualquiera.</p>

							<nav class="buildingStatsIndex" aria-label="Índice">
							<?php foreach ($endgameSections as $endgameAnchor => $endgameTitle) { ?>
								<a href="#<?php echo $endgameAnchor; ?>"><?php echo htmlspecialchars($endgameTitle, ENT_QUOTES, 'UTF-8'); ?></a>
							<?php } ?>
							</nav>

							<section class="buildingStatsSection" id="que-es">
								<h2>De qué se trata el final</h2>
								<p>Durante la primera parte de la partida cada uno crece por su cuenta: aldeas, tropas, alianzas. El final es otra cosa, y es una carrera.</p>
								<p>En algún momento el administrador <b>libera los artefactos</b>: aparecen de golpe aldeas natares nuevas repartidas por el mapa, cada una guardando un objeto adentro. Esos objetos dan poderes permanentes —tropas más rápidas, edificios más resistentes, tropas que comen menos— y son limitados: de los mejores hay <b>uno solo en todo el servidor</b>. El que llega primero con el ejército armado se los queda.</p>
								<p>Después empieza la parte final de verdad: conquistar una <b>Aldea de la Maravilla</b> a los natares y levantar ahí la <b>Maravilla del Mundo</b>. La primera alianza que la lleva al nivel <?php echo (int) $endgameWonderMaxLevel; ?> gana el servidor y la partida termina.</p>
								<div class="helpInfoBlock helpInfoLinkLess">
									<div class="helpHeadLine">Lo primero que tenés que hacer</div>
									<div class="helpText">Construir un <b>Tesoro</b>. Sin él no podés quedarte con ningún artefacto, y levantarlo hasta el nivel que hace falta lleva días. El que empieza a construirlo el día que aparecen los artefactos ya llegó tarde.</div>
								</div>
							</section>

							<section class="buildingStatsSection" id="tesoro">
								<h2>El Tesoro</h2>
								<p>Es el edificio donde se guardan los artefactos. Necesitás <b>Edificio principal nivel 10</b> para poder construirlo, y sólo podés tener <b>uno por aldea</b>.</p>
								<div class="troopStatsTableWrapper">
									<table class="troopStatsTable" cellpadding="1" cellspacing="1">
										<thead><tr><th>Nivel</th><th>Para qué sirve</th></tr></thead>
										<tbody>
											<tr><td>1</td><td>Podés ver dónde está cada artefacto del servidor, de quién es y a qué distancia te queda.</td></tr>
											<tr><td><?php echo artefactTreasuryRequirement(ARTEFACT_SIZE_SMALL); ?></td><td>Podés quedarte con un artefacto <b>pequeño</b>.</td></tr>
											<tr><td><?php echo artefactTreasuryRequirement(ARTEFACT_SIZE_LARGE); ?></td><td>Podés quedarte con uno <b>grande</b> o <b>único</b>.</td></tr>
										</tbody>
									</table>
								</div>
								<p><b>Cada Tesoro guarda un solo artefacto.</b> Si querés tener varios, necesitás varias aldeas con Tesoro. Y ojo: mientras el Tesoro tenga un artefacto adentro está ocupado, así que no podés traer otro a esa misma aldea.</p>
								<?php if ($endgameTreasuryCost) { ?>
								<p class="troopStatsIntro">Para que te hagas una idea del costo: llevar un Tesoro del nivel 9 al 10 cuesta
									<?php echo number_format($endgameTreasuryCost['wood'], 0, ',', '.'); ?> de madera,
									<?php echo number_format($endgameTreasuryCost['clay'], 0, ',', '.'); ?> de barro,
									<?php echo number_format($endgameTreasuryCost['iron'], 0, ',', '.'); ?> de hierro y
									<?php echo number_format($endgameTreasuryCost['crop'], 0, ',', '.'); ?> de cereal. Podés ver la tabla completa en <a href="building_stats.php#edificio-27">Edificios</a>.</p>
								<?php } ?>
							</section>

							<section class="buildingStatsSection" id="artefactos">
								<h2>Los artefactos</h2>
								<p>Hay <b><?php echo count($endgameCatalog); ?> clases</b> de artefacto, y cada una viene en tres tamaños. El tamaño no es sólo "más fuerte": cambia <b>a cuántas aldeas alcanza</b>.</p>
								<div class="troopStatsTableWrapper">
									<table class="troopStatsTable" cellpadding="1" cellspacing="1">
										<thead><tr><th>Tamaño</th><th>A quién afecta</th><th>Tesoro que necesitás</th><th>Cuántos hay</th></tr></thead>
										<tbody>
											<tr><td><b>Pequeño</b></td><td>Sólo la aldea donde lo guardás</td><td>Nivel <?php echo artefactTreasuryRequirement(ARTEFACT_SIZE_SMALL); ?></td><td>Varios de cada clase</td></tr>
											<tr><td><b>Grande</b></td><td>Todas tus aldeas</td><td>Nivel <?php echo artefactTreasuryRequirement(ARTEFACT_SIZE_LARGE); ?></td><td>Unos pocos de cada clase</td></tr>
											<tr><td><b>Único</b></td><td>Todas tus aldeas</td><td>Nivel <?php echo artefactTreasuryRequirement(ARTEFACT_SIZE_UNIQUE); ?></td><td><b>Uno solo en todo el servidor</b></td></tr>
										</tbody>
									</table>
								</div>
								<p>Fijate en algo que sorprende: <b>el grande es el más flojo de los tres</b>. El pequeño es fuerte pero sólo en una aldea; el grande es más suave pero te alcanza toda la cuenta; el único junta lo mejor de los dos, y por eso hay uno solo.</p>

								<h2 style="margin-top:18px;">Qué hace cada uno</h2>
								<div class="troopStatsTableWrapper">
									<table class="troopStatsTable" cellpadding="1" cellspacing="1">
										<thead><tr>
											<th>Artefacto</th>
											<?php foreach ($endgameSizes as $endgameSizeLabel) { ?><th><?php echo $endgameSizeLabel; ?></th><?php } ?>
											<th>Qué te da</th>
										</tr></thead>
										<tbody>
										<?php foreach ($endgameCatalog as $endgameType => $endgameInfo) { ?>
											<tr>
												<td><b><?php echo htmlspecialchars($endgameInfo['name'], ENT_QUOTES, 'UTF-8'); ?></b></td>
												<?php foreach (array_keys($endgameSizes) as $endgameSize) { ?>
												<td><?php echo endgameValue($endgameType, $endgameSize); ?></td>
												<?php } ?>
												<td style="text-align:left;"><?php echo htmlspecialchars($endgameInfo['effect'], ENT_QUOTES, 'UTF-8'); ?></td>
											</tr>
										<?php } ?>
										</tbody>
									</table>
								</div>
								<p>Cómo leer los números: <b>x4</b> quiere decir que multiplica por cuatro (los edificios aguantan cuatro veces más catapultazos, las tropas van al doble de rápido). <b>1/2</b> quiere decir que lo reduce a la mitad (tus tropas comen la mitad de cereal, entrenar tarda la mitad).</p>
								<div class="helpInfoBlock helpInfoLinkLess">
									<div class="helpHeadLine">El artefacto del necio</div>
									<div class="helpText">Es el raro de la familia. Cada 24 horas copia al azar el efecto de otro artefacto, y <b>puede salir en contra</b>: te puede tocar que tus tropas coman el doble o que los edificios se te caigan más fácil. El único del necio es la excepción — ese nunca te perjudica. En la ficha del artefacto, dentro del Tesoro, siempre podés ver a quién está imitando hoy y a qué hora vuelve a cambiar.</div>
								</div>
							</section>

							<section class="buildingStatsSection" id="activacion">
								<h2>Cuándo hace efecto</h2>
								<p>Tener un artefacto no alcanza. Hay dos reglas, y las dos sorprenden a todo el mundo la primera vez.</p>

								<h2 style="margin-top:14px;">1. Tarda <?php echo (int) $endgameDelayHours; ?> horas en activarse</h2>
								<p>Desde el momento en que lo capturás hasta que empieza a hacer efecto pasan <b><?php echo (int) $endgameDelayHours; ?> horas</b> (este servidor va a velocidad x<?php echo SPEED; ?>; en un servidor normal son 24). Durante ese rato el artefacto es tuyo pero no te sirve de nada, y es justo cuando más expuesto estás: el que te lo vio llegar tiene <?php echo (int) $endgameDelayHours; ?> horas para venir a sacártelo.</p>
								<p><b>Cada captura reinicia ese reloj</b>, incluso si te roban un artefacto que ya tenías y lo recuperás.</p>

								<h2 style="margin-top:14px;">2. Sólo tres pueden estar activos a la vez</h2>
								<p>Podés juntar todos los artefactos que quieras, pero tu cuenta sólo mantiene <b><?php echo ARTEFACT_MAX_ACTIVE; ?> activos</b>, y de esos <b>uno solo puede ser grande o único</b>. Los demás quedan dormidos: son tuyos, figuran en tu Tesoro, y no hacen absolutamente nada.</p>
								<p>¿Cuáles se activan? Los que hace <b>más tiempo</b> que capturaste. Eso significa que un artefacto nuevo entra último en la fila, y si ya tenés tres activos, el nuevo se queda afuera hasta que pierdas alguno de los otros.</p>
								<div class="helpInfoBlock helpInfoLinkLess">
									<div class="helpHeadLine">El truco para cambiar cuál está activo</div>
									<div class="helpText">Como la prioridad la da la antigüedad, <b>volver a capturar un artefacto tuyo</b> —atacando tu propia aldea, o conquistándola— lo manda al fondo de la fila y deja entrar a otro. Es la única forma de elegir qué combinación tenés activa.</div>
								</div>
								<p>Y una última: dentro de una aldea, un artefacto <b>pequeño le gana al grande o al único</b> de la misma clase. No se suman ni gana el más fuerte: en esa aldea manda el pequeño, y en el resto de tus aldeas sigue mandando el de cuenta. En tu Tesoro cada artefacto te dice si está <i>Activo</i>, <i>Inactivo</i> o cuánto le falta para activarse.</p>
							</section>

							<section class="buildingStatsSection" id="conseguir">
								<h2>Cómo conseguir uno</h2>
								<p>Hay dos caminos.</p>

								<h2 style="margin-top:14px;">Camino 1: robarlo con el héroe</h2>
								<p>Es el clásico, y tiene <b>cinco condiciones que se cumplen todas o no pasa nada</b>:</p>
								<div class="troopStatsTableWrapper">
									<table class="troopStatsTable" cellpadding="1" cellspacing="1">
										<thead><tr><th>&nbsp;</th><th>Condición</th></tr></thead>
										<tbody>
											<tr><td>1</td><td style="text-align:left;">Tenés un <b>Tesoro vacío</b> del nivel que pide ese artefacto, en la aldea <b>desde la que atacás</b>.</td></tr>
											<tr><td>2</td><td style="text-align:left;">El <b>Tesoro de la aldea enemiga está derribado a 0</b>. Esto se hace con catapultas apuntando al Tesoro.</td></tr>
											<tr><td>3</td><td style="text-align:left;">Es un <b>ataque normal</b>, nunca un asalto. En un asalto tu héroe saquea y se vuelve.</td></tr>
											<tr><td>4</td><td style="text-align:left;">Tu <b>héroe va en el ataque</b>.</td></tr>
											<tr><td>5</td><td style="text-align:left;">Tu héroe <b>sobrevive</b>.</td></tr>
										</tbody>
									</table>
								</div>
								<p>En la práctica son <b>tres oleadas</b>, que pueden ser tuyas o repartidas entre aliados:</p>
								<div class="troopStatsTableWrapper">
									<table class="troopStatsTable" cellpadding="1" cellspacing="1">
										<thead><tr><th>Oleada</th><th>Qué manda</th><th>Para qué</th></tr></thead>
										<tbody>
											<tr><td>1ª</td><td>Tu ejército de ataque</td><td style="text-align:left;">Matar la guarnición natar. Sin esto las otras dos se estrellan.</td></tr>
											<tr><td>2ª</td><td>Catapultas, apuntando al Tesoro</td><td style="text-align:left;">Derribar el Tesoro enemigo a nivel 0.</td></tr>
											<tr><td>3ª</td><td>Tu héroe</td><td style="text-align:left;">Llevarse el artefacto.</td></tr>
										</tbody>
									</table>
								</div>
								<p>Las tres tienen que llegar <b>en ese orden y con pocos segundos de diferencia</b>. El artefacto no vuelve caminando con tus tropas: aparece solo en tu Tesoro en el momento en que se cumple todo.</p>
								<p>Si algo falla, el informe te dice exactamente qué: que faltaba derribar el Tesoro, que tu héroe murió, que tu Tesoro es de nivel insuficiente o que ya tenía un artefacto adentro.</p>

								<h2 style="margin-top:14px;">Camino 2: conquistar la aldea</h2>
								<p>Las aldeas que guardan artefactos <b>no tienen residencia ni palacio</b>, así que también podés tomarlas con administradores como cualquier aldea. El artefacto pasa a ser tuyo junto con la aldea y se queda ahí adentro. Es más lento y te ocupa un cupo de expansión, pero no te exige tener un Tesoro propio listo ni coordinar tres oleadas.</p>
								<p>Ojo: al conquistarla, el reloj de <?php echo (int) $endgameDelayHours; ?> horas <b>arranca de nuevo</b>.</p>
							</section>

							<section class="buildingStatsSection" id="aldeas">
								<h2>Las aldeas que los guardan</h2>
								<p>Cuando se liberan los artefactos aparecen <b>aldeas natares nuevas</b> en el mapa. No son las aldeas natares que ya conocías: son otras, creadas para la ocasión, y se reconocen porque llevan el nombre del artefacto que guardan.</p>
								<p>Lo que tenés que saber antes de atacar una:</p>
								<div class="troopStatsTableWrapper">
									<table class="troopStatsTable" cellpadding="1" cellspacing="1">
										<thead><tr><th>Cosa</th><th>Cómo es</th></tr></thead>
										<tbody>
											<tr><td>Guarnición</td><td style="text-align:left;"><b>Fija, y no se repone nunca.</b> Lo que le matás queda muerto para siempre, así que podés desgastarla en varias tandas a lo largo de semanas.</td></tr>
											<tr><td>Muralla</td><td style="text-align:left;">No tienen. Mandar arietes es tirar tropas.</td></tr>
											<tr><td>Residencia</td><td style="text-align:left;">Tampoco. Por eso se pueden conquistar con administradores.</td></tr>
											<tr><td>Escondite</td><td style="text-align:left;">Sí, y grande. Buena parte de lo que tienen guardado no te lo podés llevar.</td></tr>
											<tr><td>Botín</td><td style="text-align:left;">Producen y acumulan recursos, así que una vez limpia la guarnición son buenas granjas.</td></tr>
											<tr><td>Exploradores</td><td style="text-align:left;">Tienen. Explorá antes de mandar el ejército: te vas a enterar de cuánto hay adentro.</td></tr>
										</tbody>
									</table>
								</div>
								<p><b>Dónde están.</b> No se reparten al azar: los <b>únicos</b> caen cerca del centro del mapa, los <b>grandes</b> en una corona intermedia y los <b>pequeños</b> en la periferia. Cuanto mejor el artefacto, más al medio está y más peleado va a estar. Con un Tesoro de nivel 1 ya podés ver la lista completa con las distancias desde tu aldea.</p>
								<div class="helpInfoBlock helpInfoLinkLess">
									<div class="helpHeadLine">Qué tan duras son</div>
									<div class="helpText">Su defensa <b>no es un número fijo</b>: se calcula a partir de los mejores ejércitos que hay en el servidor. O sea que crecen con ustedes. Explorá siempre antes de atacar, porque el número de hoy no es el de hace un mes.</div>
								</div>
							</section>

							<section class="buildingStatsSection" id="planos">
								<h2>Los planos</h2>
								<p>La palabra "plano" se usa para dos cosas distintas y conviene no confundirlas.</p>
								<p><b>El plano de almacenamiento</b> es uno de los <?php echo count($endgameCatalog); ?> artefactos, y es el único que no te da un bono sino que te <b>desbloquea dos edificios</b>: el Gran almacén y el Gran granero. Sin él no existen, ni siquiera aparecen en la lista de construcción. Funciona como cualquier otro artefacto: tiene que estar activo, y si se te cae del podio de <?php echo ARTEFACT_MAX_ACTIVE; ?> dejás de poder ampliarlos (lo que ya construiste se queda).</p>
								<p><b>Los planos de construcción de la Maravilla</b> son otra cosa, y en <b><?php echo SERVER_NAME; ?></b> <b>no existen</b>. En el Travian original hacen falta para levantar la Maravilla; acá no: alcanza con conquistar una Aldea de la Maravilla. Es una diferencia a propósito de este servidor.</p>
							</section>

							<section class="buildingStatsSection" id="maravilla">
								<h2>La Maravilla del Mundo</h2>
								<p>Es el edificio que termina la partida. Sólo se puede levantar en una <b>Aldea de la Maravilla</b>, que son aldeas natares especiales que ya están en el mapa desde el principio — no aparecen con los artefactos, están desde el día uno.</p>
								<p>El camino es este:</p>
								<div class="troopStatsTableWrapper">
									<table class="troopStatsTable" cellpadding="1" cellspacing="1">
										<thead><tr><th>Paso</th><th>Qué hacés</th></tr></thead>
										<tbody>
											<tr><td>1</td><td style="text-align:left;">Encontrás una Aldea de la Maravilla en el mapa (llevan ese nombre y su coordenada).</td></tr>
											<tr><td>2</td><td style="text-align:left;">Le derribás las defensas y la <b>conquistás con administradores</b>. No tienen residencia, así que no hace falta bajarles nada primero.</td></tr>
											<tr><td>3</td><td style="text-align:left;">Construís la Maravilla y la vas subiendo de nivel. Cada nivel cuesta cientos de miles de recursos, y del <?php echo (int) $endgameWonderMaxLevel; ?> hacia atrás cada vez más.</td></tr>
											<tr><td>4</td><td style="text-align:left;">Llegás al nivel <b><?php echo (int) $endgameWonderMaxLevel; ?></b> y ganás el servidor.</td></tr>
										</tbody>
									</table>
								</div>
								<div class="helpInfoBlock helpInfoLinkLess">
									<div class="helpHeadLine">Los natares no se quedan mirando</div>
									<div class="helpText">A medida que la Maravilla sube, <b>los natares la atacan</b>: mandan oleadas cada cinco niveles, y a partir del 96 en cada nivel. No es una construcción tranquila — necesitás que media alianza esté defendiendo esa aldea mientras el resto le manda recursos.</div>
								</div>
								<p>Por eso el final se juega en alianza y no solo. Una Maravilla necesita un jugador que la construya, varios que le manden recursos sin parar y muchos que la defiendan. Y los artefactos son lo que hace la diferencia: con las tropas más rápidas llegás a defender a tiempo, y con los edificios más resistentes la Maravilla aguanta lo que a otro se le cae.</p>
								<p class="troopStatsIntro">Podés ver los costos de la Maravilla nivel por nivel en <a href="building_stats.php#edificio-40">Edificios</a>.</p>
							</section>

							<div class="clear"></div>
						</div>
					</div>
					<div class="contentFooter">&nbsp;</div>
				</div>

<?php include("Templates/footer.tpl"); ?>

				<div id="ce"></div>
			</div>
		</div>
	</div>
</body>
</html>
