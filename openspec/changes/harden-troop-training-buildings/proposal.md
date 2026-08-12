## Why

The Barracks, Stable, and Workshop are core resource-spending buildings, but their shared legacy training path is only partially covered and can leave resources, queue timing, or displayed state inconsistent at boundary and failure conditions. They need one explicit behavioral contract and regression coverage across all five tribes before they can be considered production-safe.

## What Changes

- Define and enforce the valid unit family, research, building slot, building level, resource, free-crop, and request-token conditions for troop training.
- Make resource deduction and queue insertion failure-safe and serialized per village, including simultaneous submissions.
- Preserve the training time that applied when each order was submitted instead of merging incompatible adjacent batches.
- Make queue rendering valid and consistent for one or multiple batches, including overdue timers.
- Cover Barracks, Stable, and Workshop units for every supported tribe, along with forged requests, zero/negative/oversized quantities, insufficient resources, queue failures, and batch completion.

## Capabilities

### New Capabilities

- `troop-training-buildings`: Training behavior and queue guarantees for the Barracks, Stable, and Workshop.

### Modified Capabilities

None.

## Impact

- Shared training logic in `GameEngine/Technology.php` and `GameEngine/Database/db_MYSQLi.php`.
- Training completion in `GameEngine/Automation.php`.
- Barracks, Stable, and Workshop templates under `Templates/Build/`.
- Standalone regression checkers under `tools/`; no schema or external dependency change is expected.
