# settler-expansion Specification

## Purpose
TBD - created by archiving change harden-settler-expansion. Update Purpose after archive.
## Requirements
### Requirement: Tribe-specific settler costs
The system SHALL charge the configured cost of the account tribe's settler for each unit trained and SHALL keep Roman, German and Gaul settler costs distinct.

#### Scenario: Training three German settlers
- **WHEN** a German player trains three settlers with sufficient resources and expansion capacity
- **THEN** the system deducts three times the configured `u20` cost and queues exactly three settlers

### Requirement: Authoritative settler training
The server SHALL accept settler training only for a positive quantity submitted with a valid request token from a qualifying Residence or Palace, within remaining expansion capacity and available resources.

#### Scenario: Crafted request without expansion building
- **WHEN** a player submits settler training without a qualifying Residence or Palace
- **THEN** the system creates no training entry and deducts no resources

#### Scenario: Insufficient resources
- **WHEN** a player requests settlers whose configured cost exceeds any available resource
- **THEN** the system creates no training entry and no resource balance becomes negative

#### Scenario: No remaining expansion capacity
- **WHEN** the village has no remaining settler capacity
- **THEN** the system rejects additional settler training without creating negative queue quantities or crediting resources

### Requirement: Three settlers per founding
The system SHALL require and deduct exactly three tribe-appropriate settlers plus 750 units of each resource for a village-founding movement.

#### Scenario: Fewer than three settlers
- **WHEN** a player attempts to found with fewer than three settlers
- **THEN** no movement is created and no founding resources are deducted

#### Scenario: Valid founding dispatch
- **WHEN** all building, slot, culture, settler, target and resource requirements are met
- **THEN** exactly three settlers and 750 of each resource are deducted and one settlement movement is created

### Requirement: Account-wide culture capacity
The system SHALL calculate founding and conquest eligibility against the slow culture threshold curve using owned villages plus all unprocessed settlements reserved by the account, regardless of their source village.

#### Scenario: Two source villages compete for one culture slot
- **WHEN** an account has culture capacity for one additional village and already has a settlement pending from one village
- **THEN** a founding request from another village is rejected

#### Scenario: Multiple available culture slots
- **WHEN** the account's culture points satisfy the slow threshold for all owned and pending villages plus one more
- **THEN** the additional valid founding request is accepted

### Requirement: Settlement completion safety
The system SHALL revalidate culture capacity at settlement arrival, atomically claim the target, initialize all village records, and either complete consistently or preserve/refund the founding assets.

#### Scenario: Culture capacity consumed while traveling
- **WHEN** another expansion consumes the account's remaining culture capacity before settlers arrive
- **THEN** no village is created and the three settlers plus founding resources are refunded

#### Scenario: Target claimed by another account
- **WHEN** another settlement claims the target first
- **THEN** no duplicate village is created and the losing movement receives its refund

#### Scenario: Village initialization fails
- **WHEN** any required settlement record cannot be initialized after claiming the field
- **THEN** partial settlement records are removed, the field is released and the movement remains retryable

### Requirement: Consistent Spanish tribe terminology
Player-facing Spanish text SHALL refer to tribe 2 as Germano or Germanos and SHALL not use Teutón or Teutones.

#### Scenario: German tribe label is displayed
- **WHEN** a Spanish player sees a tribe name or description for tribe 2
- **THEN** the visible term is Germano or Germanos as grammatically appropriate

