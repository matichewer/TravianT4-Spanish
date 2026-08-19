## Why

Official T4 has three kinds of Natar village. This server has two: the Natar capital and the 13 Wonder villages, both static scenery with a garrison that is spent once. The third kind is missing entirely — the *independent* Natar villages, which spawn gradually, behave like ordinary villages (they produce, raise their fields, build, and train troops slowly in barracks and stables), and can be raided, razed and eventually conquered. In official T4 they are what keeps raiding alive once players run out of farms.

The gap is visible on this world. There are 13 player villages and 14 Natar villages on a 201×201 map: nothing to raid that fights back. The Wonder villages hold ~31,000 troops each, while the largest army seen on this server is around 330 troops, so they are a wall, not a target.

## What Changes

- Add a per-village NPC kind, so the engine can tell a static garrison apart from a living NPC village. Today "is this scenery" is decided per *account*, and both kinds share the `Natars` account.
- Move the troop-upkeep and starvation exemptions from account-level to village-level: static villages keep them, living ones pay crop and starve like any player village.
- Add independent Natar villages whose entire state is a function of their age and their own field levels: fields rise on a schedule, production follows, and the garrison converges on what the village's crop can feed. No invented difficulty constant.
- Spawn them anchored to player villages rather than uniformly across the map, and let them respawn after being razed.
- Fold the ranking filter into the shared account boundary, and cover the razing path for NPC-owned villages with a regression test.

Explicitly out of scope: conquering them with senators (recorded as phase 2 in `openspec/BACKLOG.md`), and the grey zone, which this server does not implement.

## Capabilities

### New Capabilities

- `independent-natar-villages`: Defines the living NPC village — how it comes into being, how its state is derived, how it is farmed and how it dies.

### Modified Capabilities

- `npc-accounts`: The system/player boundary gains a per-village dimension, because one system account now owns villages of two different kinds.

## Impact

Adds two columns to `vdata` (`npckind`, `npcupdate`) with a backfill, so `tools/migrations.sql` grows and the migration must be applied by hand on production. Touches `GameEngine/Accounts.php`, `GameEngine/Automation.php`, `GameEngine/NatarVillage.php`, `GameEngine/Ranking.php` and the installer, and adds a new engine module plus regression checkers. No new dependency.

Deliberately different from official T4: spawn placement is anchored to player villages. Official spawns uniformly, which works there because thousands of players make "anywhere" mean "near someone"; on a four-player map it would make the feature invisible.
