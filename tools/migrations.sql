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
