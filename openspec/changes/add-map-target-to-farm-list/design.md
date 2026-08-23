## Context

See `proposal.md` for motivation. Map actions are rendered by `Templates/Map/vilview.tpl`, while farm lists are managed through the rally-point Gold Club tab. The existing slot form already selects an owned list, collects troops, validates coordinates, calculates wrapped-map distance, and calls an ownership-checked database helper.

## Goals / Non-Goals

**Goals:**

- Reuse the existing full-page slot editor and its troop configuration.
- Preserve a selected target while a player creates their first list.
- Keep all authorization and duplicate enforcement server-side.
- Make the link work from both full tile details and the AJAX popup.

**Non-Goals:**

- Adding a target without choosing troop quantities.
- Creating or editing lists inside the map popup.
- Changing Gold Club eligibility or farm-list data structures.
- Automatically selecting a particular raid troop composition.

## Decisions

### Navigate to the existing rally-point forms

The map action will use the building-type route (`build.php?gid=16`) with a small set of integer query parameters for the target. The slot form will read those parameters only for display defaults; its existing POST path remains authoritative.

This avoids duplicating the large troop form or introducing a new AJAX mutation. A popup selector was considered, but it cannot safely complete the operation because every farm slot also needs troop quantities.

### Resolve no-list state on the server

The map can determine whether the account owns lists. If none exist, its link opens list creation with the target coordinates as continuation parameters. After a successful list insert, the new list id is obtained and the request redirects to the slot form with that id and the preserved coordinates.

Using a redirect after creation prevents form resubmission and makes the continuation explicit. Automatically creating a generically named list was rejected because list name and source village are player choices.

### Enforce uniqueness in the database helper

The slot insert will use an `INSERT ... SELECT` that requires an owned destination list and excludes an existing row with the same list id and target wref. The helper will distinguish success from a rejected insert, while the form will check for an existing owned-list target to show the specific duplicate message.

This keeps crafted requests safe even if they bypass the form. A schema-level unique key would be stronger, but it would require a live-world migration and is unnecessary for this scoped UI path.

### Render unavailable states without navigation

For eligible targets, the action is rendered disabled with a reason when Gold Club is inactive or the current village lacks a rally point. Eligibility is checked before list existence so the map never links into an inaccessible workflow.

## Risks / Trade-offs

- [Concurrent identical submissions could race without a unique database key] → Use an atomic `NOT EXISTS` insert; MariaDB evaluates it in the write statement, reducing the practical window while avoiding a migration.
- [Query parameters can be forged] → Cast coordinates and ids to integers, re-resolve the world reference, and retain ownership checks at insert time.
- [A list can belong to a different source village than the current one] → Continue to permit all owned lists as the existing editor does; distance is calculated from the current village used to open the form, consistent with the current workflow.
- [Legacy templates may emit notices for absent POST values] → Introduce explicit request defaults for the touched coordinate fields.

## Migration Plan

No schema migration is required. Deploy the PHP/template changes live, run the new regression checker and the full checker suite, then smoke-test map popup navigation. Rollback consists of reverting these files; existing list and slot data remains compatible.
