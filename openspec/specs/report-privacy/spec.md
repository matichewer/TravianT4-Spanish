# report-privacy Specification

## Purpose

Defines a single, centralized authorization policy for reading and mutating battle, trade, adventure, and reinforcement reports, so no route can leak report contents or metadata to an unauthorized player.

## Requirements

### Requirement: Centralized report authorization
The system SHALL authorize every report read using the same policy: the report owner SHALL have access, and a current member of the report alliance SHALL have access only when the report type is military.

#### Scenario: Owner reads a private report
- **WHEN** a player requests their own trade, adventure, reinforcement, combat, or espionage report
- **THEN** the system returns the report

#### Scenario: Alliance member reads a military report
- **WHEN** a player belongs to the alliance stored on a combat, espionage, or attacked-reinforcement report
- **THEN** the system returns the report

#### Scenario: Alliance member reads a non-military report
- **WHEN** a player requests another member's trade, adventure, or reinforcement-arrival report
- **THEN** the system denies access

#### Scenario: Unrelated player guesses a report identifier
- **WHEN** a player requests a report owned by another player without qualifying alliance access
- **THEN** the system denies access without revealing report metadata

### Requirement: Report references do not grant access
The system MUST NOT grant report access merely because a report identifier appears in a received message or other BBCode content.

#### Scenario: Private report sent to an unrelated player
- **WHEN** a message contains a report reference and the recipient is not independently authorized
- **THEN** the recipient cannot see the report title, link, or contents

#### Scenario: Authorized reader receives a report reference
- **WHEN** BBCode contains a report reference and the current reader is independently authorized
- **THEN** the system renders the report title and link

### Requirement: Secondary report routes enforce authorization
The system SHALL apply report authorization to direct report pages, BBCode previews, and attack repetition inputs.

#### Scenario: Unauthorized repeat-attack request
- **WHEN** a player supplies another player's private report identifier to the attack page
- **THEN** the system does not load that report's payload

#### Scenario: Authorized repeat-attack request
- **WHEN** a player supplies a report identifier they can read
- **THEN** the system may use its payload to prefill the attack form

### Requirement: Report mutations are owner-scoped
The system SHALL restrict read-state, archive-state, and deletion changes to reports owned by the authenticated player.

#### Scenario: Forged bulk action
- **WHEN** a player submits another player's report identifier in a bulk delete, archive, unarchive, read, or unread action
- **THEN** the other player's report remains unchanged

### Requirement: Total attacker loss reveals no defense intelligence
The system MUST omit defensive troop composition, reinforcement tribes, casualties, resources, buildings, and other reconnaissance results from the attacker's report when no attacking troop survives.

#### Scenario: All attacking troops die
- **WHEN** a normal attack ends with zero surviving attacking troops
- **THEN** the attacker's report shows no defensive tables or defensive details
- **AND** the defender's own report remains complete

#### Scenario: All spies die
- **WHEN** an espionage mission ends with zero surviving spies
- **THEN** the attacker's report shows no defensive troops, resources, buildings, or reinforcement information
- **AND** the defender's own report remains complete
