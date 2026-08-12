## 1. Culture progression

- [x] 1.1 Select the existing slow culture threshold mode in server configuration
- [x] 1.2 Extend expansion regression coverage to assert the configured slow thresholds

## 2. Existing-world normalization

- [x] 2.1 Add a preview-first, explicit-apply tool that caps regular players at the slow threshold for one additional owned village
- [x] 2.2 Cover surplus, below-cap, system-account, maximum-table, and repeat-run behavior with a standalone checker

## 3. Verification and operations

- [x] 3.1 Run PHP syntax checks, focused regression checkers, all standalone checkers, and OpenSpec validation
- [x] 3.2 Document the production backup, preview, apply, and verification commands in the implementation handoff

## 4. Passive production and helmets

- [x] 4.1 Apply a centralized 25% factor to raw village culture production and keep daily credit, progress display, Plus breakdown, and artwork calculations consistent
- [x] 4.2 Change culture helmet effects and player-facing descriptions to 25/100/200 PC per day
- [x] 4.3 Extend regression coverage for rounding, multiple villages, helmet contribution, and unchanged celebration rewards

## 5. Artwork cooldown

- [x] 5.1 Add installer and production schema support for a durable account artwork-use timestamp
- [x] 5.2 Enforce exactly one artwork per request and a concurrency-safe rolling 24-hour cooldown without consuming rejected items
- [x] 5.3 Display the cooldown restriction and remaining state consistently in artwork interfaces
- [x] 5.4 Add regression coverage for first use, repeated use, elapsed cooldown, invalid quantities, and atomic failure behavior

## 6. Final verification

- [x] 6.1 Run syntax checks, focused culture/item tests, every standalone checker, page smoke tests, diff checks, and strict OpenSpec validation
- [x] 6.2 Provide the exact production schema, deploy, verification, preview, and apply command order
