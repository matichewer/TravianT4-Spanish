## Why

Using a water bucket while a paid hero revival is queued revives the hero in the currently selected village but leaves the paid revival queued. This creates conflicting hero locations and can leave the revived hero unavailable to the player.

## What Changes

- Make the water bucket complete any pending paid revival immediately in the village where that revival was purchased.
- Remove the completed revival queue entry and place exactly one hero unit in the resolved village.
- Preserve normal bucket behavior when no paid revival is pending.
- Add regression coverage for queued and non-queued bucket revival.

## Capabilities

### New Capabilities

- `hero-revival`: Defines consistent hero state, placement, and queue handling when reviving with resources or a water bucket.

### Modified Capabilities

None.

## Impact

Hero consumable handling and the hero training queue in `GameEngine/Inventory.php` and `GameEngine/Database/db_MYSQLi.php`, plus standalone regression checkers under `tools/`.
