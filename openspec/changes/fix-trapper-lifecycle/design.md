## Context

Trap state is split between `units.u99` (built traps), `units.u99o` (occupied traps), `prisoners` (captured troop groups), `attacks`, and `movement`. The active tables use MyISAM, so multi-table transactions and row-level locking are unavailable. Current request handlers and battle automation mutate these tables in separate statements, which leaves race windows and can remove a prisoner before its return movement exists.

## Goals / Non-Goals

**Goals:**

- Keep trap counts, prisoner rows, attack payloads, and return movements consistent.
- Preserve all existing player-facing trapper actions while enforcing their server-side preconditions.
- Make trap allocation composition-aware and prevent fixed-slot shielding.
- Keep trapped troops in home-village upkeep calculations.
- Cover the lifecycle with a repeatable regression that runs against an isolated database.

**Non-Goals:**

- Replacing MyISAM or redesigning the global automation scheduler.
- Changing trap costs, capacities, travel speeds, or the documented 25% liberation loss.
- Adding a new framework, dependency, or persistent schema column.

## Decisions

### Use MyISAM table locks around trap state transitions

Capture and release database methods will acquire narrowly scoped `LOCK TABLES` sets, re-read authoritative state while locked, perform all related writes, compensate any partial inserts when necessary, and always unlock in `finally`-style cleanup. A conditional update alone was considered, but it cannot safely coordinate a partial proportional allocation with the matching `prisoners` row across MyISAM tables.

### Move lifecycle commits into database-layer operations

Battle and request code will calculate troop distributions and travel times, while database methods will commit capture, manual release, disband, own-prisoner merge, and allied return operations. A prisoner row is deleted only after the destination attack or movement state exists. This keeps failure handling close to the writes and makes replay behavior testable.

### Allocate traps proportionally with randomized tie-breaking

Each troop slot receives its floor share of the available traps. Remaining traps go to the largest fractional remainders, with randomized tie-breaking so equal troop groups cannot rely on slot order. The allocation always preserves the requested total and never exceeds any troop count.

### Derive trapped upkeep from prisoner rows

`Technology::getAllUnits()` will add every prisoner group whose `from` is the village, mapping positional `t1..t10` values to that owner's tribe and adding trapped heroes to the hero count. Existing upkeep logic can then account for them without a separate crop formula.

### Enforce the selected training field

Trap training requires a Gaul session, a non-great queue, and a completed type-36 field at the submitted field ID. Village-wide capacity remains the sum of every completed trapper.

### Normalize release-report indexing

Combat templates will read the extra release message after all eleven trapped-unit fields. Access will be guarded with `isset()` to avoid malformed legacy report warnings.

## Risks / Trade-offs

- [Table locks briefly serialize trap operations across the server] → Keep lock scopes short and exclude travel-time or battle calculations.
- [A PHP process could terminate while holding locks] → MariaDB releases table locks when the connection closes.
- [Random tie-breaking makes exact slot allocation nondeterministic] → Regressions assert conservation, proportional bounds, and capacity rather than a fixed tied slot.
- [Legacy rows could already have mismatched `u99o`] → Every capture clamps against built traps and building capacity; release updates use non-negative arithmetic.

## Migration Plan

Deploy the PHP changes normally; no schema migration is required. Existing prisoner rows remain compatible. Rollback consists of reverting the code changes, although the new regression should be run before any rollback because older code reintroduces consistency windows.

## Open Questions

None.
