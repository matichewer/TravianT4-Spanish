## Context

See `proposal.md` for motivation. Culture thresholds currently live in `GameEngine/Data/cp.php`; `CP = 1` selects the official x1 table even though the economy is x3. Eligibility, status rendering and balance conversion already share authoritative helpers, and the existing conversion tool can preserve interval-relative progress between named modes.

The repository also contains mode 2, an undocumented historical custom table explicitly marked as unsuitable. Reusing it would not make the 10-day target auditable and would retain values with no stated derivation.

## Goals / Non-Goals

**Goals:**

- Make the intended 10-day pacing traceable to a deterministic rule.
- Preserve one authoritative threshold lookup for every gameplay and presentation consumer.
- Convert the live world's balances without changing enabled slots or relative progress.
- Make fresh installations and reinstalls select the same balanced default.

**Non-Goals:**

- Rebalance building CP/day values or daily credit timing.
- Change celebrations, culture helmets, artwork, conquest loyalty bonuses or world speed.
- Guarantee exactly 10 calendar days for every account; construction choices and active culture sources intentionally affect actual time.

## Decisions

### Introduce a distinct intermediate mode derived from the x1 table

Add a new mode rather than relabeling official mode 0 or 1 or silently repurposing historical mode 2. For each village count, calculate `round((x1 requirement * 2 / 3) / 100) * 100`; village 1 remains zero. Materializing the resulting values once in the culture data is acceptable, but regression checks must prove every row follows that formula.

Two thirds is chosen because unchanged PC production makes a 15-day target approximately 10 days. Rounding to the nearest 100 keeps player-visible thresholds legible and deterministic. Alternatives considered were official x3, rejected as too fast at roughly 5 days, and historical mode 2, rejected because its progression has no documented balance model.

### Keep threshold rebalance independent from culture income

Only the selected requirement table changes. Passive production, fixed-amount speed classification and production-based celebration/artwork rules remain untouched. This preserves the meaning of existing player investments and limits the balance change to the stated pacing knob.

### Extend the existing interval-preserving converter

The live migration uses the existing curve-to-curve interpolation: locate the player's current interval on mode 1, preserve the fractional position inside it, and map that position into the corresponding interval on the new mode. Accounts beyond the final configured interval retain the converter's proportional-tail behavior.

The command remains preview-first and performs apply mode in one transaction. It validates slot capacity before committing, records a mode-specific administration log marker and rejects repeated application unless the existing explicit recovery override is used.

### Switch configuration and balances as one deployment operation

Code supporting both old and new modes ships first. The operator previews conversion, applies mode 1 to the new mode, and enables the new default in the same maintenance operation. Fresh installer output selects only the new mode; no player-facing selector is added.

## Risks / Trade-offs

- [Actual pacing differs by play style] → Describe 10 days as a balance target, not a guaranteed timer, and pin the mathematical two-thirds rule in tests.
- [Configuration changes before balance conversion] → Document and test the deployment order; preview and apply explicitly name source and destination modes.
- [Rounding could create duplicate adjacent thresholds late in the table] → Validate that every configured threshold is monotonic and that conversion preserves capacity across the full table.
- [Legacy mode 2 creates operator ambiguity] → Give the new curve a distinct mode and descriptive constant/comment; do not alter historical mode semantics as part of this change.
- [Main specs contain older culture-source expectations] → Limit this delta to threshold/default and source-preservation behavior; reconcile unrelated historical spec drift separately rather than changing runtime behavior here.

## Migration Plan

1. Deploy code that recognizes the new intermediate mode while the world still selects mode 1.
2. Run the balance conversion in preview mode from mode 1 to the intermediate mode and review slot/progress invariants.
3. In a maintenance window, apply the conversion transaction and switch the generated/runtime configuration default to the intermediate mode as one release operation.
4. Run culture, settler and conquest regression checkers and verify the culture progress interface for representative accounts.
5. Rollback, if required, by using the same converter in the reverse direction and restoring mode 1; preview and validate capacity before applying.
