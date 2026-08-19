## Purpose

Extends the system/player account boundary with the per-village dimension that a living NPC village makes necessary.

## MODIFIED Requirements

### Requirement: System-owned villages are exempt from player economy rules
The system MUST NOT apply troop upkeep or starvation to a village recorded as a **static** NPC village, and SHALL decide that from the village's recorded kind rather than from its owner alone, because one system account owns villages of more than one kind.

#### Scenario: Production of a static NPC village
- **WHEN** production is credited to a static NPC village
- **THEN** its garrison consumes no crop

#### Scenario: Starvation sweep reaches a static NPC village
- **WHEN** the starvation sweep encounters a static NPC village
- **THEN** no troop is killed
- **AND** any accumulated crop deficit is cleared instead of carried forward

#### Scenario: Starvation sweep reaches a living NPC village
- **WHEN** the starvation sweep encounters a living NPC village with an empty granary and a negative balance
- **THEN** troops die of hunger, as they would in a player village

#### Scenario: Starvation sweep reaches a player village
- **WHEN** the starvation sweep encounters a player-owned village with an empty granary and a negative balance
- **THEN** troops still die of hunger

### Requirement: New ad-hoc spellings of the boundary fail
The system SHALL fail a regression check when the account boundary is expressed outside the shared module, including the ranking filter's tribe-and-access form, while allowing unrelated comparisons that merely resemble it.

#### Scenario: A new inline owner comparison is introduced
- **WHEN** engine or page code compares an account or village owner against the system-account range directly
- **THEN** the regression check fails and names the file

#### Scenario: A ranking query filters by tribe and access
- **WHEN** a ranking query excludes system accounts by comparing tribe and access instead of using the shared boundary
- **THEN** the regression check fails and names the file

#### Scenario: Resource field types are compared
- **WHEN** code compares a resource field or building type against its own range
- **THEN** the regression check does not flag it
