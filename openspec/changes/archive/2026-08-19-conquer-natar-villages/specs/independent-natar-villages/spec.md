## Purpose

Makes an independent Natar village answerable to everything a player can do to it.

## ADDED Requirements

### Requirement: Building damage persists and is repaired over time
The system SHALL keep a destroyed field or building destroyed, and SHALL raise it back toward the level its age allows at no more than one level per repair interval, which SHALL be shorter than the growth interval.

#### Scenario: Catapults destroy a field
- **WHEN** a field of an independent Natar village is destroyed and the village is brought up to date moments later
- **THEN** the field is still destroyed

#### Scenario: Time passes after the damage
- **WHEN** one repair interval elapses
- **THEN** the field has gained exactly one level
- **AND** it never exceeds the level the village's age allows

### Requirement: Damage reaches the garrison
The system SHALL derive the sustainable garrison and the training rate from the village's actual fields and buildings, not from the ideal for its age.

#### Scenario: The crop fields are destroyed
- **WHEN** an independent Natar village's crop fields are destroyed
- **THEN** its sustainable garrison drops, and the troops above it starve away

#### Scenario: The barracks is destroyed
- **WHEN** the barracks is destroyed
- **THEN** the village retrains more slowly until it is rebuilt

### Requirement: A conquered village hands over no troops
The system SHALL dissolve the garrison of a conquered village rather than transferring it, and SHALL do so only once the transfer of ownership has succeeded.

#### Scenario: A player conquers a Natar village
- **WHEN** an independent Natar village is conquered
- **THEN** it belongs to the player, is no longer an NPC village, and holds no troops

#### Scenario: The conquest does not complete
- **WHEN** the ownership transfer fails
- **THEN** the defender keeps their garrison

### Requirement: Village names repair themselves
The system SHALL rename an independent Natar village whose name does not match the one derived from its identifier, and the derived name SHALL carry the village's coordinates.

#### Scenario: A village named before names carried coordinates
- **WHEN** such a village is brought up to date
- **THEN** it is renamed to its derived name, coordinates included

#### Scenario: A conquered village
- **WHEN** a village has been conquered by a player
- **THEN** its name is left alone
