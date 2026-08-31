## Why

Unread report badges currently do not communicate their report kind reliably: route badges can appear green, while green should be reserved for hero adventures. Players also lack a direct way to list only reports they have not opened.

## What Changes

- Classify unread adventure reports separately from other miscellaneous reports.
- Give unread report badges stable semantic colors: routes light gray, trade dark gray, and adventures green.
- Add a "No leídos" report tab that lists every unarchived unread report owned by the player.
- Keep the report navigation usable within its fixed width despite the additional filter.
- Add regression coverage for unread ownership, deletion/archive visibility, classification, filtering, and navigation rendering.

## Capabilities

### New Capabilities

- `report-unread-navigation`: Unread report classification, badge presentation, and the unread-only report list.

### Modified Capabilities

None.

## Impact

The change affects the report database queries, report controller and list templates, top navigation badge markup/styles, and standalone PHP regression checkers. It adds no dependency or schema migration.
