## Context

Oasis state is split across `wdata`, `odata`, village production, enforcement, and movement rows. `lastupdated` and `lastupdated2` are active clocks and cannot also serve as immutable conquest history. Player-gameplay release paths currently span the Hero's Mansion, village destruction, and account deletion.

## Goals / Non-Goals

**Goals:**
- Centralize release ordering so every caller preserves production and troops.
- Make loyalty accrual deterministic across sweep frequencies.
- Keep fresh and already-installed worlds compatible through an additive migration.

**Non-Goals:**
- Change oasis bonus percentages, annexation radius, combat balance, or raid rules.
- Redesign the Hero's Mansion UI.
- Change administrative hard-deletion semantics, which deliberately bypass ordinary gameplay cleanup.

## Decisions

1. Use whole-point accrual with a remainder-preserving clock, matching village loyalty. The clock advances only by the seconds consumed by awarded points; when no whole point is earned it remains unchanged. This avoids both lost fractions and sweep-frequency-dependent rounding.
2. Keep troop return orchestration in `Automation`, because it owns movement timing and unit conversion. Add a lifecycle wrapper there that accrues production, returns troops, then delegates the database state reset. Destruction/deletion paths use the wrapper; the low-level database helper remains suitable for fixtures and emergency cleanup.
3. Add `odata.conquered_at` as an unsigned timestamp. Conquest writes it, release clears it, and the installer plus idempotent migration create it. Existing occupied rows are backfilled from `lastupdated2`, the closest preserved historical clock available.
4. Fix presentation at the source and add static regression assertions for all duplicated profile switches and coordinate order.

## Risks / Trade-offs

- [Existing conquest timestamps are approximate during backfill] → Use `lastupdated2` only once; all future conquests are exact.
- [Returning troops during bulk deletion can add movements while cleanup is running] → Return oasis troops before deleting village-related movement rows and skip invalid/missing home villages safely.
- [Schema not migrated before PHP deploy] → Read the new field defensively in the template and retain a clock fallback; writes require the migration to be applied as part of deployment.

## Migration Plan

1. Deploy the additive installer and migration definition.
2. Apply `tools/migrations.sql` to existing worlds before exercising oasis conquest.
3. Deploy PHP/template changes and run focused plus full regression suites.
4. Rollback PHP safely while leaving the additive column in place if needed.
