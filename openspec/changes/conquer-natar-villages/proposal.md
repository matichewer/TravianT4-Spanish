## Why

Fase 2 of the independent Natar villages: making them conquerable. Verification showed the conquest path already works — living Natar villages carry no residence, so nothing blocks a chief, and the NPC-kind reset added earlier already hands the village back to the player world. Three chief hits transfer it, the expansion slot is consumed, and it starts paying crop like any village.

What verification did surface is a real flaw in the model underneath. Field and building levels were derived from the village's age and re-imposed on every sweep, so catapult damage was undone within sixty seconds. Destroying a Natar village's cranny, its crop fields or its barracks accomplished nothing at all. Troops and resources responded to raiding correctly — they are real state — but everything built did not.

And on conquest the survivors changed sides, which for a Natar village means inheriting troops of a tribe the new owner can never retrain and must feed forever.

## What Changes

- Field and building levels approach their age-appropriate target instead of being imposed, so catapult damage persists and the village repairs one level per repair interval — slightly faster than it grows, for a bit more dynamism.
- The sustainable garrison and the training rate are computed from the village's *actual* fields and buildings, not from the ideal for its age, so damage reaches the troops: breaking the crop fields starves the garrison down, breaking the barracks slows its recovery.
- A conquered village's surviving garrison dissolves instead of changing sides. This applies to every conquest, player-versus-player included.
- The village name is derived too, so the sweep repairs a stale one — villages named before names carried coordinates rename themselves rather than needing a manual update.

## Capabilities

### Modified Capabilities

- `independent-natar-villages`: damage persists and is repaired over time; names self-heal.
- `npc-accounts`: conquest dissolves the garrison it used to transfer.

## Impact

Touches `GameEngine/NatarSettlement.php` and the conquest write in `GameEngine/Database/db_MYSQLi.php`, and extends the two settlement checkers. No schema change and nothing to run on an existing world — the stale name repairs itself on the first sweep.
