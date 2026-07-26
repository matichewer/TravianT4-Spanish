## Why

Settler training and village founding rely partly on UI limits, allowing crafted requests to bypass building and resource requirements. Culture-point capacity also ignores pending settlements from other villages, so an account can exceed its allowed village count.

## What Changes

- Enforce settler training requirements, positive amounts, expansion capacity, and resource affordability on the server.
- Count every pending settlement owned by the account when checking culture-point capacity.
- Keep settlement launch, arrival, refunds, and expansion slots consistent under competing requests.
- Make map and confirmation screens use the same account-wide culture calculation as the server.
- Standardize player-facing Spanish tribe terminology on Germano/Germanos instead of Teutón/Teutones.
- Add regression checks for settler costs, training authorization, three-settler founding, culture thresholds, and account-wide pending settlements.

## Capabilities

### New Capabilities

- `settler-expansion`: Secure training and founding rules, culture-point capacity reservations, settlement lifecycle behavior, and consistent Spanish terminology.

### Modified Capabilities

None.

## Impact

The change affects troop training, database helpers, village-founding requests and completion automation, map/confirmation templates, Spanish localization and regression tooling. It does not change the configured settler costs or the rule that culture points are cumulative and not spent.
