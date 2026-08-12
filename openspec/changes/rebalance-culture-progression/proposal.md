## Why

Culture capacity currently advances faster than players can found villages, removing the expansion constraint and making Town Hall celebrations strategically irrelevant. Existing accounts also carry enough surplus culture to bypass a stricter curve unless that surplus is normalized once during deployment.

## What Changes

- Switch the server from the normal culture threshold curve to the existing slow curve.
- Add a repeatable, preview-first migration that caps each existing player's culture points at the slow-curve requirement for one village beyond their current village count, while leaving lower balances unchanged.
- Preserve the current natural culture production and celebration rewards while documenting the balance impact of culture helmets and artwork for a separate decision.
- Provide explicit production deployment commands and verification output.

## Capabilities

### New Capabilities

- `culture-balance-migration`: Preview and apply the one-time normalization of existing player culture balances safely.

### Modified Capabilities

- `settler-expansion`: Expansion capacity uses the slow culture threshold curve.

## Impact

- `config/config.php` selects the slow curve for all expansion eligibility and progress displays.
- A new standalone tool reads the authoritative culture table and updates eligible player accounts only when invoked with an explicit apply flag.
- Existing accounts above their one-next-village threshold lose only the excess; accounts below it are unchanged.
- No database schema changes or static asset cache bumps are required.
