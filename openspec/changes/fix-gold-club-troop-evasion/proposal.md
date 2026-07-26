## Why

Gold Club troop evasion currently applies the wrong side of Travian's ten-second return window and always moves the hero with the capital's troops. This can protect troops that should remain for combat and prevents players from independently choosing whether their hero defends or evades.

## What Changes

- Apply the ten-second restriction to ordinary troop returns that arrive immediately before an attack.
- Preserve the exception for returns created by a previous automatic evasion.
- Index movement return-window lookups for installed and new game worlds.
- Keep capital troop evasion limited to locally owned troops, including settlers, and exclude reinforcements.
- Use the existing persistent hero hiding preference independently from the Gold Club capital troop setting.
- Make the hero inventory control clearly describe the independent behavior and update it through a session-protected request.
- Add regression coverage for the timing, unit mapping, hero preference, and return behavior.

## Capabilities

### New Capabilities

- `troop-evasion`: Defines Gold Club capital troop evasion, the ten-second return restriction, settler handling, reinforcement exclusion, and the independent hero evasion preference.

### Modified Capabilities

None.

## Impact

The change affects attack and return processing in `GameEngine/Automation.php`, return lookup in the database adapters, the movement table index, the hero inventory endpoint and template, and focused regression tooling. The hero setting needs no new column because the hero table already stores the independent `hide` preference, but existing worlds require the idempotent movement-index migration.
