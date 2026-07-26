## Why

The hero attribute screen shows resource bonuses as bare numbers, which makes hourly production look like an immediate resource grant and obscures the difference between the all-resource and focused modes.

## What Changes

- Label every hero resource bonus explicitly as an hourly rate.
- Explain that the bonus accrues continuously, is not granted immediately, and applies from the moment the distribution changes.
- Keep the existing production formulas and gameplay behavior unchanged.

## Capabilities

### New Capabilities

- `hero-resource-display`: Defines how hero resource-production rates and distribution behavior are explained to players.

### Modified Capabilities

None.

## Impact

The change affects the player-facing hero attributes template and its regression coverage. It adds no schema changes, dependencies, or gameplay changes.
