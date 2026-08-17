## Context

The derived-state model was right for what players change directly and wrong for what they build. Troops and resources live in `units` and `vdata` and are only ever advanced toward a target, so raiding them works. Field levels were recomputed from age on every sweep and written unconditionally, which is indistinguishable from "repair everything instantly".

## Goals / Non-Goals

**Goals:** catapults matter; the model stays derivable enough that nothing can drift; conquest hands over a village, not an unusable army.

**Non-Goals:** letting players conquer the Natar capital (a capital is never conquerable, which already covers it); the Wonder villages' own conquest flow, which is unchanged and still requires razing their residence first.

## Decisions

- Approach the target rather than impose it, using the same shape the garrison already uses: `min(ideal, current + steps)`. At spawn the ideal is applied directly, since a new village has nothing to repair.
- Pace repair from the village's birth and use `npcupdate` as the "last seen" marker, so the number of earned repair steps is derived and needs no new column.
- Set the repair interval below the growth interval — 2 days against 3. Repairing faster than it grows keeps a catapulted village from being crippled for a month, while staying far enough above zero that the attack was worth making.
- Compute the garrison target and the training rate from real fields. This is what makes damage reach the troops, and it produces a consequence worth knowing: destroying wood, clay or iron fields *raises* the sustainable garrison, because population eats crop and a smaller village frees some. Only the crop fields starve it. That is correct Travian arithmetic, and it means an attacker who wants a Natar village disarmed has to aim at the crop.
- Dissolve the garrison after the ownership write succeeds, never before. If the conquest fails — another player got there first, or no chief survived — the defender keeps their troops. `hero` is deliberately untouched: the hero has its own lifecycle and `reassignHeroHomeVillage()` handles it.

## Risks / Trade-offs

- [Dissolving the garrison changes player-versus-player conquest] → Chosen deliberately; the alternative was to special-case NPC villages, which would have left two rules to remember.
- [A village nobody visits for a year repairs fully] → Correct: a year is more than enough to rebuild, and the level is still capped by what its age allows.
