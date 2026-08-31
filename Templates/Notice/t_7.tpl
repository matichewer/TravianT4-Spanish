<?php
// Las entregas de ruta conservan los tipos 10..13 para reutilizar el informe de
// comercio. La marca al final de `data` distingue el origen automatico sin cambiar
// el esquema; el tipo 26 representa una salida programada que no pudo ejecutarse.
$noticeSqlFilter = "and (ntype = ".Automation::NTYPE_ROUTE_NOT_SENT
    ." or (ntype IN (10,11,12,13) and data LIKE '%,route'))";
include("Templates/Notice/t_2.tpl");
