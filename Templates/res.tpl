<?php
/* Tablero de recursos del encabezado: cantidad actual, barra que se llena hasta la
   capacidad del deposito con el tiempo que falta adentro, produccion por hora y
   capacidad maxima. El conteo en vivo lo hace resource-board.js; aca solo va el
   estado inicial ya calculado por el servidor mas los datos que necesita el tick.

   La produccion sale siempre de Village::getProd (GameEngine/Production.php es la
   unica formula del juego); getProd('crop') ya viene neta de poblacion y tropas,
   asi que puede ser negativa. */

$heroData = $database->getHeroData($session->uid);
$heroProduction = heroVillageResourceBonus($heroData, $village->wid, SPEED);
$cropGrossProduction = $village->allcrop + $heroProduction['crop'];
$cropUpkeep = $village->pop + $technology->getUpkeep($village->unitall, 0);
$cropNetProduction = $village->getProd('crop');

$formatResourceAmount = function ($amount) {
	return number_format((int) round($amount), 0, ',', '.');
};

/* Reloj corto de la barra, con las horas sin tope (H:MM:SS). */
$formatClock = function ($seconds) {
	$seconds = max(0, (int) round($seconds));
	$hours = intdiv($seconds, 3600);
	if ($hours > 999) {
		return '999+';
	}

	return $hours . ':' . str_pad(intdiv($seconds % 3600, 60), 2, '0', STR_PAD_LEFT)
		. ':' . str_pad($seconds % 60, 2, '0', STR_PAD_LEFT);
};

/* Texto largo para el tooltip. */
$formatStorageFillTime = function ($capacity, $currentAmount, $production) {
	if ($production < 0) {
		if ($currentAmount <= 0) {
			return 'El depósito está vacío';
		}

		$minutes = (int) round($currentAmount * 60 / -$production);
		if ($minutes < 1) {
			return 'Se vacía en menos de 1 min';
		}
	} else {
		if ($currentAmount >= $capacity) {
			return 'Ya está lleno';
		}

		if ($production == 0) {
			return 'No se llenará con la producción actual';
		}

		$minutes = (int) round(($capacity - $currentAmount) * 60 / $production);
		if ($minutes < 1) {
			return 'Lleno en menos de 1 min';
		}
	}

	$days = intdiv($minutes, 1440);
	$hours = intdiv($minutes % 1440, 60);
	$remainingMinutes = $minutes % 60;
	$parts = array();

	if ($days > 0) {
		$parts[] = $days . ($days === 1 ? ' día' : ' días');
	}
	if ($hours > 0) {
		$parts[] = $hours . ' h';
	}
	if ($remainingMinutes > 0) {
		$parts[] = $remainingMinutes . ' min';
	}

	return ($production < 0 ? 'Se vacía en ' : 'Lleno en ') . implode(' ', $parts);
};

$resourceSlots = array(
	'wood' => array('slot' => 'r1', 'name' => WOOD, 'amount' => $village->awood, 'capacity' => $village->maxstore, 'production' => $village->getProd('wood')),
	'clay' => array('slot' => 'r2', 'name' => CLAY, 'amount' => $village->aclay, 'capacity' => $village->maxstore, 'production' => $village->getProd('clay')),
	'iron' => array('slot' => 'r3', 'name' => IRON, 'amount' => $village->airon, 'capacity' => $village->maxstore, 'production' => $village->getProd('iron')),
	'crop' => array('slot' => 'r4', 'name' => CROP, 'amount' => max(0, $village->acrop), 'capacity' => $village->maxcrop, 'production' => $cropNetProduction),
);

/* ?resource_preview=digits fuerza numeros largos para ver si siguen entrando en la celda. */
if (isset($_GET['resource_preview']) && $_GET['resource_preview'] === 'digits') {
	$previewAmounts = array(
		'wood' => array(120000, 160000, 9999),
		'clay' => array(1234567, 8000000, 45678),
		'iron' => array(12345678, 80000000, 123456),
		'crop' => array(87654321, 90000000, -1234),
	);
	foreach ($previewAmounts as $previewKey => $previewValues) {
		$resourceSlots[$previewKey]['amount'] = $previewValues[0];
		$resourceSlots[$previewKey]['capacity'] = $previewValues[1];
		$resourceSlots[$previewKey]['production'] = $previewValues[2];
	}
}

$resourceBoardState = array();
foreach ($resourceSlots as $resourceKey => $slot) {
	$resourceBoardState[] = array(
		'key' => $resourceKey,
		'amount' => (float) $slot['amount'],
		'capacity' => (float) $slot['capacity'],
		'production' => (float) $slot['production'],
	);
}
?>
<ul id="res">
<?php foreach ($resourceSlots as $resourceKey => $slot) {
	$capacity = max(1.0, (float) $slot['capacity']);
	$amount = (float) $slot['amount'];
	$production = (float) $slot['production'];
	$percent = min(100, max(0, $amount * 100 / $capacity));
	$barClass = '';
	if ($amount >= $capacity) {
		$barClass = ' resBarFull';
		$clock = 'Lleno';
	} elseif ($production < 0) {
		$barClass = ' resBarDraining';
		$clock = $amount > 0 ? $formatClock($amount * 3600 / -$production) : '0:00:00';
	} elseif ($production == 0) {
		$clock = '&ndash;';
	} else {
		$clock = $formatClock(($capacity - $amount) * 3600 / $production);
	}
	$fillTime = $formatStorageFillTime($capacity, $amount, $production);
?>
	<li class="<?php echo $slot['slot']; ?>" title="<div style=color:#FFF><b><?php echo $slot['name']; ?></b></div><?php echo $fillTime; ?>">
		<span class="resAmount"><img src="img/x.gif" alt="<?php echo $slot['name']; ?>" /><span class="resValue" id="resValue_<?php echo $resourceKey; ?>"><?php echo $formatResourceAmount($amount); ?></span></span>
		<span class="resBar<?php echo $barClass; ?>" id="resBar_<?php echo $resourceKey; ?>"><span class="resBarFill" id="resFill_<?php echo $resourceKey; ?>" style="width:<?php echo number_format($percent, 2, '.', ''); ?>%;"></span><span class="resBarClock" id="resClock_<?php echo $resourceKey; ?>"><?php echo $clock; ?></span></span>
		<span class="resFoot"><span class="resRate">/h: <?php echo $formatResourceAmount($production); ?></span><span class="resMax">máx <?php echo $formatResourceAmount($slot['capacity']); ?></span></span>
	</li>
<?php } ?>
	<li class="r5" title="<div style=color:#FFF><b><?php echo CROP_COM; ?></b></div><?php echo 'Consumo: ' . $formatResourceAmount($cropUpkeep) . ' de ' . $formatResourceAmount($cropGrossProduction) . ' de producción'; ?>">
		<span class="resAmount"><img src="img/x.gif" alt="<?php echo CROP_COM; ?>" /><span class="resValue"><?php echo $formatResourceAmount($cropUpkeep); ?></span></span>
		<span class="resFoot resFootWide"><span class="resMax">prod <?php echo $formatResourceAmount($cropGrossProduction); ?></span></span>
		<span class="resFoot"><span class="resMax<?php echo $cropNetProduction < 0 ? ' resNegative' : ''; ?>">libre <?php echo $formatResourceAmount($cropNetProduction); ?></span></span>
	</li>
</ul>
<div class="clear"></div>

<script type="text/javascript">
	window.resourceBoardState = <?php echo json_encode($resourceBoardState, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
