## 1. Authoritative intermediate curve

- [x] 1.1 Add a distinct culture mode derived at two thirds of every official x1 threshold with nearest-100 rounding, preserving mode 2 unchanged and documenting the 10-day target.
- [x] 1.2 Extend the authoritative threshold/status/rescale helpers to recognize the new mode and verify its full table is monotonic and complete.
- [x] 1.3 Select the intermediate mode in both the active configuration and `install/data/constant_format_mysqli.tpl`, updating their operator-facing comments and examples.

## 2. Existing-world balance migration

- [x] 2.1 Update `tools/rescale_culture_points.php` defaults and documentation for the mode-1-to-intermediate conversion while retaining explicit source/destination and recovery override support.
- [x] 2.2 Verify preview mode is read-only and reports old/new balances, capacity and progress for every regular player.
- [x] 2.3 Verify apply mode converts all balances transactionally, rejects capacity drift, records a unique administration marker and prevents accidental repetition.
- [x] 2.4 Document the production rollout and reverse-conversion rollback commands so configuration and stored balances cannot be switched independently by mistake.

## 3. Regression coverage

- [x] 3.1 Extend `tools/check_culture_balance.php` to pin the intermediate formula, representative early thresholds, monotonicity, selected defaults and unchanged income-source rules.
- [x] 3.2 Add conversion cases covering exact thresholds, fractional interval progress, the final configured interval and round-trip capacity preservation.
- [x] 3.3 Update settler and conquest regression fixtures to use the intermediate mode and prove founding, pending-settlement accounting, conquest and displayed status share the authoritative thresholds.
- [x] 3.4 Run strict OpenSpec validation, every `tools/check_*.php` checker in the web container, and focused page checks for culture progress and expansion eligibility.
