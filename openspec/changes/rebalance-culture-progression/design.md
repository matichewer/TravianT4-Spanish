## Context

The project already contains fast, slow, and normal culture threshold tables and routes expansion checks through shared helpers. The selected mode is currently a hard-coded configuration constant. Existing worlds require a data migration because changing the curve alone does not remove accumulated surplus.

Culture is credited from building production once per real 24 hours. Town Hall celebration duration is divided by server speed while its 500/2000 rewards remain fixed. Equipped culture helmets add 100, 400, or 800 points to daily account production, and each artwork grants one daily production amount capped at 5000.

## Goals / Non-Goals

**Goals:**

- Select the existing slow threshold table everywhere through the shared configuration mode.
- Normalize only surplus accumulated before deployment.
- Make the production operation inspectable, explicit, and repeatable.

**Non-Goals:**

- Do not alter natural production, celebration rewards, helmet bonuses, artwork rewards, or item availability in this change.
- Do not modify NPC/system accounts or database schema.

## Decisions

### Select the existing slow curve

Set the culture mode to the existing slow table rather than introducing a fourth table. This uses the already centralized eligibility and display paths and raises early requirements from 1000/4600/12000 to 2000/8000/20000. A custom multiplier was rejected because it would duplicate a complete, established progression.

### Cap instead of reset or grant

For each regular account with at least one village, compute the slow threshold for `owned villages + 1` and set culture to `min(current culture, threshold)`. This matches the requested one-future-village allowance without increasing anyone below the threshold. Pending settlements do not raise the cap because the rule is explicitly based on owned villages.

### Ship a PHP migration tool

Use a standalone checker-style PHP script so table prefixes and the authoritative threshold function come from application configuration. Default execution is dry-run; `--apply` performs updates. Direct ad-hoc SQL with a duplicated 125-row CASE table was rejected because it can drift from game rules and is difficult to audit.

### Leave item tuning separate

Do not tune helmets or artwork simultaneously with the progression change. The strongest helmet contributes 800 PC/day, equal to 40% of the slow second-village requirement every day, and also raises artwork value. That feedback loop deserves separate item-era/availability analysis after observing the slower curve.

## Risks / Trade-offs

- [Players perceive removed surplus as a loss] → Preview exact per-player reductions and deploy with an explicit operator action.
- [A player earns more culture between preview and apply] → Apply recomputes from current database state; maintenance mode is unnecessary because the cap operation is atomic per account.
- [Accounts above the configured 125-village table cannot be capped] → Report them as skipped instead of inventing a threshold.
- [Items may still trivialize early expansion] → Report their quantitative impact now and handle any reward/availability change separately.

## Migration Plan

1. Deploy the code selecting the slow curve and the migration tool.
2. Run the tool without flags and review affected accounts.
3. Run the tool with `--apply` once.
4. Run it again without flags; expect zero pending changes.
5. Rollback of the curve is a configuration revert. Removed surplus cannot be reconstructed from the post-migration balance, so take a database backup before apply.
