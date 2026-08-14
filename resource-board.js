/* Tablero de recursos del encabezado (Templates/res.tpl).

   Cada segundo recalcula la cantidad a partir de la produccion por hora que mando
   el servidor, mueve la barra y actualiza el reloj de "cuanto falta para llenar el
   deposito" que va adentro de la barra. Con produccion negativa (cereal en rojo)
   el reloj cuenta lo que falta para vaciarlo.

   Reemplaza a initTimer()/executeTimer() de crypt.js, que esperaban el formato
   viejo "1.898 / 11.800" en un unico <span id="l1">. Aquellos ids ya no existen,
   asi que initTimer no encuentra nada y no hace nada. */
(function () {
	'use strict';

	var TICK_MS = 1000;
	var MS_PER_HOUR = 3600000;

	function formatAmount(value) {
		return String(Math.round(value)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
	}

	function pad(value) {
		return value < 10 ? '0' + value : String(value);
	}

	function formatClock(seconds) {
		seconds = Math.max(0, Math.round(seconds));
		var hours = Math.floor(seconds / 3600);
		if (hours > 999) {
			return '999+';
		}

		return hours + ':' + pad(Math.floor(seconds / 60) % 60) + ':' + pad(seconds % 60);
	}

	function setClass(node, className, enabled) {
		if (!node) {
			return;
		}

		var classes = node.className.replace(new RegExp('\\s*' + className, 'g'), '');
		node.className = enabled ? classes + ' ' + className : classes;
	}

	function collect(state) {
		var tracked = [];

		for (var i = 0; i < state.length; i++) {
			var entry = state[i];
			var value = document.getElementById('resValue_' + entry.key);
			if (!value) {
				continue;
			}

			tracked.push({
				amount: entry.amount,
				capacity: entry.capacity,
				production: entry.production,
				value: value,
				bar: document.getElementById('resBar_' + entry.key),
				fill: document.getElementById('resFill_' + entry.key),
				clock: document.getElementById('resClock_' + entry.key)
			});
		}

		return tracked;
	}

	function update(tracked, startedAt) {
		var elapsedHours = (Date.now() - startedAt) / MS_PER_HOUR;

		for (var i = 0; i < tracked.length; i++) {
			var res = tracked[i];
			var capacity = res.capacity > 0 ? res.capacity : 1;
			var amount = res.amount + res.production * elapsedHours;

			if (amount > res.capacity) {
				amount = res.capacity;
			}
			if (amount < 0) {
				amount = 0;
			}

			res.value.innerHTML = formatAmount(amount);

			if (res.fill) {
				res.fill.style.width = Math.min(100, Math.max(0, amount * 100 / capacity)) + '%';
			}

			var full = amount >= res.capacity;
			var draining = res.production < 0;
			setClass(res.bar, 'resBarFull', full);
			setClass(res.bar, 'resBarDraining', !full && draining);

			if (!res.clock) {
				continue;
			}

			if (full) {
				res.clock.innerHTML = 'Lleno';
			} else if (draining) {
				res.clock.innerHTML = formatClock(amount * 3600 / -res.production);
			} else if (res.production === 0) {
				res.clock.innerHTML = '&ndash;';
			} else {
				res.clock.innerHTML = formatClock((res.capacity - amount) * 3600 / res.production);
			}
		}
	}

	function start() {
		if (!window.resourceBoardState || !window.resourceBoardState.length) {
			return;
		}

		var tracked = collect(window.resourceBoardState);
		if (!tracked.length) {
			return;
		}

		/* El estado viene calculado al momento en que el servidor armo la pagina, asi
		   que el punto de partida es la carga y no cada tick: si la pestania se duerme
		   el valor se recalcula igual al volver. */
		var startedAt = Date.now();
		update(tracked, startedAt);
		window.setInterval(function () {
			update(tracked, startedAt);
		}, TICK_MS);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
