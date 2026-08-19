## 1. Village kind

- [x] 1.1 Add `vdata.npckind` and `vdata.npcupdate` to `tools/migrations.sql`, with the backfill and a self-healing column check
- [x] 1.2 Name the kinds in `GameEngine/Accounts.php` and expose the per-village predicate
- [x] 1.3 Move the upkeep and starvation exemptions from account-level to village-level
- [x] 1.4 Mark the capital and the Wonder villages as static in the installer and in `tools/fix_natar_villages.php`

## 2. The living village

- [x] 2.1 New engine module: age to field levels, fields to production and storage, crop to sustainable garrison
- [x] 2.2 Training rate from the village's own barracks and stable levels against the unit training times
- [x] 2.3 Bring a village up to date: advance the garrison toward its target under a compare-and-swap on `npcupdate`, with the catch-up bound
- [x] 2.4 Hook the update into every path that touches an NPC village (map view, attack resolution, raid)

## 3. Spawning and death

- [x] 3.1 Free-field search within a distance band of a player village
- [x] 3.2 Spawn attempt on the `addAdventures()` clock pattern, with the cap and the config knobs
- [x] 3.3 Respawn delay after a village is razed
- [x] 3.4 Refuse reinforcements sent to a living NPC village

## 4. Loose ends this change touches

- [x] 4.1 Fold the ranking filter into the shared boundary and teach the scanner its shape
- [x] 4.2 Confirm the razing path on an NPC-owned village, and that the capital survives it

## 5. Regression coverage

- [x] 5.1 Derived state at one hour, one week and one year of age, and idempotent recomputation
- [x] 5.2 Catch-up bound, crop ceiling, and the concurrent-update case
- [x] 5.3 Spawn placement: inside the band, only on free fields, cap respected, no field leaked when placement fails
- [x] 5.4 Living village starves, static village does not, player village still does
- [x] 5.5 Raiding returns loot, razing removes the village, the capital survives razing, rankings stay clean
- [x] 5.6 Run strict OpenSpec validation, PHP syntax checks and the full checker battery
