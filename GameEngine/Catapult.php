<?php

/** Shared server/UI policy for explicitly selectable catapult targets. */
function catapultTargetCatalog() {
    return array(
        1=>array('name'=>'Leñador','level'=>5), 2=>array('name'=>'Excavación de barro','level'=>5),
        3=>array('name'=>'Mina de hierro','level'=>5), 4=>array('name'=>'Campo de cereal','level'=>5),
        5=>array('name'=>'Aserradero','level'=>5), 6=>array('name'=>'Fábrica de ladrillos','level'=>5),
        7=>array('name'=>'Fundición de hierro','level'=>5), 8=>array('name'=>'Molino','level'=>5),
        9=>array('name'=>'Panadería','level'=>5), 10=>array('name'=>'Almacén','level'=>3),
        11=>array('name'=>'Granero','level'=>3), 12=>array('name'=>'Herrería','level'=>10),
        14=>array('name'=>'Plaza de torneos','level'=>10), 15=>array('name'=>'Edificio principal','level'=>10),
        16=>array('name'=>'Plaza de reuniones','level'=>10), 17=>array('name'=>'Mercado','level'=>10),
        18=>array('name'=>'Embajada','level'=>10), 19=>array('name'=>'Cuartel','level'=>10),
        20=>array('name'=>'Establo','level'=>10), 21=>array('name'=>'Taller','level'=>10),
        22=>array('name'=>'Academia','level'=>10), 23=>array('name'=>'Escondite','level'=>PHP_INT_MAX),
        24=>array('name'=>'Ayuntamiento','level'=>10), 25=>array('name'=>'Residencia','level'=>10),
        26=>array('name'=>'Palacio','level'=>10), 27=>array('name'=>'Tesorería','level'=>10),
        28=>array('name'=>'Oficina de comercio','level'=>10), 29=>array('name'=>'Gran cuartel','level'=>10),
        30=>array('name'=>'Gran establo','level'=>10), 34=>array('name'=>'Taller de cantería','level'=>PHP_INT_MAX),
        35=>array('name'=>'Cervecería','level'=>10), 36=>array('name'=>'Trampero','level'=>PHP_INT_MAX),
        37=>array('name'=>'Mansión del héroe','level'=>10), 38=>array('name'=>'Gran almacén','level'=>3),
        39=>array('name'=>'Gran granero','level'=>3), 40=>array('name'=>'Maravilla del mundo','level'=>10),
        41=>array('name'=>'Abrevadero','level'=>10), 42=>array('name'=>'Gran taller','level'=>10)
    );
}

function catapultNormalizeTarget($value, $rallyPointLevel, $allowSecondRandom = false) {
    if(!is_scalar($value) || !is_numeric($value)) return 0;
    $target = (int)$value;
    if($target === 0 || ($allowSecondRandom && $target === 99)) return $target;
    $catalog = catapultTargetCatalog();
    return isset($catalog[$target]) && (int)$rallyPointLevel >= $catalog[$target]['level'] ? $target : 0;
}

function catapultIsKnownTarget($value, $allowSecondRandom = false) {
    $target = (int)$value;
    return $target === 0 || ($allowSecondRandom && $target === 99) || isset(catapultTargetCatalog()[$target]);
}
