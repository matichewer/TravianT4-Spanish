## ADDED Requirements

### Requirement: Intermediate culture threshold curve
The system SHALL provide one authoritative intermediate culture threshold curve whose requirement for every configured village count is two thirds of the corresponding official slow-curve requirement, rounded to the nearest 100 culture points, with the first village requiring zero points.

#### Scenario: Early intermediate thresholds
- **WHEN** the system reads the intermediate requirements for the second, third, fourth and fifth villages
- **THEN** it returns 1,300, 5,300, 13,300 and 26,000 culture points respectively

#### Scenario: All culture consumers read the same curve
- **WHEN** a requirement is displayed or checked for founding or conquest
- **THEN** the value comes from the same authoritative intermediate curve

### Requirement: Preserve progress during intermediate-curve migration
The migration SHALL translate each regular player's balance from the slow curve to the intermediate curve while preserving both the number of culture-enabled village slots and the proportional progress from the current threshold toward the next threshold.

#### Scenario: Preview intermediate migration
- **WHEN** an operator runs the migration without explicit apply mode
- **THEN** the system reports every proposed balance conversion and changes no data

#### Scenario: Apply intermediate migration
- **WHEN** an operator explicitly applies the slow-to-intermediate migration
- **THEN** all player balances are converted atomically and no player gains or loses a culture-enabled village slot

#### Scenario: Repeated application is rejected
- **WHEN** the same slow-to-intermediate migration was already applied successfully
- **THEN** the system rejects another application unless the operator explicitly invokes the existing recovery override

#### Scenario: Unrepresentable conversion is detected
- **WHEN** any converted balance would change that player's culture-enabled village slots
- **THEN** the migration writes no player balances and reports the inconsistency

## MODIFIED Requirements

### Requirement: New worlds use permanent balanced defaults
The installer SHALL always generate the intermediate culture threshold mode targeting approximately ten days per additional village in this world economy and SHALL NOT expose or accept a normal or fast culture progression override.

#### Scenario: Fresh world is installed
- **WHEN** an operator completes a new world installation
- **THEN** its generated configuration selects the intermediate culture curve

#### Scenario: World is reset and reinstalled
- **WHEN** an operator recreates a world through the standard reset and installer flow
- **THEN** the world receives the intermediate curve and all installer-backed culture schema support without an additional culture fix

### Requirement: Celebration rewards remain fixed
Town Hall celebrations SHALL continue to use their existing speed-world rules and production-based rewards without modification from the threshold rebalance.

#### Scenario: Celebration completes after threshold rebalance
- **WHEN** a valid celebration finishes after the intermediate curve is enabled
- **THEN** it grants exactly the same culture reward it would have granted before the threshold rebalance

