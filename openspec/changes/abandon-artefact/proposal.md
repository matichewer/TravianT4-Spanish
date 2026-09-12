## Why

Players cannot relinquish an unwanted artefact unless another player captures it. Voluntary abandonment should free their treasury while keeping the artefact available in the world.

## What Changes

- Add a confirmed owner-only abandonment action to the treasury artefact detail.
- Relocate the same artefact to a newly created, defended static Natar village on a free tile.
- Preserve ownership if relocation fails and prevent repeated requests from duplicating villages or artefacts.

## Capabilities

### New Capabilities
- `artefact-abandonment`: Voluntary abandonment and defended Natar relocation.

### Modified Capabilities
None.

## Impact

Treasury templates, build request handling, artefact village generation and database writes; standalone regression coverage. No new external dependencies.
