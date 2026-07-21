<?php
if($_SERVER['REQUEST_METHOD'] === 'POST') {
	$goldInput = isset($_POST['gold']) ? trim((string)$_POST['gold']) : '';
	$silverInput = isset($_POST['silver']) ? trim((string)$_POST['silver']) : '';
	$exchangeCompleted = false;

	if($goldInput !== '' && $silverInput === '' && ctype_digit($goldInput)) {
		$gold = (int)$goldInput;
		$exchangeCompleted = $database->exchangeGoldForSilver($session->uid, $gold);
	} elseif($silverInput !== '' && $goldInput === '' && ctype_digit($silverInput)) {
		$silver = (int)$silverInput;
		$exchangeCompleted = $database->exchangeSilverForGold($session->uid, $silver);
	}

	if(!$exchangeCompleted) {
		$_SESSION['exchange_error'] = 'La cantidad no es válida o no tienes saldo suficiente.';
	}

    header("Location: plus.php?id=6");
	exit;
}

$exchangeError = isset($_SESSION['exchange_error']) ? $_SESSION['exchange_error'] : '';
unset($_SESSION['exchange_error']);
?>

<div id="silverExchange">

	<h3>Casa de cambio</h3>
	<?php if($exchangeError !== '') { ?><p class="error"><?php echo htmlspecialchars($exchangeError, ENT_QUOTES, 'UTF-8'); ?></p><?php } ?>
	<p>Ingresa la cantidad de oro o plata que quieres intercambiar.</p>

	<h4>Tasas de cambio</h4>
	<p>1 oro : 100 plata<br>200 plata : 1 oro</p>
<?php $id = $_SESSION['id']; ?>
<form action="plus.php?id=6" method="post">
<input type="hidden" name="id" id="id" value="<?php echo $id; ?>">
	<div class="boxes boxesColor gray exchange"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">		<table cellpadding="1" cellspacing="1" class="exchangeOffice transparent">
			<tbody>
				<tr>
					<td>
						<img src="img/x.gif" class="gold" alt="Oro de Travian">
						<?php echo $session->gold; ?>
                        </td>
					<td>
						<img src="img/x.gif" class="silver" alt="Plata de Travian">
						<?php echo $session->silver; ?>
                        </td>
				</tr>

				<tr>
					<td>
						<input name="gold" id="goldInput" type="text" class="text" value="">
					</td>
					<td>
						<input name="silver" id="silverInput" type="text" class="text" value="">
					</td>
				</tr>
			</tbody>
		</table>
			</div>
				</div>
		<p>
			<input type="hidden" name="a" value="84">
			<input type="hidden" name="c" value="18a">

			<button type="submit" value="exchange"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">Intercambiar</div></div></button>

            </p>


		<div class="boxes boxesColor gray exchange"><div class="boxes-tl"></div><div class="boxes-tr"></div><div class="boxes-tc"></div><div class="boxes-ml"></div><div class="boxes-mr"></div><div class="boxes-mc"></div><div class="boxes-bl"></div><div class="boxes-br"></div><div class="boxes-bc"></div><div class="boxes-contents">
        <table cellpadding="1" cellspacing="1" class="exchangeOffice transparent">
			<tbody>
				<tr>
					<td>
						<img src="img/x.gif" class="gold" alt="Oro de Travian">
						<span id="goldResult">0</span>
					</td>
					<td>
						<img src="img/x.gif" class="silver" alt="Plata de Travian">
						<span id="silverResult">0</span>
					</td>
				</tr>
			</tbody>
		</table>
			</div>
				</div>	</form>

</div>
<script type="text/javascript">
	window.addEvent('domready', function(){
		new Travian.Game.GoldToSilver({
			elementInputGold: 'goldInput',
			elementInputSilver: 'silverInput',
			elementResultGold: 'goldResult',
			elementResultSilver: 'silverResult',
			gold: <?php echo $session->gold; ?>,
			silver: <?php echo $session->silver; ?>,
			rateGoldToSilver: 100,
			rateSilverToGold: 200
		});
	});
</script>
