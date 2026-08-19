## Context

The standard and large maps both load their grid through the village-oriented `getMInfo()` query. For oasis tiles they later load `getOMInfo()`, but currently use it only for the player name; alliance and tribe remain derived from the earlier record and local variables can retain values across loop iterations.

## Goals / Non-Goals

**Goals:**

- Use one authoritative owner identity for every tooltip field.
- Keep the standard and large map behavior aligned.
- Prevent identity values from leaking between rendered tiles.

**Non-Goals:**

- Changing oasis ownership mechanics or stored data.
- Refactoring the overall map renderer or database layer.

## Decisions

- Select the tile-specific record before resolving identity: `getOMInfo()` for oasis tiles and the existing map/village data for villages. This keeps all player-derived fields tied to the same owner ID.
- Initialize tooltip identity defaults on every loop iteration. This handles unoccupied tiles and owners without an alliance without retaining a previous tile's values.
- Apply the same focused logic to both map templates because they implement separate render loops.

An alternative was to change `getMInfo()` to join oasis data too, but duplicate column names would make the legacy `SELECT *` result ambiguous and could affect unrelated callers.

## Risks / Trade-offs

- [The duplicated templates could drift again] → Cover both files in one regression checker.
- [Extra per-tile owner lookup remains] → Preserve the existing query count; this fix only corrects which owner feeds the already-present lookups.
