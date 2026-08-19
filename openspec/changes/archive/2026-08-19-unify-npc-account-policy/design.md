## Context

The installer fixes four system accounts at known ids: `Support` 1 and `Nature` 3 seeded from `install/data/sql.sql`, `Natars` 2 and `Multihunter` 4 inserted by `install/include/multihunter.php`. Player registration always writes a real tribe, so nothing but those four can sit at ids 1-4. Every consumer of that fact currently rediscovers it inline, in whichever dialect suited its file.

Reach matters for placement. The consumers span three layers: `Automation.php` and `NatarVillage.php` (loaded through `Session.php`), `Database/db_MYSQLi.php` (which needs a SQL fragment, not a PHP predicate), and `index.php` / `serverLogin.php` / `serverRegister.php`, which are public pages that include only `GameEngine/Database.php` and a language file — no session, no game data tables.

## Goals / Non-Goals

**Goals:** one named boundary reachable from all three layers, both as a PHP predicate and as a SQL fragment; one resolver for the Natar account; a checker that makes a new ad-hoc spelling fail loudly.

**Non-Goals:** touching the concepts that only resemble this one (`vdata.natar`, the oasis and caged-animal tribe fallbacks, the resource-field `$tid <= 4` comparisons); changing who starves, who produces, or how the Natars behave; any schema change.

## Decisions

- Put the boundary in a new dependency-free `GameEngine/Accounts.php` and require it from `GameEngine/Database.php`. That file is the one include every consumer already has, so no caller needs a new include and the public pages keep their tiny bootstrap.
- Expose both shapes: `isSystemAccount($uid)` / `isPlayerAccount($uid)` for PHP, and `systemAccountSql($column)` / `playerAccountSql($column)` for queries. Hand-writing `owner > 4` in SQL is exactly the habit being removed, so the module has to serve the query layer too.
- Name the four accounts as constants (`UID_SUPPORT`, `UID_NATARS`, `UID_NATURE`, `UID_MULTIHUNTER`) and derive the boundary from them rather than from a bare `4`. The literal stops appearing anywhere.
- Resolve `Natars` through `systemAccountId('Natars')`, which looks the account up by username once per request and falls back to the constant. This keeps the robustness the existing comment in `startNatarAttack()` asks for — old worlds whose ids drifted — while removing the duplicated join. Both call sites then filter on `owner`, which is indexed, instead of joining `users`.
- Switch the online counter from `tribe != 0 AND tribe != 4 AND tribe != 5` to the player predicate. The tribe filter was an approximation that never covered `Support`, whose tribe is 1.
- Make the checker match on the surrounding identifier (`owner`, `uid`, `$starv['owner']`) rather than on a bare `<= 4`, so the resource-field comparisons in `Building.php` do not trip it.

## Risks / Trade-offs

- [A world whose system accounts are not at ids 1-4 would misclassify] → The username resolver covers the Natar case, which is the one the existing code already worried about; the checker asserts the installer still seeds the documented ids.
- [Requiring a file from `Database.php` widens a low-level include] → `Accounts.php` has no dependencies of its own, defines only constants and pure functions, and is guarded against double loading.
- [The online counter changes what players see] → It can only ever decrease the count, by at most one, and only when `Support` has a fresh timestamp. Called out in the proposal as the single intended behaviour change.
