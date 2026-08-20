## Why

The oasis lifecycle has several inconsistencies that can misstate ownership history, miscalculate loyalty or production, strand reinforcements, and send players to the wrong map tile. These paths affect real resources and troops, so they need one coherent contract and regression coverage.

## What Changes

- Preserve fractional oasis-loyalty regeneration time and apply the configured rate exactly.
- Accrue village production before an oasis bonus is removed.
- Return stationed reinforcements whenever an oasis becomes free, including village deletion paths.
- Correct oasis coordinate links and display an actual conquest timestamp.
- Add focused regression checks for lifecycle state transitions and profile bonus rendering.

## Capabilities

### New Capabilities
- `oasis-lifecycle-integrity`: Defines consistent oasis ownership, loyalty, production, reinforcement cleanup, history, and navigation behavior.

### Modified Capabilities

None.

## Impact

Touches oasis automation, database lifecycle helpers, the Hero's Mansion template, oasis schema migration/installer definitions, and standalone regression checkers. Existing worlds require the additive migration for the conquest timestamp. Administrative hard-deletion semantics remain outside the player-gameplay lifecycle.
