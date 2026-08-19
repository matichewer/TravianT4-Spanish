## Context

The attributes template currently renders each plus sign as a GET link. The bottom of the template maps that link to a database method which performs one conditional increment, then redirects. The existing database guarantee is atomic for one point, but cannot guarantee all-or-nothing behavior for a multi-attribute distribution.

## Goals / Non-Goals

**Goals:**

- Keep the provisional distribution entirely in the browser until confirmation.
- Preserve server authority over available points and the 100-point cap.
- Apply a confirmed distribution in one conditional database update.
- Keep the interface usable with the legacy JavaScript and CSS stack.

**Non-Goals:**

- Redistributing points that were already persisted.
- Changing attribute formulas, caps, level rewards, or resource selection.
- Adding a new JavaScript dependency.

## Decisions

### Submit explicit increments through POST

Each attribute will have a hidden input containing only its provisional increment. Client-side code will update those inputs and the visible values. «Aplicar» submits the four increments and the session request token through POST. This is clearer and safer than encoding state in query parameters, and it keeps persisted values distinct from the preview.

Alternative considered: issue one asynchronous request per click. That retains partial-save and concurrency problems and does not satisfy the single-confirmation behavior.

### Use one conditional SQL update

The database method will accept the four non-negative increments, reject totals of zero, and execute one UPDATE whose WHERE clause verifies the remaining point balance and every resulting cap. This retains the legacy data-access style while making the entire distribution atomic under concurrent requests.

Alternative considered: select the hero, validate in PHP, then update. A concurrent request between the read and write could overspend points.

### Preview derived values in the existing markup

The template will expose persisted point counts and the relevant display multipliers as data attributes. A small page-local script will update point counts, progress bars, strength, bonus percentages, resource values, action enabled states, and the remaining balance. Cancel resets all preview increments and derived displays from the original values. A persistent action bar below the attributes will reuse the game's standard button markup; the buttons remain gray and disabled without pending changes, then switch to green as soon as a point is previewed. The balance message uses its own state derived from the prospective remaining count: green above zero and gray at zero.

The global tooltip class and content will be attached directly to each attribute-name element instead of its complete row. The plus controls will not carry their own `title`. Structurally narrowing the trigger avoids event-order races in the legacy tooltip system and guarantees that values, bars, points, and plus controls cannot open the tooltip.

## Risks / Trade-offs

- [JavaScript disabled leaves plus controls non-functional] → Keep the confirmed POST path authoritative; this project already relies on JavaScript for major hero inventory interactions.
- [A stale page submits points after another request changed the hero] → The conditional UPDATE rejects the whole stale distribution and redirects to current values.
- [Visual calculations drift from engine formulas] → Pass tribe-specific strength and server-speed production increments from PHP rather than duplicating those rules in JavaScript.

## Migration Plan

No schema migration is required. Deploy the PHP/template and versioned CSS reference together. Rollback consists of reverting those files; persisted hero data remains compatible.
