## Why

Occupied-oasis tooltips can display the alliance and tribe from a previously rendered map tile instead of the oasis owner. This contradicts the correct information shown in the tile details dialog and can misidentify the oasis.

## What Changes

- Resolve occupied-oasis tooltip identity from the oasis record's owner.
- Keep tooltip identity values isolated per rendered tile in both standard and large maps.
- Add regression coverage for the occupied-oasis tooltip data source.

## Capabilities

### New Capabilities

- `map-tile-tooltips`: Defines consistent owner information in map tooltips and tile details.

### Modified Capabilities

None.

## Impact

The standard and large map PHP templates and their standalone regression checker are affected. No database schema, API, or dependency changes are required.
