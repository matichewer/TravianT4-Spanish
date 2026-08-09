## Why

The Gaul trapper's happy path works, but trapped troops currently stop consuming crop and several capture, release, training, and reporting operations can become inconsistent or be bypassed. These defects create gameplay exploits and can lose or duplicate troop state under failed or concurrent processing.

## What Changes

- Make trap reservation and prisoner persistence atomic so concurrent attacks cannot overbook traps.
- Make manual and battle-driven releases failure-safe, preserving prisoners until every required return movement is queued.
- Count trapped troops toward the upkeep of their home village.
- Require trap training requests to originate from a completed trapper field.
- Distribute captures proportionally across the attacking troop composition instead of consuming unit slots in fixed order.
- Render release information from the correct battle-report field.
- Add repeatable integration regressions for training, capture, release, disbanding, upkeep, authorization, concurrency, and reports.

## Capabilities

### New Capabilities

- `trapper-lifecycle`: Defines trap construction, capacity, capture distribution, prisoner accounting, upkeep, release, disbanding, reporting, and lifecycle consistency.

### Modified Capabilities

None.

## Impact

The change affects `GameEngine/Automation.php`, `GameEngine/Technology.php`, `GameEngine/Units.php`, the MySQL/MySQLi database adapters, trapper and rally-point templates, battle-report templates, conquest/deletion cleanup, and a new regression script under `tools/`. No schema migration or external dependency is required.
