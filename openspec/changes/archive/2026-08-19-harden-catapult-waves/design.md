## Context

Attack resolution already uses a global database advisory lock, but tied rows lack a stable secondary order and are loaded as a batch. Target allowlists are duplicated between dispatch and impact, while the HTML exposes only a subset. Catapult impacts update several derived values but not production timestamps or construction queues.

## Goals / Non-Goals

**Goals:** deterministic waves, server-authorized selection, one target catalog, and atomic-looking village side effects within the existing procedural architecture.

**Non-Goals:** combat rebalance, changing the chosen missing-target fallback, database migrations, or introducing a framework.

## Decisions

- Put target metadata and rally-point requirements in a shared PHP helper loaded by both dispatch and automation. This prevents three independent allowlists.
- Use `moveid` as the stable tie-breaker because it reflects insertion order and already uniquely identifies movements.
- Revalidate each selected movement immediately before processing. This safely handles rows deleted by an earlier village-destroying attack.
- Accrue production once immediately before the first effective level change, using the recorded arrival time threaded into impact resolution.
- Cancel every queued construction row for the impacted slot. Recomputing legacy queue levels after arbitrary damage is more error-prone and could resurrect destroyed buildings.

## Risks / Trade-offs

- [Players lose prepaid queued construction when its slot is hit] → This is explicit siege behavior and prevents invalid resurrection; document it in tests.
- [A global helper adds another include] → Keep it dependency-free and guarded against duplicate loading.
- [Stale-row checks add queries] → Only due attacks are checked, and correctness outweighs the small per-attack lookup.
