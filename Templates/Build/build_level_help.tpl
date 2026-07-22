<?php
if (!isset($buildingHelpType, $buildingHelpLevel)) {
	return;
}

$buildingHelpItems = array();
$buildingHelpNote = '';
$buildingHelpWarning = false;

if ($buildingHelpType === 'rally-point') {
	$buildingHelpTitle = 'Ventajas de la Plaza de reuniones';
	$buildingHelpItems = array(
		1 => 'Permite enviar tropas para reforzar, saquear, atacar o conquistar, y consultar los movimientos de la aldea.',
		3 => 'Las catapultas pueden elegir almacenes y graneros como objetivo.',
		5 => 'Las catapultas pueden apuntar a campos y edificios de recursos. También permite reconocer qué tipos de unidades participan en un ataque entrante, aunque no sus cantidades.',
		10 => 'Las catapultas pueden elegir casi cualquier edificio militar o de infraestructura como objetivo.',
		15 => 'Cumple el requisito para construir la Plaza de torneos.',
		20 => 'Las catapultas pueden seleccionar dos objetivos en el mismo ataque.'
	);
	$buildingHelpNote = 'Los niveles intermedios aportan población y puntos de cultura, pero no aumentan la velocidad ni la fuerza de las tropas.';
} elseif ($buildingHelpType === 'embassy') {
	$buildingHelpTitle = 'Ventajas de la Embajada';
	$buildingHelpItems = array(
		1 => 'Permite aceptar invitaciones y unirse a una alianza. También cumple uno de los requisitos para construir el Palacio.',
		3 => 'Permite fundar una alianza con capacidad inicial para 9 miembros.',
		4 => 'Desde aquí, cada nivel aumenta en 3 el límite de miembros de la alianza: nivel 4 admite 12, nivel 5 admite 15 y así sucesivamente.',
		20 => 'Permite alcanzar el máximo de 60 miembros.'
	);
	$buildingHelpNote = 'El aumento de capacidad solo resulta útil para quien dirige la alianza; se toma la Embajada de mayor nivel entre sus aldeas. Para los demás miembros, los niveles adicionales aportan principalmente población y puntos de cultura.';
	if ($buildingHelpLevel >= 3 && $buildingHelpLevel < 20) {
		$buildingHelpNote = 'Esta Embajada permite un límite de '.($buildingHelpLevel * 3).' miembros; al nivel '.($buildingHelpLevel + 1).' permitiría '.(($buildingHelpLevel + 1) * 3).'. '.$buildingHelpNote;
	} elseif ($buildingHelpLevel >= 20) {
		$buildingHelpNote = 'Esta Embajada ya permite el máximo de 60 miembros. '.$buildingHelpNote;
	}
} elseif ($buildingHelpType === 'hero-mansion') {
	$buildingHelpTitle = 'Ventajas de la Mansión del héroe';
	$buildingHelpItems = array(
		1 => 'Permite administrar al héroe y consultar los oasis cercanos a la aldea.',
		10 => 'El héroe puede conquistar el primer oasis para esta aldea.',
		15 => 'Desbloquea un segundo oasis conquistable.',
		20 => 'Desbloquea el tercer y último oasis conquistable.'
	);
	$buildingHelpNote = 'Cada oasis conquistado aumenta la producción de los recursos indicados por ese oasis. Los niveles intermedios aportan población y puntos de cultura, pero no aumentan directamente la fuerza del héroe.';
} elseif ($buildingHelpType === 'residence') {
	$buildingHelpTitle = 'Ventajas de la Residencia';
	$buildingHelpItems = array(
		1 => 'Protege la aldea frente a conquistas mientras el edificio siga en pie y permite consultar la lealtad y los puntos de cultura.',
		10 => 'Desbloquea el primer espacio de expansión para entrenar 3 colonos o un jefe y fundar o conquistar otra aldea.',
		20 => 'Desbloquea el segundo espacio de expansión.'
	);
	$buildingHelpNote = 'Cada nivel también reduce el tiempo de entrenamiento de colonos y jefes. La Residencia no permite nombrar una capital y no puede coexistir con un Palacio en la misma aldea.';
} elseif ($buildingHelpType === 'palace') {
	$buildingHelpTitle = 'Ventajas del Palacio';
	$buildingHelpItems = array(
		1 => 'Permite convertir esta aldea en capital y la protege frente a conquistas mientras el edificio siga en pie.',
		10 => 'Desbloquea el primer espacio de expansión para entrenar 3 colonos o un jefe.',
		15 => 'Desbloquea el segundo espacio de expansión.',
		20 => 'Desbloquea el tercer espacio de expansión.'
	);
	$buildingHelpNote = 'Cada nivel también reduce el tiempo de entrenamiento de colonos y jefes. Solo puede existir un Palacio por cuenta y no puede coexistir con una Residencia en la misma aldea.';
} elseif ($buildingHelpType === 'town-hall') {
	$buildingHelpTitle = 'Ventajas del Ayuntamiento';
	$buildingHelpItems = array(
		1 => 'Permite celebrar una fiesta pequeña, que concede 500 puntos de cultura.',
		10 => 'Desbloquea la celebración grande, que concede 2000 puntos de cultura.'
	);
	$buildingHelpNote = 'Todos los niveles reducen la duración de las celebraciones y aumentan la producción diaria de puntos de cultura del edificio.';
	if (isset($bid24[$buildingHelpLevel]['attri'])) {
		$buildingHelpCurrentValue = str_replace('.', ',', (string) round($bid24[$buildingHelpLevel]['attri'], 2));
		$buildingHelpNote = 'Duración actual: '.$buildingHelpCurrentValue.'% del tiempo base. '.$buildingHelpNote;
	}
} elseif ($buildingHelpType === 'academy') {
	$buildingHelpTribe = isset($session->tribe) ? (int) $session->tribe : 1;
	if ($buildingHelpTribe === 2) {
		$buildingHelpTitle = 'Ventajas de la Academia (Germanos)';
		$buildingHelpItems = array(
			1 => 'Permite investigar Lancero, Explorador y Paladín cuando también se cumplen los requisitos de sus otros edificios.',
			3 => 'Permite investigar Hachero.',
			10 => 'Permite investigar Ariete.',
			15 => 'Permite investigar Caballero germano y Catapulta.',
			20 => 'Permite investigar Jefe germano.'
		);
	} elseif ($buildingHelpTribe === 3) {
		$buildingHelpTitle = 'Ventajas de la Academia (Galos)';
		$buildingHelpItems = array(
			3 => 'Permite investigar Espadachín.',
			5 => 'Permite investigar Explorador, Trueno de Teutates y Druida.',
			10 => 'Permite investigar Ariete.',
			15 => 'Permite investigar Haeduano y Catapulta.',
			20 => 'Permite investigar Jefe galo.'
		);
	} else {
		$buildingHelpTitle = 'Ventajas de la Academia (Romanos)';
		$buildingHelpItems = array(
			1 => 'Permite investigar Pretoriano cuando también se cumplen los requisitos de los otros edificios.',
			5 => 'Permite investigar Imperiano y las tres unidades de caballería romanas.',
			10 => 'Permite investigar Ariete.',
			15 => 'Permite investigar Catapulta de fuego.',
			20 => 'Permite investigar Senador.'
		);
	}
	$buildingHelpNote = 'El nivel de Academia no basta por sí solo: cada unidad puede exigir además Herrería, Cuartel, Establo, Taller, Plaza de reuniones u otro edificio. Cada nivel reduce también la duración de las investigaciones.';
} elseif ($buildingHelpType === 'treasury') {
	$buildingHelpTitle = 'Ventajas de la Tesorería';
	$buildingHelpItems = array(
		1 => 'Permite consultar los artefactos propios y los artefactos disponibles en el servidor.',
		10 => 'Permite guardar y capturar un artefacto pequeño.',
		20 => 'Permite guardar y capturar un artefacto grande o único.'
	);
	$buildingHelpNote = 'Cada Tesorería almacena un solo artefacto. Para capturarlo, hay que destruir la Tesorería enemiga y realizar un ataque normal con el héroe, que debe sobrevivir.';
} elseif ($buildingHelpType === 'tournament-square') {
	$buildingHelpTitle = 'Ventajas de la Plaza de torneos';
	$buildingHelpItems = array(
		1 => 'Configura una bonificación de velocidad para la parte de los viajes que supera '.(defined('TS_THRESHOLD') ? TS_THRESHOLD : 30).' casillas.',
		20 => 'Alcanza la bonificación máxima configurada: 300% de velocidad en el tramo de larga distancia.'
	);
	$buildingHelpNote = 'Cada nivel añade 10 puntos porcentuales: nivel 1 configura 110%, nivel 10 configura 200% y nivel 20 configura 300%. Atención: el cálculo principal de salida de tropas tiene esta bonificación desactivada actualmente en el código del servidor.';
	$buildingHelpWarning = true;
} elseif ($buildingHelpType === 'horse-drinking-trough') {
	$buildingHelpTitle = 'Ventajas del Abrevadero';
	$buildingHelpItems = array(
		1 => 'Acelera el entrenamiento de toda la caballería romana tanto en el Establo como en el Gran establo.',
		20 => 'Alcanza la velocidad máxima configurada: 125% de la velocidad normal de entrenamiento.'
	);
	$buildingHelpNote = 'Cada nivel mejora progresivamente la velocidad de entrenamiento de la caballería.';
	if (isset($bid41[$buildingHelpLevel]['attri'])) {
		$buildingHelpCurrentValue = round($bid41[$buildingHelpLevel]['attri'] * 100);
		$buildingHelpNote = 'Velocidad actual configurada: '.$buildingHelpCurrentValue.'%. '.$buildingHelpNote;
	}
	$buildingHelpNote .= ' La reducción clásica del consumo de cereal de la caballería no está activa en esta versión.';
} elseif ($buildingHelpType === 'brewery') {
	$buildingHelpTitle = 'Ventajas de la Cervecería';
	$buildingHelpItems = array(
		1 => 'Está concebida para aumentar el ataque de las tropas germanas de toda la cuenta, a cambio de reducir la persuasión de los jefes y obligar a las catapultas a disparar al azar.',
		10 => 'Es el nivel máximo del edificio.'
	);
	$buildingHelpNote = 'Atención: en esta instalación los diez niveles tienen una bonificación configurada de 0% y el combate no aplica el efecto de la Cervecería. Hasta corregir esa mecánica, subirla solo aporta población y puntos de cultura.';
	$buildingHelpWarning = true;
} elseif ($buildingHelpType === 'main-building') {
	$buildingHelpTitle = 'Ventajas del Edificio principal';
	$buildingHelpItems = array(
		1 => 'Acelera la construcción y mejora de todos los campos de recursos y edificios de la aldea.',
		10 => 'Desbloquea la demolición controlada de edificios, un nivel por vez.',
		20 => 'Alcanza la reducción máxima del tiempo de construcción que ofrece este edificio.'
	);
	$buildingHelpNote = 'Todos los niveles reducen progresivamente el tiempo de construcción.';
	if (isset($bid15[$buildingHelpLevel]['attri'])) {
		$buildingHelpCurrentValue = str_replace('.', ',', (string) round($bid15[$buildingHelpLevel]['attri'], 2));
		$buildingHelpNote = 'Tiempo actual: '.$buildingHelpCurrentValue.'% del tiempo base.';
		if (isset($bid15[$buildingHelpLevel + 1]['attri'])) {
			$buildingHelpNextValue = str_replace('.', ',', (string) round($bid15[$buildingHelpLevel + 1]['attri'], 2));
			$buildingHelpNote .= ' En el próximo nivel será '.$buildingHelpNextValue.'%.';
		}
	}
} elseif ($buildingHelpType === 'marketplace') {
	$buildingHelpTitle = 'Ventajas del Mercado';
	$buildingHelpItems = array(
		1 => 'Permite enviar recursos, comprar y vender ofertas. Proporciona un mercader.',
		20 => 'Proporciona 20 mercaderes y, junto con un Establo de nivel 10, cumple el requisito para construir la Oficina de comercio.'
	);
	$buildingHelpNote = 'Cada nivel añade exactamente un mercader. La capacidad de cada mercader depende de la tribu y de la Oficina de comercio; subir el Mercado aumenta la cantidad de mercaderes, no la carga individual.';
	if (isset($bid17[$buildingHelpLevel]['attri'])) {
		$buildingHelpCurrentValue = (int) $bid17[$buildingHelpLevel]['attri'];
		$buildingHelpNote = 'Mercaderes proporcionados por este nivel: '.$buildingHelpCurrentValue.'. '.$buildingHelpNote;
	}
} elseif ($buildingHelpType === 'smithy') {
	$buildingHelpTitle = 'Ventajas de la Herrería';
	$buildingHelpItems = array(
		1 => 'Permite mejorar el ataque de las unidades investigadas hasta el mismo nivel que tenga la Herrería.',
		20 => 'Permite llevar las mejoras ofensivas de las unidades hasta el nivel máximo 20.'
	);
	$buildingHelpNote = 'El nivel del edificio funciona como límite: con Herrería 8, cada unidad puede mejorarse como máximo hasta nivel 8. Todos los niveles reducen además la duración de esas mejoras.';
	if (isset($bid12[$buildingHelpLevel]['attri'])) {
		$buildingHelpCurrentValue = str_replace('.', ',', (string) round($bid12[$buildingHelpLevel]['attri'], 2));
		$buildingHelpNote = 'Duración actual: '.$buildingHelpCurrentValue.'% del tiempo base. '.$buildingHelpNote;
	}
	$buildingHelpNote .= ' Esta instalación no ofrece mejoras de armadura: la Herrería solo gestiona y aplica las mejoras ofensivas.';
	$buildingHelpWarning = true;
} elseif (in_array($buildingHelpType, array('roman-wall', 'earth-wall', 'palisade'), true)) {
	if ($buildingHelpType === 'roman-wall') {
		$buildingHelpTitle = 'Ventajas de la Muralla romana';
		$buildingHelpValues = $bid31;
		$buildingHelpWallComparison = 'Es la muralla que ofrece la mayor bonificación defensiva, pero también la más vulnerable a los arietes.';
	} elseif ($buildingHelpType === 'earth-wall') {
		$buildingHelpTitle = 'Ventajas del Muro de tierra';
		$buildingHelpValues = $bid32;
		$buildingHelpWallComparison = 'Ofrece la menor bonificación defensiva, pero es la muralla más resistente frente a los arietes.';
	} else {
		$buildingHelpTitle = 'Ventajas de la Empalizada gala';
		$buildingHelpValues = $bid33;
		$buildingHelpWallComparison = 'Mantiene un equilibrio entre bonificación defensiva y resistencia frente a los arietes.';
	}
	$buildingHelpItems = array(
		1 => 'Añade una bonificación multiplicativa a la defensa de todas las tropas que protegen la aldea.',
		20 => 'Alcanza la bonificación defensiva máxima de este tipo de muralla.'
	);
	$buildingHelpNote = $buildingHelpWallComparison;
	if (isset($buildingHelpValues[$buildingHelpLevel]['attri'])) {
		$buildingHelpCurrentValue = $buildingHelpValues[$buildingHelpLevel]['attri'];
		$buildingHelpNote = 'Bonificación actual: '.$buildingHelpCurrentValue.'%. ';
		if (isset($buildingHelpValues[$buildingHelpLevel + 1]['attri'])) {
			$buildingHelpNextValue = $buildingHelpValues[$buildingHelpLevel + 1]['attri'];
			$buildingHelpNote .= 'Próximo nivel: '.$buildingHelpNextValue.'%. ';
		}
		$buildingHelpNote .= $buildingHelpWallComparison;
	}
} else {
	return;
}

$buildingHelpNextLevel = null;
foreach ($buildingHelpItems as $buildingHelpRequiredLevel => $buildingHelpText) {
	if ($buildingHelpRequiredLevel > $buildingHelpLevel) {
		$buildingHelpNextLevel = $buildingHelpRequiredLevel;
		break;
	}
}
if ($buildingHelpType === 'embassy' && $buildingHelpLevel >= 4) {
	$buildingHelpNextLevel = null;
}
?>
<details class="buildingLevelHelp">
	<summary title="Mostrar las ventajas de subir este edificio de nivel">
		<span class="buildingLevelHelpIcon" aria-hidden="true">?</span>
		<span>Ventajas al subir de nivel</span>
	</summary>
	<div class="buildingLevelHelpContent">
		<div class="buildingLevelHelpHeading">
			<strong><?php echo $buildingHelpTitle; ?></strong>
			<span>Nivel actual: <?php echo (int) $buildingHelpLevel; ?></span>
		</div>
		<ul>
			<?php foreach ($buildingHelpItems as $buildingHelpRequiredLevel => $buildingHelpText) {
				$buildingHelpItemClass = $buildingHelpRequiredLevel <= $buildingHelpLevel ? 'unlocked' : '';
				if ($buildingHelpRequiredLevel === $buildingHelpNextLevel) {
					$buildingHelpItemClass = 'next';
				}
			?>
			<li<?php echo $buildingHelpItemClass !== '' ? ' class="'.$buildingHelpItemClass.'"' : ''; ?>>
				<strong>Nivel <?php echo $buildingHelpRequiredLevel; ?>:</strong>
				<?php echo $buildingHelpText; ?>
				<?php if ($buildingHelpItemClass === 'next') { ?><span class="buildingLevelHelpNext">Próximo hito</span><?php } ?>
			</li>
			<?php } ?>
		</ul>
		<p class="buildingLevelHelpNote<?php echo $buildingHelpWarning ? ' warning' : ''; ?>"><?php echo $buildingHelpNote; ?></p>
	</div>
</details>
<?php
unset(
	$buildingHelpType,
	$buildingHelpLevel,
	$buildingHelpTitle,
	$buildingHelpItems,
	$buildingHelpNote,
	$buildingHelpWarning,
	$buildingHelpNextLevel,
	$buildingHelpRequiredLevel,
	$buildingHelpText,
	$buildingHelpItemClass,
	$buildingHelpTribe,
	$buildingHelpCurrentValue,
	$buildingHelpNextValue,
	$buildingHelpValues,
	$buildingHelpWallComparison
);
?>
