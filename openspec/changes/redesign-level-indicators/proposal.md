## Why

The village level badges currently encode upgrade availability in the center color, duplicate their presentation between resource fields and buildings, and add a separate hammer marker for construction. Matching the clearer Travian Android treatment will make upgrade and construction state readable from the badge itself while keeping both village views consistent.

## What Changes

- Move upgrade availability to the outer badge ring: turquoise blue for completed maximum level, green when the next level is affordable, and yellow when it is not.
- Keep the badge center white normally and turn it orange for every active or queued construction job.
- Show the target level of a construction job inside its orange center, including level 1 for a new building.
- Remove the active and queued hammer markers.
- Replace duplicated inline presentation with shared semantic badge classes used by resource fields, regular building slots, the rally point, and the wall.
- Preserve existing badge positions and the city level visibility toggle.

## Capabilities

### New Capabilities

- `village-level-indicators`: Defines the visual and behavioral states of resource-field and building level badges.

### Modified Capabilities

None.

## Impact

- Affects level-state calculation in `GameEngine/Building.php`.
- Affects badge markup in `Templates/field.tpl` and `Templates/dorf2.tpl`.
- Affects shared badge styling in `img/travian_basics.css` and supersedes the relevant legacy graphic-pack styling.
- Does not change database schema, building rules, construction timing, or external APIs.
