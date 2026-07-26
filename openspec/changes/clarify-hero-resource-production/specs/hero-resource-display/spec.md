## ADDED Requirements

### Requirement: Hero resource bonuses display their unit
The hero attribute interface SHALL render every all-resource and focused-resource bonus as a positive rate per hour.

#### Scenario: Player compares resource distributions
- **WHEN** the hero resource distribution options are displayed
- **THEN** every amount includes a plus sign and an hourly unit

#### Scenario: Player reads the current resource tooltip
- **WHEN** the player views the resource-production attribute tooltip
- **THEN** the selected bonus is identified as an hourly rate

### Requirement: Resource distribution behavior is explained
The hero attribute interface SHALL explain that resource bonuses accrue continuously from the time a distribution is selected, do not grant resources immediately, and replace rather than stack with the previous distribution.

#### Scenario: Player focuses production on one resource
- **WHEN** the player reads the distribution control
- **THEN** the interface explains that the focused rate replaces the all-resource bonus for that resource
