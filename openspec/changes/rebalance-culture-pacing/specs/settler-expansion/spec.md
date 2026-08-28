## MODIFIED Requirements

### Requirement: Account-wide culture capacity
The system SHALL calculate founding and conquest eligibility against the intermediate culture threshold curve using owned villages plus all unprocessed settlements reserved by the account, regardless of their source village.

#### Scenario: Two source villages compete for one culture slot
- **WHEN** an account has culture capacity for one additional village and already has a settlement pending from one village
- **THEN** a founding request from another village is rejected

#### Scenario: Multiple available culture slots
- **WHEN** the account's culture points satisfy the intermediate threshold for all owned and pending villages plus one more
- **THEN** the additional valid founding request is accepted

#### Scenario: Conquest uses the intermediate threshold
- **WHEN** an account attempts an otherwise valid conquest
- **THEN** its culture eligibility is decided with the same intermediate threshold used for founding and displayed progress

