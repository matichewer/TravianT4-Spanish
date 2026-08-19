## Why

Weekly player and alliance rankings can diverge even when alliance membership is stable because combat points are not always attributed to the same owner and alliance. Membership changes can also create artificial alliance population growth, and the Top 10 repeats the current entry when it is already visible.

## What Changes

- Attribute every weekly combat delta to a player and derive the matching alliance from that same player row.
- Apply net raiding points to a player and that same player's current alliance through one consistent operation.
- Rebase alliance population tracking around membership changes so joining and leaving do not count as growth.
- Avoid repeating the current player or alliance below a Top 10 that already contains it.
- Add regression checkers covering combat attribution, net raiding, membership changes, and Top 10 rendering.

## Capabilities

### New Capabilities

- `ranking-consistency`: Defines consistent weekly player/alliance scoring and Top 10 presentation.

### Modified Capabilities

None.

## Impact

The change affects battle ranking updates in `GameEngine/Automation.php`, alliance membership operations in `GameEngine/Alliance.php`, database ranking helpers, ranking templates, and standalone regression checkers under `tools/`.
