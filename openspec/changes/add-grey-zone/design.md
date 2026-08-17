## Context

`startNatarAttack()` already invents attack waves from a fixed table and sends them from a Natar village without deducting anything, and `sendunitsComplete()` already knows not to return troops that came from a static NPC village. The grey-zone assault is the same shape aimed at a different trigger, so it reuses both.

Measured on this world: the thirteen Wonder villages sit in two clean rings, five at distance ~12 and eight at ~21, which is exactly the official five-inside/eight-outside split waiting for a boundary drawn between them. The official radius of 22 puts all thirteen inside and reproduces nothing.

## Goals / Non-Goals

**Goals:** the mechanic that makes the grey zone memorable; zero effect on anything already built; a shape a fresh world can set back to official with two constants.

**Non-Goals:** the terrain; the culture-point restriction; relocating anything.

## Decisions

- A ring, not a disc, with the inner radius configurable to `0` to recover the official disc. The ring is documented at the top of the module as an adaptation to a world already in progress, not as a preference.
- Draw the boundary at 10–16. Below 10 the core stays safe, which is where the existing central villages are; 16 keeps the inner Wonder ring in and the outer one out.
- Accept the loophole this creates: a player can found inside the safe core and end up a few tiles from a Wonder village without paying the toll. It grants nothing new, because the core is precisely where the grandfathered villages already sit; closing it would mean either moving villages or taxing players for where they settled before the rule existed.
- Trigger on settlement completion, after the village exists and the movement is marked processed, so a settlement that failed halfway schedules nothing.
- Waves arrive one second apart so they resolve in order and the player gets fourteen readable reports instead of one.
- Leave the catapult targets unset. The engine then spreads the impact over occupied non-wall slots, which is what "the Natars do not choose what to break" means mechanically.
- Mark the zone only on free valleys in the tooltip. That is where the warning can change a decision; on an occupied tile it would be noise.

## Risks / Trade-offs

- [Waves sized by judgement, not by an official table] → Travian never published the settling-wave composition. They are scaled by `SPEED` and sized against this server's largest observed army (~330 troops) so the cleaner is decisively unstoppable without being absurd, and they live in one function to retune.
- [A player founds inside and loses the village] → That is the mechanic, and it is why the tooltip warning is part of this change rather than a follow-up.
