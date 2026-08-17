## Why

The engine has no name for "this account is not a player". The idea is spelled at least seven different ways across the engine, the database layer and the public pages — `owner <= 4`, `owner > 4`, `username = 'Natars'`, `tribe != 0 AND tribe != 4 AND tribe != 5` — and none of them knows the others exist.

That is not cosmetic. The Natar starvation bug was exactly this: the engine had no concept of an NPC village, so it applied player rules to scenery, drained a Wonder village's crop to -45k/h and let starvation empty its whole garrison within ten minutes of the first attack it received. Fixing it added two more ad-hoc spellings. One spelling has already drifted: the online counter filters by tribe and therefore still counts `Support` (uid 1, tribe 1) as a connected player.

## What Changes

- Introduce one home for the system-account boundary, with named ids for `Support`, `Natars`, `Nature` and `Multihunter`, a predicate for callers and SQL fragments for queries.
- Resolve the Natar account through that single resolver instead of joining `users` by username in two places.
- Replace the ad-hoc spellings in starvation, starvation notices, production upkeep, nearest-player-village search, the Natar provisioning module and the three public pages.
- Correct the online counter to exclude every system account rather than three tribe values, so `Support` is no longer counted as a player.
- Add regression coverage that fails when a new raw spelling of the boundary appears outside the shared module.

Explicitly out of scope: `vdata.natar` (a Wonder-village marker), the oasis and caged-animal tribe fallbacks in the report templates, and the resource-field `$tid <= 4` comparisons in `Building.php` and `Automation.php`. Those are different concepts that merely look similar.

## Capabilities

### New Capabilities

- `npc-accounts`: Defines the single boundary between system-owned accounts and player accounts, and the rules that follow from it.

### Modified Capabilities

## Impact

Touches `GameEngine/Automation.php`, `GameEngine/Database/db_MYSQLi.php`, `GameEngine/NatarVillage.php`, `index.php`, `serverLogin.php`, `serverRegister.php` and the Natar regression checkers. Adds `GameEngine/Accounts.php`. No schema change and no new dependency. The only intended behaviour change is the online counter; everything else is a rename of an existing condition.
