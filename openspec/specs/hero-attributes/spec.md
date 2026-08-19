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
The system SHALL let the player preview a distribution of available points among fighting strength, attack bonus, defense bonus, and resource-production attributes, SHALL apply the complete distribution only after explicit confirmation, and SHALL limit each attribute to 100 points.

#### Scenario: Preview several points
- **WHEN** a hero has available points and the player presses an attribute's add control repeatedly
- **THEN** the interface updates the prospective attribute value and remaining available points without reloading or persisting the distribution

#### Scenario: Hover the attribute name
- **WHEN** the pointer is over an attribute's name
- **THEN** the interface shows that attribute's informative tooltip

#### Scenario: Hover outside the attribute name
- **WHEN** the pointer is over the plus control, value, progress bar, or points column
- **THEN** the interface does not show the attribute tooltip or a tooltip belonging to that control

#### Scenario: Allocation controls have no pending changes
- **WHEN** the attribute page opens, the player cancels a preview, or the hero has zero available points
- **THEN** the interface keeps the remaining-points message below the attributes and both action buttons visible in an inactive gray state while the message color continues to reflect its balance

#### Scenario: Allocation controls have pending changes
- **WHEN** the player previews at least one attribute point
- **THEN** both action buttons change to the active green state

#### Scenario: Available-point balance has points remaining
- **WHEN** the persisted or prospective available-point balance is greater than zero
- **THEN** the remaining-points message is shown in green

#### Scenario: Available-point balance is exhausted
- **WHEN** the persisted or prospective available-point balance is zero
- **THEN** the remaining-points message is shown in gray

#### Scenario: Apply a valid distribution
- **WHEN** the player confirms a preview whose total does not exceed the hero's current available points and whose attributes remain at or below 100
- **THEN** the system atomically adds every previewed point, subtracts their total from the available balance, and reloads the attribute page once

#### Scenario: Cancel a distribution
- **WHEN** the player cancels a previewed distribution
- **THEN** the interface restores the persisted attribute values and available balance without reloading or changing the hero

#### Scenario: Attempt to exceed the cap in the preview
- **WHEN** an attribute's prospective value reaches 100
- **THEN** the interface prevents additional points from being previewed for that attribute

#### Scenario: Submitted distribution is no longer valid
- **WHEN** a submitted distribution exceeds the points or attribute capacity currently available on the server
- **THEN** the system leaves every attribute and the available point balance unchanged

#### Scenario: Concurrent submissions spend the same points
- **WHEN** concurrent requests attempt to allocate more points in total than remain available
- **THEN** at most one complete valid distribution is persisted and no partial distribution is applied

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

