## Context

See `proposal.md` for motivation. Hero liveness and position are split across `hero`, `units`, and the `training` row used for paid revival. The current bucket path updates these independently and ignores an existing revival row. The database tables use MyISAM, so SQL transactions cannot provide atomic rollback.

## Goals / Non-Goals

**Goals:**

- Resolve the destination deterministically from the paid revival when one exists.
- Serialize bucket revival per account and validate every affected row before mutation.
- Keep all related mutations in one database-layer operation.
- Guarantee one stationary hero across the player's villages after success.

**Non-Goals:**

- Refund resources already paid for a revival.
- Change revival costs or duration.
- Repair unrelated historic hero inconsistencies automatically.

## Decisions

### Centralize bucket revival in the database layer

A dedicated operation will validate the hero, item, selected village, and optional revival queue under a per-account named lock before applying changes. This avoids leaving the request handler responsible for a fragile sequence of independent updates. Keeping the current sequential calls was rejected because partial or competing requests can recreate inconsistent state.

### Prefer the queued revival village

If a valid paid revival exists, its `vref` is the authoritative destination because that is where the player paid to revive the hero. Without a queue, the selected owned village remains the destination, preserving existing bucket behavior. Using `hero.home` was rejected because it represents the resource/home relationship and may differ from the village where revival was purchased.

### Remove owned-village hero copies before placing one

On successful revival, all `units.hero` values in villages owned by the account are cleared and the destination is set to one. This explicitly restores the single-hero invariant. Incrementing the destination was rejected because stale state could duplicate the hero.

### Consume the item only after preconditions are established

The item is selected by id, owner, type, unused state, and positive count. The operation mutates only after all destination and hero checks pass. A named lock prevents concurrent revival requests; guarded writes make unexpected failures detectable despite MyISAM's lack of transactions.

## Risks / Trade-offs

- [A MyISAM write can fail after an earlier write succeeds] → Validate first, serialize with a named lock, use simple guarded statements, and order item consumption last so a failed placement does not charge the player.
- [Historic duplicate paid revival rows may exist] → Use the earliest valid row as destination and remove the player's paid hero-revival rows after successful completion so none can later create another hero.

## Migration Plan

Deploy the PHP change without a schema migration. Existing stuck accounts can be repaired manually; future bucket uses follow the corrected operation. Rollback consists of reverting the code change.
