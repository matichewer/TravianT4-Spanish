## Context

`Templates/Map/mapview.tpl` and its large twin build the tile tooltip as `"Aldea " . $name`. That reads fine for "Hefesto" and badly for anything already containing the word, which is every Natar village this server creates. The label is decoration: the tooltip already says `Jugador:`, `Población:` and `Tribu:` on their own lines, so the name does not need a noun in front of it.

`addVillage($wid, $uid, $username, $capital)` builds the village name as `"Aldea de " . $username`. The installer calls it correctly for the Wonder villages and with the last two arguments swapped for the capital, which is why that one is named after the literal `'1'`. The wrong `capital` argument is harmless because the next statement overwrites it.

## Goals / Non-Goals

**Goals:** names a player can read and tell apart; one creation path for Natar villages regardless of who triggers it; a repair tool that reports what it found.

**Non-Goals:** renaming the Wonder villages, which are correctly named; touching the map layout or tile art; the grey zone.

## Decisions

- Remove the label rather than renaming the villages. The doubling is the template's fault and it affects the 13 Wonder villages too, so fixing it at the source repairs more than it touches, and no data has to change.
- Derive each independent village's name from its existing seed, picking from a small table of Natar-flavoured place names with the coordinates as a suffix when the table wraps. It stays a pure function of `wref`, like the rest of that module, so the name survives any recomputation and two villages never collide.
- Rename the capital in the installer *and* in a migration. Fixing the installer alone leaves every already-installed world with "1's village" forever, and this world is one of them.
- Point the two admin mods at `natarRestockGarrison()` and `natarProvisionVillage()`. They currently duplicate an obsolete copy of the creation logic — wrong owner, no economy, no NPC kind — and that duplication is what let them drift in the first place.
- Report `nivel actual → nivel planeado` in the repair tool and warn when a field sits above the official maximum. The tool only ever raises levels, so a level that is too high is invisible to it; saying so is cheaper and more honest than silently lowering it.

## Risks / Trade-offs

- [Renaming the capital changes something players have seen] → It is currently a bug that reads as a placeholder; the migration is a single targeted `UPDATE` keyed on the wrong name so it cannot touch anything else.
- [Generated names could collide on a large map] → The coordinate suffix makes them unique, and the checker asserts uniqueness across the whole spawn range.
