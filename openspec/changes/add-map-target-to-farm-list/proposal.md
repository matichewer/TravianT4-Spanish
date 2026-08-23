## Why

Adding a target to a farm list currently requires navigating to the rally point and entering its coordinates manually. The map already exposes target-specific actions, so it should provide a direct, safe path into the existing farm-list workflow.

## What Changes

- Add an "Agregar a lista de granjas" action to eligible village and oasis map details.
- Pre-fill the existing farm-list slot form with the selected tile coordinates.
- Allow the player to choose any farm list they own and configure the raiding troops before saving.
- When the player has no farm lists, route them to list creation with a clear explanation instead of presenting an unusable selector.
- Hide or disable the action when farm lists are unavailable, including missing Gold Club access or a missing rally point.
- Reject duplicate targets within the selected list and preserve server-side ownership validation.

## Capabilities

### New Capabilities
- `map-farm-list-action`: Covers adding an eligible map target to an owned farm list, pre-filled navigation, unavailable states, and duplicate protection.

### Modified Capabilities

None.

## Impact

- Map tile detail actions in `Templates/Map/vilview.tpl`.
- Gold Club farm-list creation and slot forms under `Templates/goldClub/`.
- Farm-list database helpers in `GameEngine/Database/db_MYSQLi.php`.
- Regression coverage in a new standalone `tools/check_*.php` checker.
- No schema or external dependency changes.
