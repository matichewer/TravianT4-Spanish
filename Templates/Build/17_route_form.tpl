<?php
// Formulario compartido por 17_create.tpl y 17_edit.tpl: mismos campos en los dos casos
// (destino, recursos, uno o varios horarios, envios), asi que editar una ruta permite
// modificar exactamente lo mismo que crearla, incluido el destino y agregar horarios
// extra (que se guardan como rutas nuevas con el mismo destino/recursos/envios).
//
// Variables que espera, definidas por el que incluye:
//   $routeFormAction    'addRoute' | 'editRoute'
//   $routeFormOriginalRouteIds array de ids reales del grupo que se edita, en el mismo
//                       orden que $routeFormSchedules; vacio en creacion. El horario en
//                       la posicion N del formulario actualiza el id en la posicion N de
//                       este array (o crea una fila nueva si no hay id en esa posicion);
//                       los ids que sobran al guardar se borran. Ver procTradeRoutes().
//   $routeFormHeading   string
//   $routeFormTarget    int, aldea de destino seleccionada (0 = ninguna todavia)
//   $routeFormResource  array [wood,clay,iron,crop]
//   $routeFormDeliveries int
//   $routeFormSchedules array de ['hour'=>int,'minute'=>int], al menos uno

$routeFormResourceLabels = array(1=>'Madera',2=>'Barro',3=>'Hierro',4=>'Cereal');

// Aldeas propias primero; jugador antes de la aldea, como en el envio de recursos y tropas.
$routeFormTargetOptions = array();
$routeFormTripSeconds = array();
foreach(tradeRouteDestinations($session->uid, $village->wid) as $destination) {
    $candidateWid = (int)$destination['wref'];
    $routeFormTripSeconds[$candidateWid] = Automation::routeTripSeconds($village->wid,$candidateWid,$session->tribe,1);
    $coor = $database->getCoor($candidateWid);
    $label = $destination['username'].': '.$destination['name'].' ('.(int)$coor['x'].'|'.(int)$coor['y'].')';
    $label .= (int)$destination['owner'] === (int)$session->uid ? ' — Propia' : '';
    $routeFormTargetOptions[$candidateWid] = htmlspecialchars($label,ENT_QUOTES,'UTF-8');
}
// Sin destino confirmado todavia (creacion): preseleccionar la primera aldea disponible,
// igual que antes.
if(!$routeFormTarget && !empty($routeFormTargetOptions)) {
    $routeFormTargetKeys = array_keys($routeFormTargetOptions);
    $routeFormTarget = (int)$routeFormTargetKeys[0];
}
?>
<form action="build.php" method="post" class="routeForm">
    <input type="hidden" name="action" value="<?php echo htmlspecialchars($routeFormAction,ENT_QUOTES,'UTF-8'); ?>">
    <input type="hidden" name="a" value="<?php echo htmlspecialchars((string)$session->mchecker,ENT_QUOTES,'UTF-8'); ?>">
    <?php foreach($routeFormOriginalRouteIds as $routeFormOriginalId) { ?>
    <input type="hidden" name="original_routeid[]" value="<?php echo (int)$routeFormOriginalId; ?>">
    <?php } ?>
    <h3 class="routeFormTitle"><?php echo htmlspecialchars($routeFormHeading,ENT_QUOTES,'UTF-8'); ?></h3>

    <div class="routeFormPanel">

        <div class="routeFormTopGrid">
            <div class="routeFormField">
                <label>Recursos</label>
                <table class="routeFormResourceTable" cellpadding="0" cellspacing="0">
                    <?php foreach($routeFormResourceLabels as $resIndex => $resLabel) { ?>
                    <tr>
                        <td class="routeFormResIco"><img src="<?php echo GP_LOCATE; ?>img/r/<?php echo $resIndex; ?>.gif" alt="<?php echo $resLabel; ?>" title="<?php echo $resLabel; ?>"></td>
                        <td class="routeFormResName"><?php echo $resLabel; ?></td>
                        <td class="routeFormResVal"><input class="text" type="text" inputmode="numeric" name="r<?php echo $resIndex; ?>" id="r<?php echo $resIndex; ?>" maxlength="5" tabindex="<?php echo $resIndex; ?>" value="<?php echo (int)$routeFormResource[$resIndex - 1]; ?>"></td>
                        <td class="routeFormResMax">/ <button type="button" class="routeFormResAdd" data-res="<?php echo $resIndex; ?>" title="Sumar la capacidad de un mercader"><?php echo (int)$market->maxcarry; ?></button></td>
                    </tr>
                    <?php } ?>
                </table>
            </div>

            <div class="routeFormSideCol">
                <div class="routeFormField">
                    <label for="routeFormTarget">Aldea de destino (propia o de tu alianza)</label>
                    <select id="routeFormTarget" name="tvillage" required>
                        <?php if(empty($routeFormTargetOptions)) { ?><option value="">No hay aldeas propias o aliadas disponibles</option><?php } ?>
                        <?php foreach($routeFormTargetOptions as $optionValue => $optionLabel) { ?>
                        <option value="<?php echo (int)$optionValue; ?>"<?php echo ((int)$optionValue === (int)$routeFormTarget) ? ' selected="selected"' : ''; ?>><?php echo $optionLabel; ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="routeFormField">
                    <label for="routeFormDeliveries">Envíos</label>
                    <select id="routeFormDeliveries" name="deliveries">
                        <?php for($d = 1; $d <= 3; $d++) { ?><option value="<?php echo $d; ?>"<?php echo ($d === (int)$routeFormDeliveries) ? ' selected="selected"' : ''; ?>><?php echo $d; ?></option><?php } ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="routeFormField">
            <label for="routeFormFrequency">Frecuencia</label>
            <select id="routeFormFrequency">
                <option value="manual">Manual</option>
                <option value="fastest">Lo antes posible (ida y vuelta)</option>
                <?php foreach(array(15=>'Cada 15 minutos',30=>'Cada media hora',60=>'Cada hora',120=>'Cada 2 horas',180=>'Cada 3 horas',240=>'Cada 4 horas',360=>'Cada 6 horas',720=>'Cada 12 horas') as $interval => $label) { ?>
                <option value="<?php echo $interval; ?>"><?php echo $label; ?></option>
                <?php } ?>
            </select>
            <div id="routeFormAutomatic" hidden>
                <label for="routeFormFirst">Primera salida del día</label>
                <input type="time" id="routeFormFirst" value="00:00" step="300">
                <button type="button" id="routeFormGenerate">Generar horarios</button>
            </div>
            <p id="routeFormFrequencyHelp" aria-live="polite"></p>
        </div>

        <div class="routeFormField">
            <div class="routeFormSchedulesHeader">
                <label id="routeFormSchedulesLabel">Horarios de salida</label>
                <button type="button" id="routeFormAddSchedule" class="routeFormAddSchedule">+ agregar horario</button>
            </div>
            <div class="routeFormSchedules" id="routeFormSchedules" aria-labelledby="routeFormSchedulesLabel">
                <?php foreach($routeFormSchedules as $schedule) { ?>
                <div class="routeFormSchedule">
                    <select name="schedule_hour[]" aria-label="Hora">
                        <?php for($h = 0; $h <= 23; $h++) { ?><option value="<?php echo $h; ?>"<?php echo ($h === (int)$schedule['hour']) ? ' selected="selected"' : ''; ?>><?php echo sprintf('%02d',$h); ?></option><?php } ?>
                    </select>
                    <span class="routeFormScheduleSep">:</span>
                    <select name="schedule_minute[]" aria-label="Minuto">
                        <?php for($m = 0; $m <= 55; $m += 5) { ?><option value="<?php echo $m; ?>"<?php echo ($m === (int)$schedule['minute']) ? ' selected="selected"' : ''; ?>><?php echo sprintf('%02d',$m); ?></option><?php } ?>
                    </select>
                    <button type="button" class="routeFormScheduleRemove" title="Quitar este horario" aria-label="Quitar este horario">&times;</button>
                </div>
                <?php } ?>
            </div>
        </div>

    </div>
    <p><button type="submit" value="save"><div class="button-container"><div class="button-position"><div class="btl"><div class="btr"><div class="btc"></div></div></div><div class="bml"><div class="bmr"><div class="bmc"></div></div></div><div class="bbl"><div class="bbr"><div class="bbc"></div></div></div></div><div class="button-contents">guardar</div></div></button></p>
</form>

<template id="routeFormScheduleTemplate">
    <div class="routeFormSchedule">
        <select name="schedule_hour[]" aria-label="Hora">
            <?php for($h = 0; $h <= 23; $h++) { ?><option value="<?php echo $h; ?>"><?php echo sprintf('%02d',$h); ?></option><?php } ?>
        </select>
        <span class="routeFormScheduleSep">:</span>
        <select name="schedule_minute[]" aria-label="Minuto">
            <?php for($m = 0; $m <= 55; $m += 5) { ?><option value="<?php echo $m; ?>"><?php echo sprintf('%02d',$m); ?></option><?php } ?>
        </select>
        <button type="button" class="routeFormScheduleRemove" title="Quitar este horario" aria-label="Quitar este horario">&times;</button>
    </div>
</template>
<script>
(function(){
    var list = document.getElementById('routeFormSchedules');
    var addBtn = document.getElementById('routeFormAddSchedule');
    var tpl = document.getElementById('routeFormScheduleTemplate');
    if(!list || !addBtn || !tpl) { return; }
    var MAX_SCHEDULES = <?php echo (int)Market::MAX_ROUTE_SCHEDULES; ?>;
    var panel = document.querySelector('.routeFormPanel');
    var panelMaxHeight = 0;

    // El panel gris crece a medida que se agregan horarios (envuelven a otra fila),
    // pero no se achica al borrar uno: volver a un alto menor cada vez que se quita
    // un horario hace que el resto del formulario "salte" hacia arriba, que es mas
    // molesto que dejar aire de mas. Solo vuelve a su alto natural en una carga de
    // pagina nueva (guardar y volver a editar, por ejemplo).
    function ratchetPanelHeight(){
        if(!panel){ return; }
        panel.style.minHeight = '';
        var naturalHeight = panel.getBoundingClientRect().height;
        panelMaxHeight = Math.max(panelMaxHeight, naturalHeight);
        panel.style.minHeight = panelMaxHeight + 'px';
    }

    function refresh(){
        var rows = list.querySelectorAll('.routeFormSchedule');
        rows.forEach(function(row){
            var removeBtn = row.querySelector('.routeFormScheduleRemove');
            removeBtn.style.display = rows.length > 1 ? '' : 'none';
        });
        addBtn.style.display = rows.length >= MAX_SCHEDULES ? 'none' : '';
        ratchetPanelHeight();
    }

    list.addEventListener('click', function(event){
        if(!event.target.classList.contains('routeFormScheduleRemove')) { return; }
        var rows = list.querySelectorAll('.routeFormSchedule');
        if(rows.length <= 1) { return; }
        event.target.closest('.routeFormSchedule').remove();
        refresh();
    });

    // Horario "inteligente" para el proximo agregado: extiende el espacio entre los
    // dos ultimos horarios ya cargados (00, 02 -> siguiente 04; 01, 05 -> siguiente
    // 09), no siempre 00:00. Con un solo horario cargado no hay espacio que medir,
    // asi que se asume 1 hora. Lee los selects al momento del click, asi que si el
    // jugador edito un horario antes de agregar el siguiente, usa ese valor.
    function nextScheduleDefault(){
        var times = [];
        list.querySelectorAll('.routeFormSchedule').forEach(function(row){
            var hourSel = row.querySelector('select[name="schedule_hour[]"]');
            var minSel = row.querySelector('select[name="schedule_minute[]"]');
            if(hourSel && minSel){
                times.push(parseInt(hourSel.value, 10) * 60 + parseInt(minSel.value, 10));
            }
        });
        if(times.length === 0){ return {hour: 0, minute: 0}; }
        var last = times[times.length - 1];
        var gap = 60;
        if(times.length >= 2){
            gap = ((last - times[times.length - 2]) % 1440 + 1440) % 1440;
            if(gap === 0){ gap = 60; }
        }
        var next = (last + gap) % 1440;
        var hour = Math.floor(next / 60);
        var minute = Math.round((next % 60) / 5) * 5;
        if(minute === 60){ minute = 0; hour = (hour + 1) % 24; }
        return {hour: hour, minute: minute};
    }

    addBtn.addEventListener('click', function(){
        if(list.querySelectorAll('.routeFormSchedule').length >= MAX_SCHEDULES) { return; }
        var next = nextScheduleDefault();
        var clone = tpl.content.cloneNode(true);
        var hourSel = clone.querySelector('select[name="schedule_hour[]"]');
        var minSel = clone.querySelector('select[name="schedule_minute[]"]');
        if(hourSel){ hourSel.value = String(next.hour); }
        if(minSel){ minSel.value = String(next.minute); }
        // El boton vive fuera de la lista de horarios (al lado del titulo, en un lugar
        // fijo) para poder clickearlo varias veces seguidas sin que se corra de lugar
        // a medida que se van agregando pildoras.
        list.appendChild(clone);
        refresh();
    });

    var frequency = document.getElementById('routeFormFrequency');
    var target = document.getElementById('routeFormTarget');
    var deliveries = document.getElementById('routeFormDeliveries');
    var automatic = document.getElementById('routeFormAutomatic');
    var first = document.getElementById('routeFormFirst');
    var generate = document.getElementById('routeFormGenerate');
    var help = document.getElementById('routeFormFrequencyHelp');
    var trips = <?php echo json_encode($routeFormTripSeconds); ?>;

    // Daily slots: leave at least one full interval across midnight too.
    function frequencySchedules(start, interval) {
        if(!Number.isInteger(start) || start < 0 || start >= 1440 ||
            !Number.isInteger(interval) || interval < 5 || interval > 1440) { return []; }
        var result = [];
        for(var minute = start; minute < 1440 && result.length < Math.floor(1440 / interval); minute += interval) {
            result.push(minute);
        }
        return result;
    }

    function refreshFrequency() {
        var seconds = Number(trips[target.value]) * Number(deliveries.value);
        var minimum = Math.max(5, Math.ceil(seconds / 300) * 5);
        var available = seconds > 0 && minimum <= 1440;
        Array.prototype.forEach.call(frequency.options, function(option) {
            option.disabled = option.value !== 'manual' && (!available ||
                (option.value !== 'fastest' && Number(option.value) < minimum));
        });
        if(frequency.selectedOptions[0].disabled) { frequency.value = 'manual'; }
        automatic.hidden = frequency.value === 'manual';
        generate.disabled = !available;
        help.textContent = available
            ? 'Intervalo mínimo automático: ' + minimum + ' min (ida y vuelta × envíos, redondeado a 5 minutos). Generar reemplaza los horarios; se repiten cada día. Manual permite ajustarlos libremente.'
            : 'No hay una frecuencia diaria sin superposición para este destino y cantidad de envíos. Podés configurar horarios manuales si tenés suficientes mercaderes.';
        return minimum;
    }
    frequency.addEventListener('change', refreshFrequency);
    target.addEventListener('change', refreshFrequency);
    deliveries.addEventListener('change', refreshFrequency);
    generate.addEventListener('click', function() {
        var minimum = refreshFrequency();
        if(frequency.value === 'manual') { return; }
        var parts = first.value.split(':');
        var start = Number(parts[0]) * 60 + Number(parts[1]);
        if(parts.length !== 2 || start % 5 !== 0) {
            help.textContent = 'Elegí una primera salida en pasos de 5 minutos.';
            return;
        }
        var interval = frequency.value === 'fastest' ? minimum : Number(frequency.value);
        var times = frequencySchedules(start, interval);
        if(!times.length) { return; }
        list.innerHTML = '';
        times.forEach(function(minute) {
            var clone = tpl.content.cloneNode(true);
            clone.querySelector('select[name="schedule_hour[]"]').value = String(Math.floor(minute / 60));
            clone.querySelector('select[name="schedule_minute[]"]').value = String(minute % 60);
            list.appendChild(clone);
        });
        refresh();
        help.textContent += ' ' + times.length + ' salidas diarias. Pausa hasta la primera del día siguiente: ' + (1440 + times[0] - times[times.length - 1]) + ' min. Se guardan al pulsar guardar.';
    });
    list.addEventListener('change', function() { frequency.value = 'manual'; refreshFrequency(); });
    list.addEventListener('click', function(event) {
        if(event.target.classList.contains('routeFormScheduleRemove')) { frequency.value = 'manual'; refreshFrequency(); }
    });
    addBtn.addEventListener('click', function() { frequency.value = 'manual'; refreshFrequency(); });
    refreshFrequency();
    refresh();
})();
</script>
<script>
(function(){
    // "/ <capacidad>" funciona como en la pestaña Resumen: cada click suma la carga de
    // un mercader al recurso. A diferencia de Resumen, el tope no es "mercaderes libres
    // ahora mismo" (los de una ruta se ocupan recien cuando sale, no de inmediato): es
    // "todos los mercaderes del edificio a la vez", el maximo que un solo horario podria
    // llegar a necesitar sin importar como se acomoden los horarios entre si. El tope
    // real (que tiene en cuenta el solapamiento entre horarios) se valida en el servidor.
    var maxcarry = <?php echo (int)$market->maxcarry; ?>;
    var maxTotal = <?php echo (int)($market->merchant * $market->maxcarry); ?>;
    var resAddButtons = document.querySelectorAll('.routeFormResAdd');
    var resInputs = Array.prototype.map.call(resAddButtons, function(btn){
        return document.getElementById('r' + btn.getAttribute('data-res'));
    });

    function totalExcept(input) {
        var total = 0;
        resInputs.forEach(function(other){
            if(other === input) { return; }
            var value = parseInt(other.value, 10);
            if(!isNaN(value) && value > 0) { total += value; }
        });
        return total;
    }

    function refreshResButtons() {
        var atCap = totalExcept(null) >= maxTotal;
        resAddButtons.forEach(function(btn){ btn.disabled = atCap; });
    }

    resAddButtons.forEach(function(btn){
        btn.addEventListener('click', function(){
            var input = document.getElementById('r' + btn.getAttribute('data-res'));
            var current = parseInt(input.value, 10);
            if(isNaN(current)) { current = 0; }
            var roomLeft = Math.max(0, maxTotal - totalExcept(input));
            input.value = current + Math.min(maxcarry, Math.max(0, roomLeft - current));
            refreshResButtons();
        });
    });

    refreshResButtons();
})();
</script>
</div>
