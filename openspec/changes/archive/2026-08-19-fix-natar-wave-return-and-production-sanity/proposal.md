## Why

Two defects left over from the Natar work, both of the same family: a quantity that grows without anyone deciding it should.

`startNatarAttack()` invents the Wonder attack waves with `addAttack()` and never deducts them from `units`, but `returnunitsComplete()` credits the survivors back to the village they left from. So the Natar capital gained troops with every Wonder level any player ever built, permanently.

And nothing in the engine notices an impossible production rate. The Natar starvation incident was not caused by the lazy production clock — that part is sound, since starvation kills by rate and discards the debt, so an absent player is never punished retroactively. It was caused by a rate of ‑45,000 crop/h on a Wonder village and ‑5,200,000/h on the capital being applied in silence until the garrisons were gone.

While fixing the first one, a third defect surfaced: a Wonder village can be conquered once catapults raze its residence, and it kept its static NPC kind, so the new owner inherited a village exempt from troop upkeep and starvation.

## What Changes

- Wonder waves no longer return to the capital, and the `attacks` row that the return movement would have reused is deleted instead of leaking.
- Conquest resets a village's NPC kind to player in the same write that changes its owner.
- Add a world sweep that fails when a static NPC village is in the red, when any NPC village's deficit exceeds what its own fields could produce at maximum level, or when a village owned by a system account is recorded as a player village.

## Capabilities

### Modified Capabilities

- `npc-accounts`: a conquered village stops being an NPC village, and NPC production rates are held to a sustainability invariant.

## Impact

Touches `GameEngine/Automation.php` and `GameEngine/Database/db_MYSQLi.php`, adds `tools/check_production_sanity.php` and extends two Natar checkers. No schema change and nothing to run on an existing world.
