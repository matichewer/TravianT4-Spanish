## 1. Shared boundary

- [x] 1.1 Add `GameEngine/Accounts.php` with named system-account ids, the predicate pair, the SQL fragment pair and the Natar resolver
- [x] 1.2 Load it from `GameEngine/Database.php` so the engine, the database layer and the public pages all reach it

## 2. Replace the ad-hoc spellings

- [x] 2.1 Starvation, starvation notices and production upkeep in `Automation.php`
- [x] 2.2 Nearest-player-village search in `db_MYSQLi.php`
- [x] 2.3 Natar provisioning and village lookup in `NatarVillage.php`
- [x] 2.4 Wonder attack waves resolve the Natar account through the shared resolver
- [x] 2.5 Online and active counters in `index.php`, `serverLogin.php` and `serverRegister.php`

## 3. Regression coverage

- [x] 3.1 Add `tools/check_npc_accounts.php`: boundary behaviour, installed ids, and a scan that rejects new inline spellings without flagging field-type comparisons
- [x] 3.2 Update the Natar checkers for the new call shapes
- [x] 3.3 Run strict OpenSpec validation, PHP syntax checks and the full checker battery
