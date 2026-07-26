## ADDED Requirements

### Requirement: Catapult-capable tribes can train and dispatch siege
The system SHALL recognize Roman Fire Catapults, Teuton Catapults, Gaul Trebuchets, and Natar Ballistas as catapult units and MUST NOT recognize Nature animals as catapults.

#### Scenario: Player-tribe catapult army
- **WHEN** a Roman, Teuton, or Gaul army includes its position-eight catapult
- **THEN** the attack confirmation SHALL expose catapult targeting controls

#### Scenario: Nature army contains Crocodiles
- **WHEN** a Nature army includes unit 38
- **THEN** the system SHALL treat the unit as a combat animal and MUST NOT apply catapult damage

#### Scenario: Natar Ballista movement
- **WHEN** a Natar army containing Ballistas is dispatched with malformed zero-speed unit data
- **THEN** movement-time calculation SHALL use a positive safe speed and MUST NOT divide by zero

### Requirement: Catapult target selection is server-authoritative
The system SHALL normalize target inputs and resolve every impact to an occupied valid target slot.

#### Scenario: Explicit resource target
- **WHEN** an attacker selects wood, clay, iron, or crop as the catapult target
- **THEN** the system SHALL select an occupied resource field of that type

#### Scenario: Explicit building target
- **WHEN** an attacker selects a building type present in the target village
- **THEN** the system SHALL select an occupied building slot containing that type

#### Scenario: Missing explicit target type
- **WHEN** the selected target type is not present
- **THEN** the system SHALL fall back to a random occupied target slot

#### Scenario: Random target
- **WHEN** the attacker selects random targeting
- **THEN** the system SHALL select exactly one existing occupied target slot without an invalid array index

#### Scenario: Double target authorization
- **WHEN** the rally point is below level 20
- **THEN** the system SHALL discard a submitted second target

### Requirement: Catapult damage is consistent
The live combat engine and combat simulator SHALL use the same firing and level-damage rules, including casualties, combat ratio, smithy upgrades, population morale, and stonemason durability.

#### Scenario: Complete destruction
- **WHEN** effective firing power is equal to or greater than the required power for the target
- **THEN** the target level SHALL become zero

#### Scenario: Partial destruction
- **WHEN** effective firing power is positive but below the complete-destruction threshold
- **THEN** the target SHALL lose levels according to the shared siege formula

#### Scenario: No surviving firing power
- **WHEN** no catapult firing power survives combat
- **THEN** the target level SHALL remain unchanged

#### Scenario: Double impact
- **WHEN** two targets are authorized
- **THEN** the system SHALL divide total firing power evenly and apply one impact to each resolved target

#### Scenario: Raid
- **WHEN** catapults are sent in a raid
- **THEN** they SHALL participate in combat but MUST NOT damage village targets

### Requirement: Catapult damage persists village state
The system SHALL persist damage to both building slots and resource-field slots and maintain affected derived village state.

#### Scenario: Building destroyed
- **WHEN** a building slot is reduced to level zero
- **THEN** its level and building type SHALL be cleared

#### Scenario: Resource field destroyed
- **WHEN** a resource field is reduced to level zero
- **THEN** its level SHALL become zero and its resource type SHALL remain assigned

#### Scenario: Storage building damaged
- **WHEN** a warehouse or granary loses levels
- **THEN** the corresponding capacity SHALL be recalculated with the existing minimum capacity

#### Scenario: Population reaches zero
- **WHEN** catapult damage reduces a non-capital, non-final village to zero population
- **THEN** the existing village destruction cleanup SHALL run
