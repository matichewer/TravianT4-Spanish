# hero-attributes Specification

## Purpose

Defines how the hero gains and spends attribute points, how those points translate into combat and resource-production formulas, and how the interface must display values that match the engine exactly.

## Requirements

### Requirement: Hero progression has a finite boundary
The system SHALL derive the maximum hero level from the configured experience table and SHALL NOT advance a hero beyond that level or grant points beyond it.

#### Scenario: Hero reaches the final configured level
- **WHEN** a hero has enough experience for the last level in the experience table
- **THEN** the system advances the hero to that level and grants exactly four points per newly earned level

#### Scenario: Hero is already at the final configured level
- **WHEN** periodic hero processing evaluates a hero at the final level
- **THEN** the hero level and available points remain unchanged

### Requirement: Assignable attributes are bounded
The system SHALL limit fighting strength, attack bonus, defense bonus, and resource-production attributes to 100 points each.

#### Scenario: Spend a point below the cap
- **WHEN** a hero has an available point and the selected attribute is below 100
- **THEN** the system atomically decrements available points by one and increments that attribute by one

#### Scenario: Attempt to exceed the cap
- **WHEN** the selected attribute is already 100
- **THEN** the system leaves both the attribute and available points unchanged

#### Scenario: Concurrent requests spend the final point
- **WHEN** multiple requests attempt to spend the same final available point
- **THEN** exactly one request increments an attribute

### Requirement: Hero combat attributes use consistent formulas
The system SHALL calculate hero fighting strength as 100 plus the tribe-specific value of allocated strength points plus equipment power, and SHALL apply attack and defense bonus points at 0.2 percent per point up to 100 points.

#### Scenario: Roman hero attacks with a bonus
- **WHEN** a Roman hero with 10 strength points, 500 equipment power, and 50 attack-bonus points attacks
- **THEN** the base hero strength is 1600 and the participating army receives a 10 percent attack multiplier

#### Scenario: Hero defends with a bonus
- **WHEN** a defending hero has 50 defense-bonus points
- **THEN** that hero's eligible defending force receives a 10 percent defense multiplier

### Requirement: Hero resource production is consistent
The system SHALL grant resource production only while the hero is alive and stationed in the producing village, using 3 resources per attribute point for each resource in all-resource mode or 10 resources per attribute point for the selected focused resource, multiplied by server speed.

#### Scenario: Normal village production
- **WHEN** an alive stationed hero has four resource points and all-resource mode is selected on a speed-one server
- **THEN** the village receives 12 additional units per hour of wood, clay, iron, and crop

#### Scenario: Production is settled during a raid
- **WHEN** pending production for the same village is settled immediately before raid bounty is calculated
- **THEN** the system credits the same hero resource bonus as a normal village request

#### Scenario: Hero is absent or dead
- **WHEN** the hero is dead or stationed in another village
- **THEN** the village receives no hero resource bonus

### Requirement: Attribute reset preserves all points
The system SHALL return every allocated attribute point to the hero's existing unspent point balance and SHALL restore all-resource production as the selected resource mode.

#### Scenario: Reset with unspent points
- **WHEN** a hero with 10 unspent points and 30 allocated points uses a Book of Wisdom
- **THEN** the hero has 40 unspent points, zero allocated attribute points, and all-resource mode selected

### Requirement: Displayed hero values match engine values
The hero attribute interface SHALL display fighting strength, resource rates, army bonus percentages, and daily regeneration using the same inputs and speed factors as their engine calculations.

#### Scenario: Hero has no equipment power
- **WHEN** the attribute page renders a hero without power-granting equipment
- **THEN** the fighting-strength tooltip still includes the base 100 and the tribe-specific strength contribution

#### Scenario: Focused production is displayed
- **WHEN** the hero has four resource points and a focused resource is selected on a speed-one server
- **THEN** the interface displays 40 units per hour for that resource

#### Scenario: Regeneration is displayed
- **WHEN** the hero regenerates at 10 percent per day on the configured server
- **THEN** the interface displays 10 percent per day
