## Purpose

Provides a safe one-time normalization of accumulated culture points when deploying a stricter expansion curve to an existing world.

## ADDED Requirements

### Requirement: Preview-first culture normalization
The system SHALL preview every affected player and the resulting balance without changing data unless an explicit apply mode is requested.

#### Scenario: Preview normalization
- **WHEN** an operator runs the normalization without apply mode
- **THEN** the system lists affected players and makes no database changes

#### Scenario: Apply normalization
- **WHEN** an operator explicitly runs the normalization in apply mode
- **THEN** the system caps each affected player's culture balance and reports the change

### Requirement: Preserve one additional village threshold
The normalization SHALL leave each regular player with no more than the slow-curve culture requirement for one village beyond their currently owned village count, and SHALL not increase balances below that requirement.

#### Scenario: Player has surplus culture
- **WHEN** a player owns three villages and has more culture than the slow requirement for four villages
- **THEN** the player's culture balance is reduced to the slow requirement for four villages

#### Scenario: Player is below the cap
- **WHEN** a player's culture balance is below the slow requirement for one additional village
- **THEN** the player's balance remains unchanged

### Requirement: Repeatable normalization
The normalization SHALL be safe to run repeatedly without reducing an already normalized balance further.

#### Scenario: Apply is repeated
- **WHEN** the operator applies normalization more than once without intervening culture gains above the cap
- **THEN** every subsequent run reports no balance changes

