# hero-resource-display Specification

## Purpose

Defines how the hero attribute interface presents resource-production bonuses so players see the same hourly rates and stacking behavior the production engine actually applies.

## Requirements

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

### Requirement: Displayed hero rates are added directly
The system SHALL add the displayed hero rate as a fixed hourly amount to the corresponding village resource production and SHALL NOT interpret it as a percentage or apply an additional multiplier.

#### Scenario: Focused production adds 120 resources per hour
- **WHEN** the hero profile displays `+120/h` for wood and the village produces 300 wood per hour without the hero
- **THEN** the village produces 420 wood per hour

#### Scenario: All-resource production adds 36 resources per hour
- **WHEN** the hero profile displays `+36/h` for every resource and the village produces 300 of each resource per hour without the hero
- **THEN** the village produces 336 of each resource per hour
