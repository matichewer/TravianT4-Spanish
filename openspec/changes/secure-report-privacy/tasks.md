## 1. Authorization

- [x] 1.1 Add a centralized, type-aware authorized report lookup with safe identifiers and fields
- [x] 1.2 Use the centralized lookup in direct report views and remove message-derived access
- [x] 1.3 Enforce reader authorization when rendering report BBCode
- [x] 1.4 Enforce reader authorization in repeat-attack report loading

## 2. Report Mutations

- [x] 2.1 Scope bulk delete, archive, and unarchive operations to the authenticated owner
- [x] 2.2 Audit all report mutation call sites for owner scoping

## 3. Battle Intelligence

- [x] 3.1 Strip defensive flags and intelligence from total-loss attacker payloads
- [x] 3.2 Hide defensive sections in total-loss attack and espionage report templates

## 4. Verification

- [x] 4.1 Add and run authorization and mutation regression coverage
- [x] 4.2 Run PHP syntax checks and inspect the final diff for remaining report privacy bypasses
