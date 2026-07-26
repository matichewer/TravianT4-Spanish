## ADDED Requirements

### Requirement: Gold Club capital troops evade eligible attacks
When Gold Club troop evasion is enabled, the system SHALL remove all locally owned tribe units present in the player's capital at the arrival of a normal attack or raid and SHALL return them after the configured evasion duration.

#### Scenario: Settlers evade with local troops
- **WHEN** an eligible attack reaches a capital containing locally owned settlers
- **THEN** the settlers leave with the other locally owned tribe units and return without losses after the evasion duration

#### Scenario: Reinforcements remain to defend
- **WHEN** an eligible attack reaches a capital containing reinforcement troops
- **THEN** those reinforcement troops do not join the automatic evasion movement

#### Scenario: Ineligible movement reaches the capital
- **WHEN** a scouting or reinforcement movement reaches the capital
- **THEN** Gold Club troop evasion is not triggered

### Requirement: Ordinary recent returns block troop evasion
The system SHALL suppress Gold Club troop evasion when an ordinary troop return is scheduled within the inclusive ten seconds before the incoming attack's scheduled arrival, regardless of whether that return has already been processed.

#### Scenario: Ordinary troops returned five seconds before the attack
- **WHEN** an ordinary return reaches the capital five seconds before an eligible attack
- **THEN** the capital's locally owned troops remain and participate in the battle

#### Scenario: Ordinary troops return after the attack
- **WHEN** an ordinary return is scheduled five seconds after an eligible attack
- **THEN** that future return does not block troop evasion

#### Scenario: Previous automatic evasion returns before the attack
- **WHEN** a return created by a previous automatic evasion reaches the capital within ten seconds before an eligible attack
- **THEN** it does not block a new troop evasion

### Requirement: Hero evasion is independent
The system SHALL use the hero's existing hiding preference independently from the Gold Club capital troop setting and SHALL never include the hero in the capital troop evasion movement.

#### Scenario: Hero is configured to hide
- **WHEN** an attack reaches the hero's village while the hero hiding preference is enabled
- **THEN** the hero does not defend, regardless of whether Gold Club troop evasion occurs

#### Scenario: Hero is configured to defend
- **WHEN** an attack reaches the hero's village while the hero hiding preference is disabled
- **THEN** the hero remains eligible to defend, even when the capital's locally owned troops evade

### Requirement: Player can securely configure hero hiding
The hero inventory SHALL display the current independent hero hiding preference and SHALL update it only through an authenticated request with a valid session checker.

#### Scenario: Player changes the hero preference
- **WHEN** the authenticated player submits a valid hero hiding preference from the inventory
- **THEN** the system stores the normalized preference for that player's hero and redirects back to the inventory

#### Scenario: Request has an invalid checker
- **WHEN** a hero hiding update is submitted without the valid session checker
- **THEN** the system leaves the preference unchanged
