## Context

Resource fields and city buildings use the same legacy outer-circle rule but render their inner circles independently with duplicated inline styles. Upgrade affordability is currently encoded by the inner fill, while active and queued jobs add a hammer badge. The city view also has a persisted show/hide toggle for all level badges.

The implementation must stay compatible with PHP 7.4, the existing procedural template flow, absolute badge positioning, and the legacy graphic-pack CSS loaded before `img/travian_basics.css`.

## Goals / Non-Goals

**Goals:**

- Give the outer ring and inner fill independent meanings.
- Render active and queued jobs consistently, including their final queued target level.
- Use one semantic class vocabulary and one shared CSS implementation in both village views.
- Preserve layout, click areas, and the city level toggle.

**Non-Goals:**

- Changing affordability, queueing, building, or maximum-level game rules.
- Adding a level badge for the World Wonder stored in field 99.
- Repositioning badges or redesigning the village maps.
- Changing the white center to the warmer off-white visible in the reference screenshot.

## Decisions

### Separate ring and center state

`badgeUpgradeState()` will return one of `maxLevel`, `canUpgrade`, or `cannotUpgrade`. Templates will map that state to a class on the outer badge. Construction will add an independent class that changes only the child label's background to Travian orange (`#F88C1F`). This allows a queued job to remain orange while its ring still communicates upgrade availability.

Using explicit state classes is preferred over calculating colors in each template because it eliminates duplicated color decisions and makes future palette changes CSS-only.

### Represent every queued job by the final target level

The building engine will expose the highest queued target level for a field. When at least one active or queued job exists, the badge center will be orange and display that target instead of the currently completed level. Selecting the highest target makes a single badge meaningful when the same field has both an active upgrade and a later queued upgrade.

This is preferred over retaining the active/queued distinction because the hammer is being removed and both job states now deliberately share one visual treatment.

### Preserve the legacy outer geometry

The existing absolute wrapper positions and 26px content box plus 1px border will remain unchanged. Shared CSS in `img/travian_basics.css` will override the ring palette and fully define the 18px inner label, including resetting the legacy city selector's inherited margin and background.

### Reference-derived palette

The maximum ring will use a turquoise gradient based on the screenshot's `#1E92A1` / `#2C8C94` range with a `#336E71` edge. The unavailable ring will use a lemon-yellow gradient based on `#EEE617` / `#EEE42B` with a `#65560F` edge. The existing green ring palette remains unchanged.

## Risks / Trade-offs

- **The screenshot is compressed and anti-aliased, so no single source color can be recovered exactly.** → Use representative sampled colors and a subtle gradient matching the existing badge treatment.
- **Legacy city CSS targets every descendant `div` under `#levels`.** → Use a more specific child selector to reset all inner-label geometry and presentation.
- **A field can have multiple jobs but only one badge.** → Show the highest queued target level, which represents the field's eventual state.
- **Removing the hammer removes the only construction marker visible while city levels are hidden.** → Honor the toggle consistently: hidden levels hide the entire badge, including construction state.

## Migration Plan

Deploy the PHP template, engine, and CSS changes together and bump the stylesheet query version. Rollback consists of reverting those files; no data migration is required.

## Open Questions

None.
