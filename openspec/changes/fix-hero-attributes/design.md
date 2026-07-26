## Context

Hero behavior is distributed across the attribute template, inventory actions, periodic automation, battle calculations, and two independent village-production paths. The normal village path uses the intended hero production formula, while attack settlement has a stale implementation that reads the wrong data shape and owner. Attribute updates and leveling also use separate non-conditional database writes, leaving boundary and concurrency failures.

## Goals / Non-Goals

**Goals:**

- Establish one shared implementation for hero strength, army bonuses, progression boundaries, and resource rates.
- Enforce level and attribute limits on the server, including under concurrent requests.
- Preserve the complete point pool when attributes are reset.
- Make every village resource-settlement path apply the same eligible hero bonus.
- Cover boundary behavior with standalone PHP regression checks.

**Non-Goals:**

- Rebalance Travian's existing values for strength, percentages, or resource rates.
- Redesign the hero interface or inventory system.
- Add database tables, columns, or external dependencies.

## Decisions

1. Add a small `GameEngine/Hero.php` helper module containing pure calculations. Pure helpers let the template, battle engine, village engine, automation, and regression checks share formulas without introducing a framework or new object lifecycle.
2. Keep the established values: Roman strength points are worth 100, other playable tribes are worth 80, army bonus points are worth 0.2% each, all-resource production is 3 per point per resource, and focused production is 10 per point.
3. Enforce point spending with one conditional SQL update. The update decrements an available point and increments an attribute only when `points > 0` and the attribute is below 100, preventing stale concurrent requests from minting points.
4. Advance levels with a single compare-and-set update based on the full experience table. This catches up multiple earned levels at once, never reads beyond the table, and prevents concurrent automation runs from awarding the same levels twice.
5. Reset attributes with one SQL statement that adds allocated points to the existing unspent balance before clearing attributes and restoring the default all-resource selection.
6. Resolve attack-time production using the target village owner and target village reference, then pass that hero through the same production helper used by normal village requests.

## Risks / Trade-offs

- [Existing heroes already above a limit could retain excess stored values] → Runtime calculations clamp values and allocation cannot increase them further; no destructive data migration is performed.
- [Adding a shared include can expose path-resolution issues in legacy entry points] → Use `require_once __DIR__` from engine files.
- [Changing attack-time settlement changes resource totals compared with the buggy behavior] → This restores the same formula already shown and used on normal village visits.
- [Atomic SQL is implemented in the active MySQLi database driver] → The repository configuration and supported Docker stack use `DB_TYPE = 1`/MySQLi.
