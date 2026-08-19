## Why

Catapult waves currently have nondeterministic tie ordering and incomplete server-side target authorization, while impacts can change production or collide with queued construction without synchronizing related state. These gaps can make identical waves resolve differently or leave village state inconsistent.

## What Changes

- Resolve same-second attacks deterministically and ignore stale rows after a village is destroyed.
- Preserve the existing rule that a missing explicit target redirects to another occupied non-wall slot.
- Authorize selectable targets from one shared catalog using the rally point level and special-building rules.
- Accrue production before catapult damage changes fields or production bonuses.
- Reconcile pending construction with a catapult-damaged slot.
- Expose every legitimately selectable building in the attack form.
- Add regression coverage for target permissions, simultaneous waves, production, queues, and village destruction.

## Capabilities

### New Capabilities

- `catapult-resolution`: Defines deterministic, authorized and state-consistent catapult targeting and wave resolution.

### Modified Capabilities


## Impact

The change affects troop dispatch, attack automation, building queues, the catapult target UI, and standalone regression checkers. It introduces no external dependency or schema change.
