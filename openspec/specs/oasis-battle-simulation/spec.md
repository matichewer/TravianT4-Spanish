# oasis-battle-simulation Specification

## Purpose
TBD - created by archiving change add-oasis-battle-simulation. Update Purpose after archive.
## Requirements
### Requirement: Oasis detail offers battle simulation
The system SHALL show a “Simular ataque” action for an unoccupied oasis and SHALL open the combat simulator for that oasis when the player selects it.

#### Scenario: Open simulation from an unoccupied oasis
- **WHEN** a logged-in player selects “Simular ataque” in an unoccupied oasis detail view
- **THEN** the system opens the combat simulator configured for a raid against Nature

#### Scenario: Do not offer shortcut for another tile type
- **WHEN** the selected tile is not an unoccupied oasis
- **THEN** the system does not show the oasis simulation action

### Requirement: Attacking army is prefilled from the selected village
The system SHALL prefill all own non-scouting troops currently available in the selected village, mapped to the player's tribe, SHALL leave the tribe's scouting unit at zero, and SHALL always include the hero by default regardless of the real hero's health, movement, or location.

#### Scenario: Open the prefilled attacker
- **WHEN** the player opens an oasis simulation
- **THEN** the simulator shows every available non-scouting troop quantity, zero scouts, and one attacking hero

#### Scenario: Scouting units are available
- **WHEN** the selected village contains Roman Equites Legati, German Emissaries, or Gaul Pathfinders
- **THEN** the corresponding attacker quantity remains zero in the prefilled simulation

#### Scenario: Real hero is unavailable
- **WHEN** the hero is dead, away, or stationed in another village
- **THEN** the simulator still includes one attacking hero by default

### Requirement: Defending animals are prefilled from the oasis
The system SHALL prefill the Nature defender with the current quantities of all animal types stored at the selected oasis.

#### Scenario: Oasis contains animals
- **WHEN** the player opens the simulator from an oasis containing Nature units
- **THEN** each animal quantity appears in the corresponding defender field

#### Scenario: Oasis contains no animals
- **WHEN** the player opens the simulator from an oasis without Nature units
- **THEN** all Nature defender quantities are zero

### Requirement: Prefilled scenario is safe and editable
The system SHALL calculate the prefilled scenario through the existing simulator, SHALL allow the player to edit its quantities, and MUST NOT move troops, reserve troops, or mutate game state.

#### Scenario: Edit the snapshot
- **WHEN** the player changes a prefilled troop or animal quantity and simulates again
- **THEN** the result uses the edited quantities

#### Scenario: Open the snapshot
- **WHEN** the player opens a prefilled oasis simulation
- **THEN** no real troop, movement, hero, or oasis record is changed

### Requirement: Oasis target is validated
The system MUST validate the requested target as an existing unoccupied oasis before reading its units.

#### Scenario: Invalid or ineligible target
- **WHEN** a simulator URL identifies a missing tile, village, abandoned valley, or occupied oasis
- **THEN** the system displays an error and does not prefill a combat scenario

