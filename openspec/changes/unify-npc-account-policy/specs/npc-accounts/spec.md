## Purpose

Defines the single boundary between system-owned accounts and player accounts, so that every rule that depends on "this is not a player" reads the same answer from one place instead of rediscovering it inline.

## ADDED Requirements

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
The system MUST NOT apply troop upkeep or starvation to a village owned by a system account, and SHALL decide that from the shared boundary.

#### Scenario: Production of a system-owned village
- **WHEN** production is credited to a village owned by a system account
- **THEN** its garrison consumes no crop

#### Scenario: Starvation sweep reaches a system-owned village
- **WHEN** the starvation sweep encounters a village owned by a system account
- **THEN** no troop is killed
- **AND** any accumulated crop deficit is cleared instead of carried forward

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
The system SHALL fail a regression check when the account boundary is expressed outside the shared module, while allowing unrelated comparisons that merely resemble it.

#### Scenario: A new inline owner comparison is introduced
- **WHEN** engine or page code compares an account or village owner against the system-account range directly
- **THEN** the regression check fails and names the file

#### Scenario: Resource field types are compared
- **WHEN** code compares a resource field or building type against its own range
- **THEN** the regression check does not flag it
