# grey-zone Specification

## Purpose
Defines the hazardous region at the centre of the map and what founding a village there costs.
## Requirements
### Requirement: The grey zone is a configurable region around the map centre
The system SHALL define the grey zone by an inner and an outer radius from the map centre, SHALL treat an inner radius of zero as the official disc, and SHALL disable the feature entirely when the outer radius is zero.

#### Scenario: A tile inside the region
- **WHEN** a tile lies between the two radii
- **THEN** it belongs to the grey zone

#### Scenario: A tile beyond the outer radius
- **WHEN** a tile lies beyond the outer radius
- **THEN** it does not belong to the grey zone

#### Scenario: The feature is switched off
- **WHEN** the outer radius is zero
- **THEN** no tile belongs to the grey zone and nothing is ever scheduled

### Requirement: Founding in the grey zone wakes the Natars
The system SHALL schedule fourteen Natar waves against a village founded inside the grey zone, arriving after a delay scaled by the server speed, sent from a Natar village and invented rather than deducted from it.

#### Scenario: A village is founded inside
- **WHEN** a settlement completes on a tile inside the grey zone
- **THEN** fourteen waves are scheduled against it
- **AND** the Natar village they come from loses no troops

#### Scenario: A village is founded outside
- **WHEN** a settlement completes outside the grey zone
- **THEN** nothing is scheduled

#### Scenario: A settlement that fails
- **WHEN** a settlement does not complete
- **THEN** nothing is scheduled

#### Scenario: A village that already existed
- **WHEN** a village inside the grey zone was founded before the zone existed
- **THEN** it is never assaulted, and nothing about it changes

### Requirement: The assault destroys buildings and is reported
The system SHALL let the waves damage the village's buildings and fields, and SHALL report each wave to the village's owner.

#### Scenario: The waves arrive
- **WHEN** the scheduled waves resolve against the village
- **THEN** its buildings and fields lose levels
- **AND** its owner receives a report for each wave

### Requirement: The zone is visible before it is entered
The system SHALL mark a free valley inside the grey zone when its tile is inspected on the map, so founding there is an informed decision.

#### Scenario: Inspecting a free valley inside the zone
- **WHEN** a player inspects a free valley inside the grey zone
- **THEN** the tile is marked as grey zone with a warning

#### Scenario: Inspecting a free valley outside
- **WHEN** a player inspects a free valley outside the zone
- **THEN** no warning is shown

### Requirement: A newly installed world gets the grey zone terrain
The system SHALL generate the official grey-zone terrain — croppers and 50% oases — inside the zone when a world is installed, using the same definition of the zone that the rest of the engine uses.

#### Scenario: Installing a new world
- **WHEN** the map is generated
- **THEN** tiles inside the grey zone are drawn from a distribution rich in croppers and 50% oases
- **AND** tiles outside it keep the ordinary distribution

#### Scenario: An oasis inside the zone
- **WHEN** an oasis is generated inside the grey zone
- **THEN** it is one of the 50% kinds

#### Scenario: A world that already exists
- **WHEN** a world was installed before this change
- **THEN** its terrain is left exactly as it is

