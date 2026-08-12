## Context

The three buildings submit through one procedural request path and share queue persistence and global automation completion. The training table is MyISAM, so transactions cannot provide atomic ordering; named MariaDB locks and compensating resource refunds are the available compatibility mechanism. Existing tests cover selected time modifiers and Workshop authorization but not the complete shared contract.

## Goals / Non-Goals

**Goals:**

- Keep eligibility, duration, deduction, and enqueue decisions in shared PHP logic.
- Serialize the complete resource-and-queue operation per village.
- Preserve duration changes between orders.
- Give all three templates the same structurally valid queue presentation.
- Add deterministic regression coverage plus live-container checks.

**Non-Goals:**

- Adding queue cancellation, instant completion, or new troop types.
- Changing canonical Travian costs, research requirements, or duration tables.
- Migrating the legacy schema or storage engine.
- Changing Great Barracks, Great Stable, Great Workshop, Residence, Palace, or Trapper UI beyond shared code required for compatibility.

## Decisions

### Serialize the full order in `Technology`

Acquire the existing village training lock before rechecking capacity, deducting resources, and writing the queue, and release it in `finally`. The low-level queue method will support callers that already hold the lock so it does not recursively acquire the same MariaDB named lock.

This is preferred over relying only on the atomic resource `UPDATE`: that prevents negative resources but does not keep queue ordering and compensating refunds isolated from another request. A database transaction is not viable while the relevant tables remain MyISAM.

### Merge only duration-compatible adjacent orders

The persistence layer will append to the last batch only when queue family, stored unit identifier, and `eachtime` all match. Otherwise it inserts a new row beginning after the prior batch. This preserves order-time modifiers while retaining the compact representation for truly identical orders.

Recalculating old queue entries after upgrades or equipment changes was rejected because it retroactively changes a purchase the player already made.

### Centralize queue rendering

Introduce a shared PHP include for the repeated Barracks, Stable, and Workshop queue table. Each building supplies only its queue type. This removes the currently divergent closing tags and timer wording while keeping the legacy template architecture.

### Test the shared contract at two levels

A standalone checker will exercise eligibility, validation, deduction/refund, timing, queue separation, and completion-facing arithmetic with controlled stubs. Existing specialized checkers remain in place. Live PHP syntax, every `tools/check_*.php`, and authenticated building page requests will validate integration against MariaDB.

## Risks / Trade-offs

- [Named locks are connection-scoped and may time out under load] → Reject the order without deduction and always release in `finally`.
- [A process can die after deduction but before compensation] → Keep the vulnerable interval inside a small serialized block; a full crash-proof guarantee would require an InnoDB migration, which is out of scope.
- [Changing the low-level queue API may affect other training buildings] → Preserve its default locking behavior and add an explicit already-locked parameter only for the shared caller.
- [Shared template extraction can alter fragile legacy markup] → Compare rendered pages for all three buildings and lint the include plus callers.

## Migration Plan

Deploy PHP and template changes without a schema migration. Existing queue rows keep their stored durations and continue processing normally. Rollback consists of reverting the code; no data conversion is required.
