## Context

Weekly scores are cached on both `users` and `alidata`. Combat currently updates those rows independently using caller-supplied alliance IDs, while membership updates change only `users.alliance`. This permits the two caches to diverge. See `proposal.md` and `specs/ranking-consistency/spec.md`.

## Goals / Non-Goals

**Goals:**

- Make the player row the ownership source for every weekly score delta.
- Preserve the cached alliance totals needed by weekly medal distribution.
- Keep alliance totals consistent across membership changes.

**Non-Goals:**

- Reconstruct historical production data from combat reports.
- Change the definition of net raiding or combat-point weights.
- Change lifetime attack and defense counters.

## Decisions

### Centralize weekly player/alliance deltas

Add a single joined database update that changes a player's weekly field and derives the alliance from that same player row before applying the alliance delta. This replaces paired calls with independently calculated alliance IDs. Dynamic aggregation was considered, but cached alliance fields are consumed by weekly medal processing and are cheaper to keep consistent at write time.

### Transfer cached weekly contribution on membership changes

Add a dedicated membership operation that synchronizes the player's pending population delta, locks the player, subtracts the player's weekly contribution from the old alliance, changes membership, adds it to the new alliance, and rebases both alliance population baselines. This preserves both the current-member sum invariant and already-earned growth.

### Repair existing alliance totals

Provide an idempotent reconciliation operation that resets cached alliance weekly fields from current member sums. A regression checker will validate it; production can run it once after deployment without changing player scores.

### Suppress redundant current-entry rows at query/render time

Render the separator and current-entry row only when the calculated rank exceeds ten. This retains the useful out-of-range context without duplicating visible entries.

## Risks / Trade-offs

- [Concurrent combat and membership update] → Use a database transaction and row locks for membership transfer; score updates derive membership in SQL.
- [Legacy callers directly change `users.alliance`] → Replace all live alliance workflow calls and keep the low-level generic field updater unchanged for unrelated uses.
- [Existing production drift] → Reconcile alliance caches once from current player scores after deploying the corrected write paths.

## Migration Plan

Deploy the code, run the alliance weekly-score reconciliation once, then verify all standalone checkers and both ranking pages. Rollback restores the previous code; reconciliation only changes derived alliance counters and can be rerun safely.
