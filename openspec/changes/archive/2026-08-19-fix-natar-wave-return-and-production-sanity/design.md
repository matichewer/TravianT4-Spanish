## Context

The waves are a fixed table of troop counts per Wonder level, generated for the occasion. Nothing about them models an army: they are never paid for, never trained, and never miss.

## Goals / Non-Goals

**Goals:** the capital's garrison stays what the installer gave it; a rate nobody could sustain is loud instead of silent.

**Non-Goals:** changing what the waves do to the player being attacked, including their loot; rebalancing wave sizes.

## Decisions

- Make the waves not return, rather than deducting them at departure. Deducting would drain a finite stock that was never meant to be finite, and would eventually stop the endgame; not returning keeps the capital exactly as installed, which is what scenery means.
- Key the behaviour on the *source village's* NPC kind. Only a static NPC village sends attacks nobody deducted, and no player village is ever static, so the condition cannot catch a real army.
- Delete the `attacks` row when skipping the return. The normal path hands that row to the return movement to reuse; with no return, nothing would ever reference it again.
- Split the sanity sweep by who is responsible. A player may run any deficit they like — over-training and starving is a legitimate move — so those are counted and reported, never failed. An NPC village has nobody administering it, so a deficit its own fields could not cover at maximum level is always an engine bug.
- Use "gross crop with every crop field at the table maximum plus mill and bakery" as the ceiling. It is the largest number the village could ever produce, so a deficit above it is irrecoverable by definition rather than by a threshold someone picked.

## Risks / Trade-offs

- [The capital never loses troops, so waves are effectively infinite] → That is the intent, and it matches official Travian, where waves are generated rather than drawn from a stock.
- [The sanity sweep is O(villages) with a production computation each] → It is a checker, not a request path, and this world has tens of villages.
