## Context

The project already contains fast, slow, and normal culture threshold tables and routes expansion checks through shared helpers. The selected mode is currently a hard-coded configuration constant. Existing worlds require a data migration because changing the curve alone does not remove accumulated surplus.

Culture is credited from raw village building production once per real 24 hours. A four-village production sample totals 5575 PC/day without a culture helmet, enough to traverse the slow curve's fourth-to-fifth-village gap in under four days. Town Hall celebration duration is divided by server speed while its 500/2000 rewards remain fixed. Artwork currently has no cooldown and can consume multiple units in one request.

## Goals / Non-Goals

**Goals:**

- Select the existing slow threshold table everywhere through the shared configuration mode.
- Normalize only surplus accumulated before deployment.
- Make the production operation inspectable, explicit, and repeatable.
- Make passive production, helmet bonuses, artwork rewards, and every displayed daily total share one balanced calculation.
- Enforce a durable account-wide rolling artwork cooldown.

**Non-Goals:**

- Do not modify NPC/system accounts or unrelated database schema.
- Do not change celebration rewards or adventure/auction item drop rates.

## Decisions

### Select the existing slow curve

Set the culture mode to the existing slow table rather than introducing a fourth table. This uses the already centralized eligibility and display paths and raises early requirements from 1000/4600/12000 to 2000/8000/20000. A custom multiplier was rejected because it would duplicate a complete, established progression.

### Cap instead of reset or grant

For each regular account with at least one village, compute the slow threshold for `owned villages + 1` and set culture to `min(current culture, threshold)`. This matches the requested one-future-village allowance without increasing anyone below the threshold. Pending settlements do not raise the cap because the rule is explicitly based on owned villages.

### Ship a PHP migration tool

Use a standalone checker-style PHP script so table prefixes and the authoritative threshold function come from application configuration. Default execution is dry-run; `--apply` performs updates. Direct ad-hoc SQL with a duplicated 125-row CASE table was rejected because it can drift from game rules and is difficult to audit.

### Scale raw village production once

Keep `vdata.cp` as the raw sum contributed by completed building levels and apply a 25% factor only in the authoritative account-wide daily-production function. This avoids a destructive rewrite of every village and keeps future construction/demolition arithmetic intact. Round the account-wide raw village sum to the nearest integer before adding the helmet bonus, so 5575 becomes 1394 rather than accumulating fractional state.

### Reduce culture helmet values explicitly

Change the culture helmet table and player-facing descriptions to 25/100/200 PC/day. Scaling the old bonuses through the village factor was rejected because helmets are not village production and their displayed integer effects must remain explicit.

### Reuse balanced production for artwork

Artwork continues to call the authoritative daily-production function, so it naturally grants the reduced village total plus the reduced equipped helmet bonus, capped at 5000. Do not create a second artwork formula.

### Store a durable artwork timestamp on the user account

Add an `artwork_last_used` integer timestamp defaulting to zero. Consumption must atomically claim the cooldown and deduct the requested item only after validating ownership and quantity; although inventory requests currently accept an amount, artwork must reject every amount other than exactly one. Add the column to installer schema and `tools/migrations.sql` with an idempotent production migration.

## Risks / Trade-offs

- [Players perceive removed surplus as a loss] → Preview exact per-player reductions and deploy with an explicit operator action.
- [A player earns more culture between preview and apply] → Apply recomputes from current database state; maintenance mode is unnecessary because the cap operation is atomic per account.
- [Accounts above the configured 125-village table cannot be capped] → Report them as skipped instead of inventing a threshold.
- [Rounding loses fractions across the account] → Round once after summing all raw villages, limiting error to at most half a point per account per day.
- [Concurrent artwork requests bypass cooldown] → Claim the timestamp through a conditional database update before granting culture or consuming the object, and restore it if the later item operation fails.
- [Existing production lacks the cooldown column] → Include an idempotent schema migration and explicit deployment command before the new code receives traffic.

## Migration Plan

1. Take a database backup.
2. Apply the idempotent artwork cooldown schema migration immediately before deploying the PHP code.
3. Deploy the balanced production, helmet, artwork and slow-curve code.
4. Run the normalization tool without flags and review affected accounts.
5. Run the tool with `--apply` once, then preview again and expect zero pending changes.
6. Rollback requires restoring the prior code; the added timestamp column may remain harmlessly. Removed culture surplus requires the database backup to reconstruct.
