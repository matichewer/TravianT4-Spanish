# npc-accounts Specification

## Purpose
Holds NPC villages to quantities somebody decided on.
## Requirements
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

### Requirement: One named boundary between system and player accounts
The system SHALL expose the account boundary from a single shared module, as both a predicate for application code and a SQL fragment for queries, and SHALL name each system account rather than relying on a bare numeric literal.

#### Scenario: Engine code classifies an account
- **WHEN** application code needs to know whether an account is system-owned
- **THEN** it asks the shared module rather than comparing the identifier itself

#### Scenario: A query filters villages by ownership
- **WHEN** a query needs only player-owned or only system-owned villages
- **THEN** it takes the filter from the shared module rather than writing the comparison inline

#### Scenario: The four installed system accounts
- **WHEN** the boundary is asked about `Support`, `Natars`, `Nature` or `Multihunter`
- **THEN** each is reported as a system account
- **AND** any account created by player registration is reported as a player account

### Requirement: The Natar account is resolved once
The system SHALL resolve the Natar account through one shared lookup that prefers the account's username and falls back to its installed identifier, and consumers SHALL NOT join the users table by username to find it.

#### Scenario: Wonder attack waves choose their source village
- **WHEN** the engine picks the village that launches a wave against a player's Wonder
- **THEN** it filters villages by the resolved Natar account

#### Scenario: A world whose Natar account is not at the installed identifier
- **WHEN** the Natar account exists under a different identifier
- **THEN** the shared lookup still resolves it from its username

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

### Requirement: Player-facing counts exclude every system account
The system SHALL exclude all system accounts from counts of connected and active players, using the shared boundary rather than a tribe filter.

#### Scenario: Support has a recent timestamp
- **WHEN** the connected-player count is rendered and a system account has a recent timestamp
- **THEN** that account is not counted

#### Scenario: A player is connected
- **WHEN** a player account has a recent timestamp
- **THEN** it is counted

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

### Requirement: Every Natar village is created through the shared helpers
The system SHALL create Natar villages through the shared garrison and provisioning helpers regardless of which entry point triggers it, so that owner, economy and NPC kind can never diverge between creation paths.

#### Scenario: An admin adds Wonder villages from the panel
- **WHEN** Wonder villages are created from the administration panel
- **THEN** they belong to the Natar account, carry the Natar economy and are recorded as static NPC villages

#### Scenario: The installer creates the Natar world
- **WHEN** a new world is installed
- **THEN** the capital and the Wonder villages are created the same way, and the capital carries a proper name

