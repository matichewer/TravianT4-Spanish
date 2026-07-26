## Why

The hero attribute screen shows resource bonuses as bare numbers, which makes hourly production look like an immediate resource grant and obscures the difference between the all-resource and focused modes.

## What Changes

- Label every hero resource bonus explicitly as an hourly rate.
- Explain that the bonus accrues continuously, is not granted immediately, and applies from the moment the distribution changes.
- Guarantee that the displayed rate is added directly, as a fixed hourly amount, to the selected village resource without percentage scaling.
- Keep the established `36/h` all-resource and `120/h` focused values unchanged for a four-point hero on a speed-three server.

## Capabilities

### New Capabilities

- `hero-resource-display`: Defines how hero resource-production rates and distribution behavior are explained to players.

### Modified Capabilities

None.

## Impact

The change affects the player-facing hero attributes template, the documented village-production contract, and hero regression coverage. It adds no schema changes or dependencies.
