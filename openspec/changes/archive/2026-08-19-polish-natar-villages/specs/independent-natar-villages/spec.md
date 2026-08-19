## Purpose

Adds the naming rules that make a map full of independent Natar villages readable.

## ADDED Requirements

### Requirement: Independent villages are distinguishable
The system SHALL give each independent Natar village a name derived from its own identifier, so that no two share a name and the name does not change when the village's state is recomputed.

#### Scenario: Several villages exist
- **WHEN** the world holds several independent Natar villages
- **THEN** each carries a different name

#### Scenario: The village is recomputed
- **WHEN** an independent village is brought up to date
- **THEN** its name does not change

#### Scenario: A name is shown on the map
- **WHEN** a village name is rendered in the map tooltip
- **THEN** the word for village is not repeated in front of a name that already contains it
