-- Migraciones de esquema para servidores YA INSTALADOS.
--
-- Una instalacion desde cero NO necesita este archivo: install/data/config.sql
-- ya crea todas las columnas. Esto es solo para bases creadas antes de que
-- cada columna existiera.
--
-- Todo es idempotente (ADD COLUMN IF NOT EXISTS), asi que se puede correr
-- entero las veces que haga falta sin romper nada ni pisar valores.
--
-- Uso:
--   docker compose exec -T db mariadb -utravian -p<password> travian_t4 < tools/migrations.sql
--
-- El password sale de .env (MARIADB_PASSWORD) o de config/connection.php.
-- Si el prefijo de tablas no es s1_, ajustar los nombres.

-- 2026-07-20 - Tienda de oro con PayPal (commit 108b699)
-- Oculta la tienda PayPal/Liberty Reserve, que no tiene procesador real detras.
-- 0 = oculta, 1 = visible.
ALTER TABLE s1_config
  ADD COLUMN IF NOT EXISTS paypal_gold int(1) NOT NULL DEFAULT 0;

-- 2026-07-20 - Tope de medallas semanales (commit ddf08b3)
-- Cuantos puestos de cada ranking semanal reciben medalla, de 1 a 10.
-- En un servidor chico conviene 1, asi solo se premia al ganador.
ALTER TABLE s1_config
  ADD COLUMN IF NOT EXISTS medal_top int(2) NOT NULL DEFAULT 10,
  ADD COLUMN IF NOT EXISTS medal_ally_top int(2) NOT NULL DEFAULT 10;

-- 2026-07-22 - Sesiones simultaneas en varios dispositivos
-- Conserva hasta 20 tokens de sesion independientes por cuenta.
ALTER TABLE s1_users
  MODIFY COLUMN sessid varchar(2048) NOT NULL;

-- 2026-07-25 - Mensaje informativo al finalizar una subasta
-- Guarda cada oferta aceptada para poder avisar a todos los postores.
CREATE TABLE IF NOT EXISTS s1_auction_bids (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  auction_id int(11) unsigned NOT NULL,
  uid int(11) unsigned NOT NULL,
  max_bid int(11) unsigned NOT NULL,
  price_before int(11) unsigned NOT NULL,
  price_after int(11) unsigned NOT NULL,
  time int(11) unsigned NOT NULL,
  PRIMARY KEY (id),
  KEY auction_id (auction_id),
  KEY uid (uid)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

-- 2026-07-25 - Celebraciones de la Cerveceria
-- Momento hasta el que permanece activa la celebracion de hidromiel de la cuenta.
ALTER TABLE s1_users
  ADD COLUMN IF NOT EXISTS brewery int(11) unsigned NOT NULL DEFAULT 0 AFTER b4;

-- 2026-08-12 - Enfriamiento de las obras de arte
-- Impide consumir más de una obra por cuenta dentro de una ventana móvil de 24 h.
ALTER TABLE s1_users
  ADD COLUMN IF NOT EXISTS artwork_last_used int(11) unsigned NOT NULL DEFAULT 0 AFTER brewery;

-- 2026-07-26 - Soporte de emojis (utf8mb4)
-- El servidor usaba utf8mb3, que no puede guardar caracteres de 4 bytes:
-- los emojis se guardaban como "????" en mensajes, reportes, perfiles, etc.
-- Convierte la base y todas las tablas a utf8mb4. Ejecutar una sola vez.
-- Nota: la conversion cambia las columnas TEXT a MEDIUMTEXT (mismo cupo de
-- caracteres), lo cual es esperado y no requiere accion.
ALTER DATABASE travian_t4 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE s1_a2b CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_abdata CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_activate CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_active CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_admin_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_adventure CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_ali_invite CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_ali_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_ali_permission CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_alidata CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_allimedal CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_artefacts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_attacks CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_auction CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_auction_bids CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_banlist CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_bdata CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_build_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_chat CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_config CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_deleting CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_demolition CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_destroy_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_diplomacy CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_enforcement CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_farmlist CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_fdata CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_forum_cat CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_forum_edit CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_forum_post CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_forum_topic CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_general CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_gold_fin_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_hero CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_heroface CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_heroinventory CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_heroitems CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_illegal_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_links CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_login_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_market CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_market_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_mdata CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_medal CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_movement CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_ndata CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_newproc CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_odata CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_online CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_prisoners CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_raidlist CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_research CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_route CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_send CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_tdata CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_tech_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_training CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_units CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_vdata CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_wdata CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE s1_ww_attacks CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- 2026-07-26 - Ventana de retorno para la evasion del Club de Oro
-- Evita recorrer toda la tabla de movimientos por cada ataque entrante.
ALTER TABLE s1_movement
  ADD INDEX IF NOT EXISTS evasion_return_window (`to`, sort_type, endtime, `from`);

-- 2026-07-31 - Rebalanceo de animales en oasis
-- En mundos existentes reduce una unica vez a la mitad los animales de oasis
-- libres. El indicador vuelve idempotente esta migracion; mundos nuevos nacen
-- con el indicador en 1 y usan directamente las cantidades reducidas del codigo.
ALTER TABLE s1_config
  ADD COLUMN IF NOT EXISTS oasis_animals_rebalanced int(1) NOT NULL DEFAULT 0;

UPDATE s1_units AS u
INNER JOIN s1_odata AS o ON o.wref = u.vref
INNER JOIN s1_config AS c ON c.oasis_animals_rebalanced = 0
SET
  u.u31 = FLOOR(u.u31 * 0.50),
  u.u32 = FLOOR(u.u32 * 0.50),
  u.u33 = FLOOR(u.u33 * 0.50),
  u.u34 = FLOOR(u.u34 * 0.50),
  u.u35 = FLOOR(u.u35 * 0.50),
  u.u36 = FLOOR(u.u36 * 0.50),
  u.u37 = FLOOR(u.u37 * 0.50),
  u.u38 = FLOOR(u.u38 * 0.50),
  u.u39 = FLOOR(u.u39 * 0.50),
  u.u40 = FLOOR(u.u40 * 0.50)
WHERE o.conqured = 0;

UPDATE s1_config SET oasis_animals_rebalanced = 1;

-- 2026-08-01 - Reloj propio para la regeneracion de lealtad
-- `lastupdate` es el reloj de la produccion de recursos y solo avanza cuando el
-- dueno abre esa aldea, asi que la regeneracion de lealtad volvia a sumar todo
-- el tiempo transcurrido en cada pasada (una aldea atacada recuperaba 100% de
-- lealtad en la primera pasada). `loyaltyupdate` es el reloj exclusivo de la
-- lealtad y lo avanza unicamente la regeneracion.
ALTER TABLE s1_vdata
  ADD COLUMN IF NOT EXISTS loyaltyupdate int(11) unsigned NOT NULL DEFAULT 0;

UPDATE s1_vdata SET loyaltyupdate = UNIX_TIMESTAMP() WHERE loyaltyupdate = 0;

-- 2026-08-03 - Aldea natal del heroe
-- Antes `hero.wref` cumplia dos papeles a la vez: donde esta el heroe y donde se
-- produce su bono de recursos. `home` separa la aldea natal, que solo cambia si
-- el jugador lo pide al mandar al heroe a otra aldea propia; `sethome` es la
-- marca que viaja con ese movimiento.
ALTER TABLE s1_hero
  ADD COLUMN IF NOT EXISTS home int(11) unsigned NOT NULL DEFAULT 0;

UPDATE s1_hero SET home = wref WHERE home = 0;

ALTER TABLE s1_attacks
  ADD COLUMN IF NOT EXISTS sethome tinyint(1) unsigned NOT NULL DEFAULT 0;

-- 2026-08-05 - Bonos del slot de pies del heroe (botas y espuelas)
-- Hasta ahora equipar espuelas (types 100-102) o botas de regeneracion (94-96) no
-- escribia nada en el heroe: `applyHeroEquipmentBonusChange` no tenia rama para
-- btype 5. Los heroes que ya las tenian puestas quedaron con el bono sin sumar, y
-- el codigo nuevo se lo resta igual al desequiparlas (un heroe con corcel +
-- espuelas bajaria de 20 a 15, y uno con botas de regeneracion quedaria en 0).
-- No hay cambio de esquema: hay que reconciliar los datos una vez, despues del
-- deploy, con el script idempotente
--   docker compose exec -T web php /var/www/html/tools/fix_hero_equipment_bonuses.php --apply
-- que recalcula speed y autoregen a partir de los objetos equipados.

-- 2026-08-06 - Oasis huerfanos y dueno desincronizado
-- `odata` tiene dos columnas que tienen que ir juntas: `conqured` (la aldea que lo
-- tiene) y `owner` (el jugador). Nada las mantenia en sincronia:
--   * al conquistar una aldea con jefes, sus oasis quedaban con el `owner` viejo,
--     asi que los informes de defensa del oasis le seguian llegando al ex dueno y
--     el nuevo no lo veia en la Mansion del Heroe;
--   * al borrarse una cuenta o arrasarse una aldea, sus oasis quedaban marcados
--     como conquistados por una aldea inexistente: no repoblaban animales, no se
--     podian volver a tomar y seguian marcados como ocupados en el mapa.
-- El codigo ya sincroniza ambos casos; esto arregla los mundos que ya estan corriendo.

-- 1) Oasis atados a una aldea que ya no existe: vuelven a estar libres.
UPDATE s1_odata AS o
LEFT JOIN s1_vdata AS v ON v.wref = o.conqured
SET o.conqured = 0,
    o.owner = 3,
    o.loyalty = 100,
    o.lastupdated2 = UNIX_TIMESTAMP(),
    o.name = 'Oasis sin ocupar'
WHERE o.conqured <> 0 AND v.wref IS NULL;

-- 2) El dueno del oasis es siempre el dueno actual de la aldea que lo tiene.
UPDATE s1_odata AS o
INNER JOIN s1_vdata AS v ON v.wref = o.conqured
SET o.owner = v.owner
WHERE o.conqured <> 0 AND o.owner <> v.owner;

-- 3) `wdata.occupied` de los oasis tiene que reflejar si estan conquistados.
UPDATE s1_wdata AS w
INNER JOIN s1_odata AS o ON o.wref = w.id
SET w.occupied = IF(o.conqured = 0, 0, 1)
WHERE w.oasistype <> 0 AND w.occupied <> IF(o.conqured = 0, 0, 1);

-- 4) Informes huerfanos: reforzar un oasis creaba un informe con uid = 0 que no
--    era de nadie y solo engordaba la tabla.
DELETE FROM s1_ndata WHERE uid = 0;

-- 2026-08-06 - Saqueo de un oasis anexado
-- En Travian, saquear un oasis que ya tiene dueno se lleva hasta el 10% de los
-- recursos de la ALDEA que lo tiene, y ese cupo tarda 10 minutos en reponerse.
-- Aca el botin salia del stock propio del oasis (`odata.wood/clay/iron/crop`), que
-- es el comportamiento correcto solo para un oasis libre. `lastraid` es el reloj
-- de ese cupo del 10%.
ALTER TABLE s1_odata
  ADD COLUMN IF NOT EXISTS lastraid int(11) unsigned NOT NULL DEFAULT 0;

-- 2026-08-06 - Tope real del granero de los oasis
-- El barrido periodico filtraba por un 800 fijo, asi que un oasis con `maxstore` de
-- 1000 o 2000 dejaba de producir mucho antes de su tope. Y el camino que pone al dia
-- el oasis justo antes de saquearlo producia a 40 por hora en vez de 8 y **sin tope**,
-- asi que un oasis que llevaba tiempo sin tocarse podia llegar a decenas de miles de
-- recursos en el momento del ataque. Ambos ya estan corregidos; esto recorta lo que
-- haya quedado por encima del granero.
UPDATE s1_odata
SET wood = LEAST(wood, maxstore),
    clay = LEAST(clay, maxstore),
    iron = LEAST(iron, maxstore),
    crop = LEAST(crop, maxcrop)
WHERE wood > maxstore OR clay > maxstore OR iron > maxstore OR crop > maxcrop;

-- 2026-08-06 - Cascos de regeneracion (btype 1, types 4-6)
-- `applyHeroEquipmentBonusChange` no tenia rama para btype 1, asi que el Casco de la
-- Regeneracion / de la Salud / de la Curacion no sumaba nada a `hero.autoregen`. Los
-- heroes que ya lo tenian puesto quedaron sin el bono, y el codigo nuevo se lo resta
-- igual al desequiparlo. No hay cambio de esquema: hay que reconciliar los datos una
-- vez, despues del deploy, con el mismo script idempotente
--   docker compose exec -T web php /var/www/html/tools/fix_hero_equipment_bonuses.php --apply
-- que ahora tambien contempla el slot de cabeza.

-- 2026-08-06 - Aldea natal del heroe perdida
-- `hero.home` es la aldea que cobra el bono de recursos del heroe y, desde los cascos
-- de entrenamiento, tambien la que cobra el descuento de cuartel y establo. Nada la
-- reajustaba cuando el jugador dejaba de tener esa aldea (se la conquistan con jefes o
-- se la arrasan con catapultas), asi que quedaba apuntando a una aldea ajena o
-- inexistente y el heroe perdia los dos bonos sin ninguna pista de por que.
-- El codigo nuevo muda la natal sola en los dos eventos; los heroes que ya quedaron
-- rotos hay que reconciliarlos una vez, despues del deploy, con el script idempotente
--   docker compose exec -T web php /var/www/html/tools/fix_hero_home_village.php --apply
-- que la mueve a la capital y, si no hay, a la primera aldea que quede.

-- 2026-08-07 - Fuerza de combate del escudo (btype 3, types 76-78)
-- Los escudos siempre cayeron de las aventuras, pero hasta el commit que implemento la
-- mano izquierda `applyHeroEquipmentBonusChange` no tenia rama para btype 3, asi que su
-- fuerza de combate nunca se sumaba a `hero.itempower`. El codigo nuevo si se la resta
-- al desequiparlos: un heroe con escudo y arma puestos de antes tenia guardada solo la
-- fuerza del arma, y sacarse el escudo le restaba 1500 que nunca se habian sumado,
-- llevandose puesta la del arma. Hay que reconciliar una vez, despues del deploy, con
--   docker compose exec -T web php /var/www/html/tools/fix_hero_equipment_bonuses.php --apply
-- que ahora recalcula tambien `itempower` a partir del peto, el escudo y el arma.

-- 2026-08-10 - Capital natar para las oleadas contra la Maravilla
-- El instalador creo la aldea central de Natars (0|0) sin marcarla como capital y
-- Automation buscaba por error una capital del usuario Nature. El codigo nuevo
-- encuentra a Natars por nombre y tolera instalaciones viejas, pero reconciliamos
-- la marca para que el estado del mundo tambien sea correcto.
UPDATE s1_vdata AS v
INNER JOIN s1_users AS u ON u.id = v.owner
INNER JOIN s1_wdata AS w ON w.id = v.wref
SET v.capital = IF(w.x = 0 AND w.y = 0 AND v.natar = 0, 1, 0)
WHERE u.username = 'Natars';

-- 2026-08-12 - Totales semanales de alianza desincronizados
-- Defensa y saqueo se actualizaban con un id de alianza calculado por separado del
-- jugador, y los cambios de membresia no transferian los puntos semanales. El codigo
-- nuevo deriva siempre la alianza desde el jugador y transfiere su aporte al cambiar.
-- Esta reconciliacion idempotente corrige los contadores que ya estaban desviados y
-- alinea la base de poblacion para que la siguiente pasada no invente crecimiento.
UPDATE s1_alidata AS a
LEFT JOIN (
    SELECT u.alliance,
           COALESCE(SUM(u.ap), 0) AS ap,
           COALESCE(SUM(u.dp), 0) AS dp,
           COALESCE(SUM(u.clp), 0) AS clp,
           COALESCE(SUM(u.RR), 0) AS RR,
           COALESCE(SUM(v.population), 0) AS population
    FROM s1_users AS u
    LEFT JOIN (
        SELECT owner, SUM(pop) AS population
        FROM s1_vdata
        GROUP BY owner
    ) AS v ON v.owner = u.id
    WHERE u.alliance > 0 AND u.tribe <= 3 AND u.access < 8
    GROUP BY u.alliance
) AS totals ON totals.alliance = a.id
SET a.ap = COALESCE(totals.ap, 0),
    a.dp = COALESCE(totals.dp, 0),
    a.clp = COALESCE(totals.clp, 0),
    a.RR = COALESCE(totals.RR, 0),
    a.oldrank = COALESCE(totals.population, 0);

-- 2026-08-14 - Minutos y horarios multiples en rutas comerciales
-- Las rutas del Mercado solo guardaban la hora de salida (0-23); el jugador no podia
-- afinar el minuto ni cargar varios horarios de una entrega sin repetir el formulario
-- entero por cada uno. El codigo nuevo guarda tambien el minuto y el formulario permite
-- declarar varios horarios en un mismo guardado (cada uno se guarda como su propia fila).
ALTER TABLE s1_route
  ADD COLUMN IF NOT EXISTS start_minute tinyint(2) unsigned NOT NULL DEFAULT 0 AFTER start;

-- 2026-08-17 - Clase de aldea NPC (aldeas natar independientes)
-- Hasta ahora "esta aldea es escenario" se decidia por CUENTA: todo lo de Natars era
-- guarnicion estatica. Las aldeas natar independientes son de la misma cuenta pero se
-- comportan como aldeas normales (producen, crecen, entrenan, pasan hambre), asi que la
-- distincion pasa a ser por ALDEA. `npckind`: 0 jugador, 1 NPC estatico, 2 NPC vivo.
-- `npcupdate` es el reloj de tropas de una aldea viva; no puede compartir `lastupdate`,
-- que es el de la produccion de recursos, por el mismo motivo que la lealtad tuvo que
-- tener el suyo.
-- El codigo tambien crea estas columnas solo si faltan (ensureNpcVillageColumns), asi que
-- un deploy que llegue antes que esta migracion no rompe nada.
ALTER TABLE s1_vdata
  ADD COLUMN IF NOT EXISTS npckind tinyint(1) unsigned NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS npcupdate int(11) unsigned NOT NULL DEFAULT 0;

-- Backfill: todo lo que hoy pertenece a una cuenta del sistema es guarnicion estatica.
UPDATE s1_vdata AS v
INNER JOIN s1_users AS u ON u.id = v.owner
SET v.npckind = 1
WHERE u.id <= 4 AND v.npckind = 0;

-- 2026-08-17 - La capital natar se llamaba "1's village"
-- install/include/multihunter.php llamaba a addVillage($wid,$uid,$username,$capital) con
-- los dos ultimos argumentos invertidos, asi que el nombre se armaba a partir del literal
-- '1' en vez de 'Natars'. Arreglar el instalador no renombra un mundo ya instalado, y el
-- nombre se ve en el mapa y en el perfil de la cuenta natar.
-- Se ataca por wref (la capital marcada) y solo si todavia tiene un nombre generado a
-- partir del '1', para no pisar un nombre que alguien haya puesto a mano.
UPDATE s1_vdata AS v
INNER JOIN s1_users AS u ON u.id = v.owner
SET v.name = 'Capital natar'
WHERE u.username = 'Natars'
  AND v.capital = 1
  AND (v.name LIKE '%1\'s village%' OR v.name LIKE 'Aldea de 1%');

-- 2026-08-17 - La aldea del Multihunter se llamaba "Multihunter's village"
-- Mismo caso que la capital natar de arriba: el nombre lo genero una instalacion vieja en
-- ingles. El instalador ya lo fija en espanol, pero eso no renombra un mundo instalado, y
-- el nombre se ve en el mapa justo al lado de la capital natar.
-- Solo se pisa si sigue teniendo un nombre generado, nunca uno puesto a mano.
UPDATE s1_vdata AS v
INNER JOIN s1_users AS u ON u.id = v.owner
SET v.name = 'Aldea del Multihunter'
WHERE u.username = 'Multihunter'
  AND (v.name LIKE '%Multihunter\'s village%' OR v.name LIKE 'Aldea de Multihunter%');
