## Why

Hero attributes currently contain exploitable boundary failures and inconsistent resource accounting. A level-99 hero can gain unlimited levels and points, attributes can exceed their intended cap, and pending hero production is lost when an attack settles a village.

## What Changes

- Cap hero progression at level 99 and every assignable attribute at 100 points.
- Make point assignment conditional and atomic so concurrent requests cannot create extra attribute points.
- Preserve unspent points when the Book of Wisdom refunds assigned attributes.
- Use the same hero production rules when a village is visited and when its pending resources are settled during an attack or raid.
- Correct hero strength, resource-production, and regeneration values shown in the attribute interface.
- Add regression coverage for progression boundaries, attribute allocation, point refunds, combat bonuses, equipment bonuses, and resource production.

## Capabilities

### New Capabilities

- `hero-attributes`: Defines hero progression limits, attribute allocation, combat effects, resource production, point refunds, and displayed values.

### Modified Capabilities

None.

## Impact

The change affects hero attribute rendering and actions, hero database updates, periodic hero leveling, battle-time village resource settlement, and PHP regression scripts under `tools/`. It requires no schema changes or new dependencies.
