## 1. Training contract regression coverage

- [x] 1.1 Add a shared troop-training checker covering all five tribes and the Barracks, Stable, and Workshop unit families.
- [x] 1.2 Cover invalid tokens, fields, building types and levels, research, quantities, resources, free crop, queue failures, and time modifiers.
- [x] 1.3 Cover compatible batch merging, duration-changing batch separation, and overdue batch arithmetic.

## 2. Failure-safe training orders

- [x] 2.1 Serialize capacity checking, resource deduction, and queue insertion under the village training lock.
- [x] 2.2 Preserve default low-level queue locking for other callers while preventing recursive locking from the shared order path.
- [x] 2.3 Merge adjacent orders only when their stored unit and per-unit duration match, and restore resources on every queue failure.

## 3. Building interfaces

- [x] 3.1 Extract a shared, valid queue table for Barracks, Stable, and Workshop pages.
- [x] 3.2 Normalize empty-unit states, train button visibility, countdown clamping, wording, and HTML structure across the three buildings.
- [x] 3.3 Verify every tribe exposes exactly its researched units in the correct building and displays the same duration that is enqueued.

## 4. Verification

- [x] 4.1 Run PHP syntax checks for every changed PHP/template/checker file.
- [x] 4.2 Run all `tools/check_*.php` regression checkers in the web container.
- [x] 4.3 Load the three authenticated building pages and confirm successful HTTP responses without PHP warnings, notices, or fatal errors.
- [x] 4.4 Run `openspec validate --strict` and review the final diff for unrelated changes.
