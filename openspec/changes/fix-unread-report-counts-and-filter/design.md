## Context

Report list filtering is split between `berichte.php`, `Message::noticeType()`, and duplicated list templates. The top navigation derives several colored badges from one grouped SQL query. The navigation artwork has a fixed width, so a ninth full-width tab does not fit safely.

## Goals / Non-Goals

**Goals:**

- Keep unread ownership and visibility rules in database queries rather than in presentation-only counting.
- Reuse the existing all-reports list renderer for the unread view.
- Make badge colors deterministic without depending on browser support for CSS filters over the original green sprite.
- Fit the unread control in the existing report navigation width.

**Non-Goals:**

- Redesigning all report list templates or changing report type identifiers.
- Including archived reports in unread totals or the unread tab.
- Changing when opening a report marks it read.

## Decisions

- Add `adventure` as a separate unread category for `ntype = 9`; keep other entries from the Varios tab under `misc`. This matches the requested semantic green without changing the Varios list itself.
- Apply `del = 0` and `archive = 0` to unread counts so badges describe reports reachable from the normal/unread lists. The alternative of counting archived unread reports leaves an unexplained badge for non-Plus users and disagrees with the unread tab.
- Represent the unread view as filter `t=8` and allow the all-reports template to accept an additional SQL predicate and pagination base. This avoids another large copied template.
- Make shared category list templates derive detail links from the active controller filter instead of hardcoding their original category. This lets the Rutas wrapper retain `t=7` and makes detail-neighbor queries use the same route predicate.
- Keep "No leídos" in the single report navigation row and use a report-scoped flex layout whose basis follows each label width, so longer labels receive more room while all tabs collectively cover the complete bar. Recover the 40 pixels formerly supplied by the removed side padding in the explicit navigation width; this also adapts automatically when Archivo is hidden for a non-Plus account.
- Use explicit CSS background colors with the sprite used only for shape where possible, instead of hue/brightness filters whose result varies with the source image and cached styles.
- Render Rutas with a transparent container and a single `rgba(80,80,80,.25)` layer on its background halves, using `!important` to override inherited sprite/background rules. Avoid coloring both levels because their alpha compounds into a dark badge. Bump both the outer stylesheet query and the imported compact stylesheet version whenever this rule changes, because production caches both layers independently.

## Risks / Trade-offs

- [Existing archived unread rows stop contributing to the header count] → This is intentional visibility consistency; they reappear if unarchived and still unread.
- [Legacy report templates build pagination links manually] → Cover preservation of `t=8` and per-page selection with source and runtime regression checks.
- [Route reports reuse the trade template] → Assert that the shared template emits the controller's active filter rather than `t=2`.
- [Theme CSS is a compact shared file] → Scope all new rules below `#navigation` or `#content.reports` to avoid affecting other menus.

## Migration Plan

No schema or data migration is required. Deployment is a live PHP/CSS update. Rollback consists of reverting the controller, query, template, and CSS changes.
