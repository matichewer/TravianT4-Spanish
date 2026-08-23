## 1. Farm-list data safeguards

- [x] 1.1 Add owned-list lookup helpers needed by the map and continuation flow
- [x] 1.2 Prevent duplicate targets in an owned farm list and expose a duplicate check to the form

## 2. Map and form workflow

- [x] 2.1 Render the map action for eligible villages and oases with disabled Gold Club and rally-point states
- [x] 2.2 Pre-fill coordinates and select an owned list in the existing slot form
- [x] 2.3 Preserve a map target through first-list creation and redirect to the slot form
- [x] 2.4 Display clear validation feedback for duplicate and failed slot additions

## 3. Verification

- [x] 3.1 Add a standalone regression checker for action eligibility, continuation, ownership, and duplicate protection
- [x] 3.2 Validate the OpenSpec change, run the focused checker, run all regression checkers, and smoke-test affected pages
