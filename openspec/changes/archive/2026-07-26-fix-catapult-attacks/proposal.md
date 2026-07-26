## Why

Catapult attacks can persist building and resource-field damage, but the live attack form never exposes target selection and the resolution path contains random-target and damage-calculation defects. These defects make attacks unreliable and cause the simulator to disagree with real combat.

## What Changes

- Show catapult target controls whenever the submitted army contains the tribe's position-eight siege unit and the movement is an attack or raid.
- Validate and normalize catapult targets on the server, including single, double, explicit, missing, and random targets.
- Resolve eligible target slots without invalid random indexes and persist complete or partial damage consistently for buildings and resource fields.
- Use one shared siege-damage calculation for real combat and the simulator, including casualties, smithy upgrades, morale, and stonemason durability.
- Keep catapult fire limited to normal attacks and split firing power predictably across two targets.
- Prevent zero-speed siege units from causing invalid travel-time calculations.
- Add regression coverage for all supported attacker tribes, target modes, and damage outcomes.

## Capabilities

### New Capabilities

- `catapult-attacks`: Defines training, sending, target selection, combat calculation, and persisted damage behavior for catapult-class units.

### Modified Capabilities

None.

## Impact

The change affects `Templates/a2b/attack.tpl`, troop dispatch validation in `GameEngine/Units.php`, siege calculations in `GameEngine/Battle.php`, live attack processing in `GameEngine/Automation.php`, unit travel handling, and local regression tooling. It does not require a schema migration or new dependency.
