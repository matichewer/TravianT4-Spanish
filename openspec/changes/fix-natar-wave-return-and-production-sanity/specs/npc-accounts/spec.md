## Purpose

Holds NPC villages to quantities somebody decided on.

## ADDED Requirements

### Requirement: Scenery attacks do not return
The system MUST NOT return troops to a village that never had them deducted, and MUST NOT leave the attack record behind when no return movement is created.

#### Scenario: A Wonder attack wave resolves
- **WHEN** a wave launched from a static NPC village finishes its battle with survivors
- **THEN** the launching village's garrison is unchanged
- **AND** no attack record is left unreferenced

#### Scenario: A player's army returns
- **WHEN** a player's attack finishes with survivors
- **THEN** the survivors return to their village as before

### Requirement: A conquered village stops being an NPC village
The system SHALL record a conquered village as a player village in the same write that transfers ownership.

#### Scenario: A Wonder village is chiefed
- **WHEN** a player conquers a static NPC village
- **THEN** the village is recorded as a player village
- **AND** it pays troop upkeep and can starve like any other

### Requirement: Unsustainable NPC production is detected
The system SHALL fail a regression check when a static NPC village has a negative crop balance, when an NPC village's deficit exceeds what its own fields could produce at maximum level, or when a village owned by a system account is recorded as a player village. Player villages SHALL only be reported.

#### Scenario: A static garrison falls into the red
- **WHEN** a static NPC village has a negative crop balance
- **THEN** the check fails and names the village

#### Scenario: A player over-trains
- **WHEN** a player village has a negative crop balance
- **THEN** the check reports it without failing

#### Scenario: A Natar village created outside the shared helpers
- **WHEN** a village owned by a system account is recorded as a player village
- **THEN** the check fails and names the village
