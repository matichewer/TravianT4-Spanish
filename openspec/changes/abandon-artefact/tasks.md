## 1. Relocation

- [x] 1.1 Extract reusable artefact village provisioning and implement guarded relocation with failure cleanup.

## 2. Treasury action

- [x] 2.1 Add owner-only confirmed POST, session protection, sitter exclusion and result navigation.

## 3. Verification

- [x] 3.1 Add regression checks for successful relocation, effects, identity, invalid requests, replay, occupied tiles and failed writes.
- [x] 3.2 Run all standalone checkers and verify treasury pages through HTTP; document the mechanic.

Validation: 73 abandonment assertions pass, including actual crop accrual with the old diet effect. HTTP GET/POST of the treasury detail with temporary tables verifies confirmation, successful relocation and the new location link. The initial full run passed all 133 checkers. A later full run passed 132/133 after concurrent edits to storage data and its checker: `check_storage_buildings.php` still compares against 200000 while its updated message and result are 92000. Those unrelated edits were left untouched. OpenSpec strict validation and diff whitespace checks pass.
