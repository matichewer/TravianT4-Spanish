## Context

The oasis detail view already loads the target tile, displays its Nature units, and offers the raid action. The combat simulator already supports Nature as defender tribe 4, but it normally requires a manual form round trip and manual quantities. Both pages run through `GameEngine/Village.php`, so they have the same selected-village, session, database, hero, and unit context.

## Goals / Non-Goals

**Goals:**

- Launch a trustworthy snapshot of the selected village against an unoccupied oasis.
- Reuse the existing simulator form and calculation rather than duplicate battle logic.
- Treat the link as read-only: opening or editing a simulation never moves troops or changes the oasis.
- Validate the supplied target server-side.

**Non-Goals:**

- Automatically send the simulated raid.
- Include reinforcements or troops that are not available to send from the selected village.
- Keep the scenario synchronized after the page has loaded.
- Add simulation shortcuts for villages or occupied oases.

## Decisions

1. The oasis detail action links to `warsim.php?oasis=<wref>`. On a GET without submitted form data, the simulator validates the world reference, builds its normal input array server-side, and passes it through the existing `Battle::procSim()` normalization and calculation path. A query parameter is preferable to a large client-generated POST because unit quantities remain authoritative and the URL contains no army data.

2. A public Battle helper builds the initial scenario. It reads only the active village's own `units` row, maps its tribe-specific ten units to `a1_1` through `a1_10`, includes one hero only when alive and physically present, and maps Nature units `u31` through `u40` to the matching defender fields.

3. The generated scenario selects Nature and raid mode, since the simulator intentionally rejects normal attacks against oases. All values remain editable after initialization.

4. The target must resolve to an existing, unoccupied oasis tile. Invalid, occupied, or non-oasis references produce a simulator error and no prefilled combatants.

5. Nature is no longer treated as an unconditional combatant-configuration change. The existing displayed-combatant markers already detect actual selector changes; removing the unconditional reset allows both prefilled and manually entered animal counts to reach the simulator.

## Risks / Trade-offs

- [The live army or animal population can change after the page loads] → Present the values as an editable simulation snapshot and never imply that it reserves troops.
- [A crafted target parameter could request unrelated unit rows] → Validate the world tile as an unoccupied oasis before reading units.
- [Legacy simulator reload behavior could clear prefilled values] → Cover direct initialization and subsequent editable submissions in the regression script.
