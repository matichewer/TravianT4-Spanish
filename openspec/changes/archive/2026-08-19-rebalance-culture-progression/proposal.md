## Why

Culture capacity currently advances faster than players can found villages, removing the expansion constraint and making Town Hall celebrations strategically irrelevant. Existing accounts also carry enough surplus culture to bypass a stricter curve unless that surplus is normalized once during deployment.

## What Changes

- Switch the server from the normal culture threshold curve to the existing slow curve.
- Make the slow curve an installer invariant so every fresh or reset world receives the balanced progression without an operator choice.
- Add a repeatable, preview-first migration that caps each existing player's culture points at the slow-curve requirement for one village beyond their current village count, while leaving lower balances unchanged.
- Reduce recurring culture production from fields and buildings to 25% of its raw value per world-speed unit, preserving the same progression pace on x1, x3, x10 and future speeds.
- Reduce culture helmet bonuses to base values of 25/100/200 points per day and multiply their effective contribution by world speed.
- Keep celebration rewards at 500/2000 points so Town Hall investment regains strategic value.
- Make artwork grant the newly balanced daily production and limit its use to one per account in any rolling 24-hour period.
- Provide explicit production deployment commands and verification output.

## Capabilities

### New Capabilities

- `culture-balance-migration`: Balance recurring culture sources and safely normalize existing player balances.

### Modified Capabilities

- `settler-expansion`: Expansion capacity uses the slow culture threshold curve.

## Impact

- `config/config.php` and the installer template select the slow curve for all expansion eligibility and progress displays.
- A new standalone tool reads the authoritative culture table and updates eligible player accounts only when invoked with an explicit apply flag.
- Existing accounts above their one-next-village threshold lose only the excess; accounts below it are unchanged.
- Culture displays, daily credit, helmets, artwork dialogs and artwork consumption are affected.
- Artwork cooldown state requires a database schema migration for existing worlds and installer schema support for new worlds.
