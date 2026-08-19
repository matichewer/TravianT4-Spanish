## 1. Revival Operation

- [x] 1.1 Add a serialized database operation that validates and completes water-bucket revival in the queued revival village or selected owned village.
- [x] 1.2 Update inventory handling to use the centralized operation instead of independent hero, unit, and item writes.

## 2. Regression Coverage

- [x] 2.1 Add a standalone checker for queued revival completion, ordinary bucket revival, single placement, and invalid-use behavior.
- [x] 2.2 Run PHP syntax checks, the new checker, and the full `tools/check_*.php` regression suite.
