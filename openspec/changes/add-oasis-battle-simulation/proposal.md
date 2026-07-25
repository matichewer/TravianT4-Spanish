## Why

Players can inspect the animals in an unoccupied oasis, but must manually copy both armies into the combat simulator to estimate the result. A direct, prefilled simulation makes that decision faster and avoids transcription mistakes.

## What Changes

- Add a “Simular ataque” option to the detail view of unoccupied oases.
- Open the combat simulator preconfigured as a raid against Nature.
- Prefill the attacker with every troop currently available in the selected village.
- Include the hero by default when the living hero is currently stationed in the selected village.
- Prefill the defender with the oasis's current animal counts.
- Keep the prefilled values editable and avoid changing any real troops or oasis state.
- Reject invalid or non-oasis targets instead of exposing arbitrary unit data.

## Capabilities

### New Capabilities

- `oasis-battle-simulation`: Launch and use a prefilled, read-only battle scenario from an unoccupied oasis detail view.

### Modified Capabilities

None.

## Impact

- Oasis actions in `Templates/Map/vilview.tpl`.
- Simulator initialization in `warsim.php` and combat-form processing in `GameEngine/Battle.php`.
- Simulator regression coverage; no schema, dependency, or deployment changes.
