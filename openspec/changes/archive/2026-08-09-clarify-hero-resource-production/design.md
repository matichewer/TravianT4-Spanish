## Context

The hero template calculates correct all-resource and focused rates, but renders them as unlabeled numbers. The same template also changes the selected distribution, so it is the narrowest place to explain both the unit and when the change takes effect.

## Goals / Non-Goals

**Goals:**

- Make each displayed hero resource bonus identifiable as a positive hourly rate.
- Explain continuous accrual, forward-only application, and replacement of the previous distribution.
- Guarantee that the displayed value is an absolute hourly addition to village production.
- Preserve the compact legacy layout and existing calculations.

**Non-Goals:**

- Rebalance hero production.
- Change settlement timing, storage limits, or hero eligibility.
- Redesign the hero attributes page.

## Decisions

1. Render rates as `+<amount>/h` beside every distribution option and in the resource tooltip. A compact suffix fits the existing row and matches the production-per-hour convention elsewhere in the game.
2. Add one short explanatory sentence above the options. It will state that the bonus accrues per hour from the moment of selection, is not immediate, and replaces rather than stacks with the previous distribution.
3. Treat the rate returned by `heroVillageResourceBonus()` as an absolute number and preserve the engine's direct `base production + hero rate` operation. The hero contribution remains outside percentage calculations for fields, buildings, oases, and Plus bonuses.
4. Extend the existing standalone hero regression script with rate, engine-path, and markup assertions. This keeps coverage lightweight in a repository without a test framework and prevents either the direct-addition rule or its explanation from regressing.

## Risks / Trade-offs

- [The explanation adds vertical height to a compact panel] → Keep it to one short player-facing sentence and do not widen the sidebar or page layout.
- [The `/h` abbreviation may be less explicit than a full phrase] → Pair it with explanatory text containing “por hora”.
- [A future refactor could accidentally percentage-scale the hero rate] → Assert the direct-addition expression used by normal village production and numeric speed-three examples.
