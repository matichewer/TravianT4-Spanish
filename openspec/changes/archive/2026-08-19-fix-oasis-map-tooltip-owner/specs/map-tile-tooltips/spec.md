## Purpose

Ensures map tile tooltips identify occupied locations consistently with the authoritative information shown in tile details.

## ADDED Requirements

### Requirement: Occupied oasis tooltip identifies its current owner
The system SHALL display the current oasis owner's player, alliance, and tribe in an occupied-oasis map tooltip.

#### Scenario: Oasis owner differs from nearby village owner
- **WHEN** a player hovers over an occupied oasis whose owner differs from owners of other rendered tiles
- **THEN** the tooltip displays identity information belonging to the oasis owner

### Requirement: Map tiles do not share tooltip identity state
The system SHALL derive tooltip identity independently for every rendered map tile.

#### Scenario: Adjacent occupied tiles belong to different alliances
- **WHEN** occupied tiles owned by members of different alliances are rendered consecutively
- **THEN** each tooltip displays its own owner's alliance without retaining the preceding tile's alliance
