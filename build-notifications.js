(function(window, document) {
	'use strict';

	var MAX_TIMEOUT = 2147483647;
	var STALE_JOB_MS = 24 * 60 * 60 * 1000;
	var NOTIFIED_RETENTION_MS = 7 * 24 * 60 * 60 * 1000;
	var STORAGE_PREFIX = 'travian.buildNotifications.';
	var accountId = null;
	var serverName = 'Travian';
	var clockOffsetMs = 0;
	var state = createState();
	var timers = {};
	var configured = false;

	function createState() {
		return {
			enabled: false,
			jobs: {},
			notified: {}
		};
	}

	function storageKey() {
		return STORAGE_PREFIX + accountId;
	}

	function loadState() {
		var stored;
		var parsed;

		if (accountId === null) {
			return createState();
		}

		try {
			stored = window.localStorage.getItem(storageKey());
			parsed = stored ? JSON.parse(stored) : null;
		} catch (error) {
			parsed = null;
		}

		if (!parsed || typeof parsed !== 'object') {
			return createState();
		}

		return {
			enabled: parsed.enabled === true,
			jobs: parsed.jobs && typeof parsed.jobs === 'object' ? parsed.jobs : {},
			notified: parsed.notified && typeof parsed.notified === 'object' ? parsed.notified : {}
		};
	}

	function saveState() {
		if (accountId === null) {
			return;
		}

		try {
			window.localStorage.setItem(storageKey(), JSON.stringify(state));
		} catch (error) {
			// Notifications still work on the current page when storage is unavailable.
		}
	}

	function serverNowMs() {
		return Date.now() + clockOffsetMs;
	}

	function jobKey(job) {
		return String(job.villageId) + ':' + String(job.id);
	}

	function normalizeJob(rawJob) {
		var job = {
			id: parseInt(rawJob.id, 10),
			villageId: parseInt(rawJob.villageId, 10),
			village: String(rawJob.village || ''),
			building: String(rawJob.building || ''),
			level: parseInt(rawJob.level, 10),
			finishAt: parseInt(rawJob.finishAt, 10),
			url: String(rawJob.url || 'dorf1.php')
		};

		if (!job.id || !job.villageId || !job.finishAt || isNaN(job.level)) {
			return null;
		}

		return job;
	}

	function notificationsAvailable() {
		return 'Notification' in window && window.isSecureContext !== false;
	}

	function notificationsGranted() {
		return notificationsAvailable() && window.Notification.permission === 'granted';
	}

	function clearTimer(key) {
		if (timers[key]) {
			window.clearTimeout(timers[key]);
			delete timers[key];
		}
	}

	function clearAllTimers() {
		var key;
		for (key in timers) {
			if (Object.prototype.hasOwnProperty.call(timers, key)) {
				window.clearTimeout(timers[key]);
			}
		}
		timers = {};
	}

	function cleanupState() {
		var key;
		var now = serverNowMs();

		for (key in state.jobs) {
			if (Object.prototype.hasOwnProperty.call(state.jobs, key) &&
				(state.jobs[key].finishAt * 1000) < now - STALE_JOB_MS) {
				delete state.jobs[key];
				clearTimer(key);
			}
		}

		for (key in state.notified) {
			if (Object.prototype.hasOwnProperty.call(state.notified, key) &&
				state.notified[key] < Date.now() - NOTIFIED_RETENTION_MS) {
				delete state.notified[key];
			}
		}
	}

	function notificationBody(job) {
		var description = job.building + ' nivel ' + job.level;
		if (job.village) {
			description += ' en ' + job.village;
		}
		return description + '.';
	}

	function displayNotification(key, job) {
		var notification;

		state.notified[key] = Date.now();
		delete state.jobs[key];
		saveState();

		try {
			notification = new window.Notification(serverName + ': construcción finalizada', {
				body: notificationBody(job),
				icon: 'favicon.ico',
				tag: 'travian-building-' + accountId + '-' + job.id
			});

			notification.onclick = function() {
				window.focus();
				window.location.href = job.url;
				notification.close();
			};
		} catch (error) {
			delete state.notified[key];
			state.jobs[key] = job;
			saveState();
		}
	}

	function finishJob(key) {
		var job;
		var remaining;

		delete timers[key];
		state = loadState();
		job = state.jobs[key];

		if (!job || state.notified[key] || !state.enabled || !notificationsGranted()) {
			return;
		}

		remaining = (job.finishAt * 1000) - serverNowMs();
		if (remaining > 0) {
			scheduleJob(key);
			return;
		}

		displayNotification(key, job);
	}

	function scheduleJob(key) {
		var job = state.jobs[key];
		var delay;

		clearTimer(key);
		if (!job || state.notified[key] || !state.enabled || !notificationsGranted()) {
			return;
		}

		delay = Math.max(0, (job.finishAt * 1000) - serverNowMs());
		if (delay > MAX_TIMEOUT) {
			delay = MAX_TIMEOUT;
		}

		timers[key] = window.setTimeout(function() {
			finishJob(key);
		}, delay);
	}

	function scheduleAll() {
		var key;

		clearAllTimers();
		if (!state.enabled || !notificationsGranted()) {
			return;
		}

		for (key in state.jobs) {
			if (Object.prototype.hasOwnProperty.call(state.jobs, key)) {
				scheduleJob(key);
			}
		}
	}

	function controlText() {
		if (!notificationsAvailable()) {
			return 'Avisos no disponibles';
		}
		if (window.Notification.permission === 'denied') {
			return 'Avisos bloqueados';
		}
		if (state.enabled && notificationsGranted()) {
			return 'Desactivar avisos';
		}
		return 'Activar avisos';
	}

	function updateControls() {
		var controls = document.getElementsByClassName('buildingNotificationToggle');
		var text = controlText();
		var enabled = state.enabled && notificationsGranted();
		var i;

		for (i = 0; i < controls.length; i++) {
			controls[i].innerHTML = text;
			controls[i].setAttribute('aria-pressed', enabled ? 'true' : 'false');
		}
	}

	function enableNotifications() {
		if (!notificationsAvailable()) {
			window.alert('Este navegador no permite notificaciones para esta página. En producción, abre el juego mediante HTTPS.');
			return;
		}

		if (window.Notification.permission === 'denied') {
			window.alert('Las notificaciones están bloqueadas. Puedes habilitarlas desde la configuración del sitio en el navegador.');
			return;
		}

		if (window.Notification.permission === 'granted') {
			state.enabled = true;
			saveState();
			scheduleAll();
			updateControls();
			return;
		}

		window.Notification.requestPermission().then(function(permission) {
			if (permission === 'granted') {
				state.enabled = true;
				saveState();
				scheduleAll();
			}
			updateControls();
		});
	}

	function disableNotifications() {
		state.enabled = false;
		saveState();
		clearAllTimers();
		updateControls();
	}

	function toggleNotifications(event) {
		if (event) {
			event.preventDefault();
		}

		if (state.enabled && notificationsGranted()) {
			disableNotifications();
		} else {
			enableNotifications();
		}
	}

	function bindControls() {
		var controls = document.getElementsByClassName('buildingNotificationToggle');
		var i;

		for (i = 0; i < controls.length; i++) {
			if (!controls[i]._travianNotificationBound) {
				controls[i]._travianNotificationBound = true;
				controls[i].addEventListener('click', toggleNotifications, false);
			}
		}
		updateControls();
	}

	function configure(options) {
		var nextAccountId = String(options.accountId);

		if (configured && accountId !== nextAccountId) {
			clearAllTimers();
		}

		accountId = nextAccountId;
		serverName = String(options.serverName || serverName);
		clockOffsetMs = (parseInt(options.serverNow, 10) * 1000) - Date.now();
		state = loadState();
		configured = true;
		cleanupState();
		saveState();
		scheduleAll();
		bindControls();
	}

	function syncVillage(villageId, rawJobs, serverNow) {
		var normalizedVillageId = parseInt(villageId, 10);
		var incoming = {};
		var key;
		var job;
		var i;

		if (!configured || !normalizedVillageId) {
			return;
		}

		clockOffsetMs = (parseInt(serverNow, 10) * 1000) - Date.now();
		state = loadState();

		for (i = 0; i < rawJobs.length; i++) {
			job = normalizeJob(rawJobs[i]);
			if (job) {
				key = jobKey(job);
				incoming[key] = true;
				state.jobs[key] = job;
			}
		}

		for (key in state.jobs) {
			if (Object.prototype.hasOwnProperty.call(state.jobs, key) &&
				parseInt(state.jobs[key].villageId, 10) === normalizedVillageId && !incoming[key]) {
				clearTimer(key);
				if ((state.jobs[key].finishAt * 1000) <= serverNowMs() &&
					!state.notified[key] && state.enabled && notificationsGranted()) {
					displayNotification(key, state.jobs[key]);
				} else {
					delete state.jobs[key];
				}
			}
		}

		cleanupState();
		saveState();
		scheduleAll();
		bindControls();
	}

	window.TravianBuildNotifications = {
		configure: configure,
		syncVillage: syncVillage
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bindControls, false);
	} else {
		bindControls();
	}
})(window, document);
