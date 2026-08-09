## 1. Shared Hero Rules

- [x] 1.1 Add shared helpers for progression, strength, army bonuses, and resource production.
- [x] 1.2 Route battle and village production calculations through the shared helpers.

## 2. Safe Attribute State Changes

- [x] 2.1 Add atomic capped attribute allocation to the MySQLi database layer and use it from the hero page.
- [x] 2.2 Make periodic leveling bounded, multi-level aware, and safe under concurrent automation runs.
- [x] 2.3 Preserve existing unspent points when the Book of Wisdom resets attributes.

## 3. Attack Settlement and Interface

- [x] 3.1 Correct attack-time resource settlement to use the target owner's stationed hero and shared production formula.
- [x] 3.2 Correct displayed fighting strength, bonus percentages, resource rates, regeneration, and capped allocation controls.

## 4. Verification

- [x] 4.1 Add hero-attribute regression checks for progression, formulas, caps, and reset behavior.
- [x] 4.2 Run PHP syntax checks, hero regressions, OpenSpec validation, DB consistency queries, and diff checks.
