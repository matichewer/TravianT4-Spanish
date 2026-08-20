## 1. Data and clocks

- [x] 1.1 Add and backfill the `conquered_at` oasis column in fresh-install and migration schemas
- [x] 1.2 Make oasis loyalty regeneration preserve fractional elapsed time and concurrency safety

## 2. Lifecycle transitions

- [x] 2.1 Add a release workflow that accrues production and returns reinforcements before freeing an oasis
- [x] 2.2 Route voluntary, destruction, and account-deletion releases through the safe workflow
- [x] 2.3 Record and clear conquest history during conquest and release

## 3. Presentation

- [x] 3.1 Correct owned-oasis coordinate navigation and conquest-date display
- [x] 3.2 Keep both profile views' type 6 bonus rendering isolated from iron

## 4. Regression and verification

- [x] 4.1 Add focused non-destructive checks for loyalty clocks, release ordering, history, coordinates, and profile rendering
- [x] 4.2 Run strict OpenSpec validation, focused oasis checks, full regression suite, syntax checks, and diff checks
