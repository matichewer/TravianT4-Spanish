## 1. Server-side allocation

- [x] 1.1 Add an atomic database operation that validates and applies a complete multi-attribute distribution
- [x] 1.2 Handle token-protected POST submissions before rendering the hero inventory page

## 2. Attribute preview interface

- [x] 2.1 Replace immediate allocation links with a form and client-side multi-point preview controls
- [x] 2.2 Add Aplicar/Cancelar controls, responsive preview styling, and a static-asset cache version bump
- [x] 2.3 Move the persistent point balance and action buttons below the attributes and implement gray/green inactive and pending states
- [x] 2.4 Make the balance indicator green while prospective points remain and gray only at zero
- [x] 2.5 Suppress attribute tooltips only while the pointer is over a plus control
- [x] 2.6 Scope each attribute tooltip structurally to the attribute name only

## 3. Verification

- [x] 3.1 Extend the hero attribute regression checker for valid, invalid, capped, and stale batch allocations
- [x] 3.2 Run PHP syntax checks, the game-logic regression checkers, OpenSpec validation, and page smoke tests
- [x] 3.3 Verify the persistent controls and their state transitions in the rendered page
- [x] 3.4 Verify balance color states and rerun the targeted hero attribute checks
- [x] 3.5 Verify tooltip exclusion markup and rerun the targeted hero attribute checks
- [x] 3.6 Verify name-only tooltip triggers and rerun the targeted hero attribute checks
