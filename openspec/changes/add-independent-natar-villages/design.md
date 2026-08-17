## Context

The engine is pull-driven: there is no cron and no tick, `Automation` runs in its constructor on every request, and a village's resources only advance when somebody touches it (`lastupdate` + `updateRes`). Anything that grows on its own has to fit that shape or it will simply not happen at four in the morning.

Two precedents matter. `addAdventures()` is a world-level periodic event driven by a per-row clock compared against `time()` inside the same sweep — no scheduler. And loyalty needed its own clock column precisely because `lastupdate` belongs to resource production and cannot be shared.

The account boundary added in `unify-npc-account-policy` is per *account*. That was right for a world where every Natar village was scenery. It stops being enough here: the `Natars` account will own both static garrisons and living villages.

## Goals / Non-Goals

**Goals:** a living NPC village whose difficulty lands in range for this server's actual armies; state that is derivable rather than accumulated, so it cannot drift or be corrupted by concurrent requests; content that is reachable from where players actually are.

**Non-Goals:** conquest with senators; the grey zone; changing anything about the Wonder villages or the capital; a scheduler, daemon or cron.

## Decisions

### The village kind is a column, not an inference

`vdata.npckind`: 0 player, 1 static NPC, 2 living NPC. Backfilled to 1 for every village owned by a system account and 0 everywhere else.

The alternative was to infer it — `capital = 1 OR natar = 1` means static, anything else living. It needs no migration, and it is exactly the implicit convention the previous change spent its time removing. A third kind later would break every inference site silently. The column costs one `ALTER TABLE` applied by hand on production, which `tools/migrations.sql` already exists to record.

### Exemptions move from the account to the village

`isSystemAccount()` stays as the account boundary; starvation and production upkeep stop asking it and start asking the village kind. Static villages keep both exemptions. Living villages pay crop upkeep and starve, and that is deliberate: **crop is the difficulty dial.** A living village's garrison converges on what its fields can feed, so nothing needs an invented cap, and the number falls out of the production formula the whole codebase already shares.

### One clock drives everything: the village's age

Field level is `min(10, start + floor(age / growth interval))`. Production follows from the fields through `villageGrossProduction()`. The sustainable garrison follows from the net crop. Storage, and therefore loot, follows from the fields and the warehouse. Every one of those is a pure function of `(created, wref, now)` — `wref` doubles as the seed, so no seed column is needed.

Measured against this world's numbers, the progression lands where it should:

| fields | net crop/h | sustainable garrison | reads as |
|---|---|---|---|
| level 2 | ~200 | ~100-150 | one of this server's current armies |
| level 5 | ~900 | ~400-600 | worth gathering for |
| level 10 | ~3,000 | ~1,500-3,000 | an alliance target |

The largest army observed on this server is ~330 troops, so a fresh village is beatable today and a mature one is a goal. A Wonder village's 31,000 stays out of that range on purpose.

### The garrison is state; the target and the rate are derived

Troops cannot be a pure function of age, because players kill them. So `units` stays authoritative and is advanced *toward* the derived target at a derived rate, exactly as `updateRes` advances resources toward the present. That needs a clock that resource accrual does not move, hence `vdata.npcupdate`, following the loyalty precedent.

The training rate comes from the village's own barracks and stable levels against the unit training times in `unitdata` — the same inputs a player village uses. "It trains like a real village" becomes literally true instead of a constant someone picked, and it self-balances with the rest of the village's growth.

Both the clock advance and the resource accrual use the existing compare-and-swap shape (`WHERE npcupdate = <value read>`), so two concurrent requests cannot credit the same interval twice.

### Catch-up is capped

A village nobody has touched for months must not materialise a garrison from months of accumulated training. The crop ceiling already bounds the total, and the catch-up is additionally clamped to a bounded number of intervals. This is the same class of bug as the Natar starvation incident — an unbounded quantity credited retroactively — and it is the one thing in this design that has already bitten this codebase once.

### Spawning anchored to players, driven by the existing sweep

A spawn attempt runs on the `addAdventures()` pattern: a clock, a threshold, inside the page-load sweep. If nobody is playing nothing spawns, which is correct — there is nobody there to farm it.

Placement picks a random player village and then a free field 5 to 25 tiles away. At `INCREASE_SPEED = 3` that band is roughly 14 to 71 minutes of travel for a slow unit, which is raiding distance. Official T4 spawns uniformly across the map; on this map, with four players in four clusters and ~40,400 tiles, uniform placement would put essentially nothing within reach of anyone.

Tunable through config, starting at: 2 per player cluster, global cap 12, one spawn attempt every 12-24 h, one field level every ~3 days to a maximum of 10, and a delay before a razed village is replaced so that razing one feels like it did something.

### Razing already works; rankings already exclude them

`destroyCatapultedVillage()` ignores ownership and refuses only on a capital or an account's last village, so the Natar capital is protected and independents are destructible. Rankings filter on `u.tribe <= 3 AND u.access < 8`, which excludes all four system accounts — correctly, but as an eighth spelling of the account boundary that the previous change missed. It gets folded into `Accounts.php` here, since this change is what would multiply the villages behind it.

## Risks / Trade-offs

- [A long-untouched village materialises an absurd garrison] → Crop ceiling plus a clamped catch-up, and a checker that asserts the state at one hour, one week and one year of age.
- [Spawn placement collides with an occupied field, an oasis or the map edge] → Placement validates against `wdata` the same way village founding does, and gives up cleanly if the band has no free field rather than retrying forever.
- [More farms inject resources into a small economy] → Loot scales with the village's age through the same field levels, so a young world gets small farms; the numbers are config-driven and can be retuned without code.
- [Diverging from official on placement] → Documented in the proposal. The intent of the official rule is preserved; only the mechanism adapts to a map that has four players instead of thousands.
- [Two new columns on a live table] → Both are nullable with defaults and are backfilled by a single statement recorded in `tools/migrations.sql`; the code treats a missing column as "player village" so an unmigrated world degrades to today's behaviour instead of breaking.
