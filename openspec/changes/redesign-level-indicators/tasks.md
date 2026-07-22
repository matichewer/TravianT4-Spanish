## 1. Badge state model

- [x] 1.1 Return an explicit unavailable upgrade state from `badgeUpgradeState()`.
- [x] 1.2 Expose the highest active or queued target level for a village field.

## 2. Shared badge rendering

- [x] 2.1 Convert resource-field badges to semantic outer-state, construction-state, and label classes.
- [x] 2.2 Convert regular city, rally point, and wall badges to the same semantic structure and remove hammer markup.

## 3. Shared visual treatment

- [x] 3.1 Add the reference-derived blue and yellow rings, existing green ring, white center, and orange construction center to the shared stylesheet.
- [x] 3.2 Remove obsolete hammer visibility styles and bump the stylesheet cache version.

## 4. Verification

- [x] 4.1 Run PHP syntax checks for all changed PHP/template files.
- [x] 4.2 Verify representative state calculations and rendered badge markup for normal, maximum, active, queued, and multi-queued fields.
- [x] 4.3 Check the final diff for malformed whitespace and unintended changes.

## 5. Ring contrast refinement

- [x] 5.1 Apply saturated green and yellow gradients, a shared near-black outline, and restrained outer and inset shadows without changing badge dimensions or centers.
- [x] 5.2 Bump the stylesheet cache version and visually compare resource and city badge states.
- [x] 5.3 Validate the updated OpenSpec change and check the final diff.
