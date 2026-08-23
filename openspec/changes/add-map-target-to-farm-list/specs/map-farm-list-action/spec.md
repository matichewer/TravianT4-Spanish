## Purpose

Allows players to move directly from an eligible map target into the existing farm-list workflow without manually re-entering its coordinates.

## ADDED Requirements

### Requirement: Eligible map targets expose a farm-list action
The system SHALL display an "Agregar a lista de granjas" action for other villages and raidable oases shown in map tile details.

#### Scenario: Other village is selected
- **WHEN** a player opens map details for a village other than the current village
- **THEN** the details include an action to add that village to a farm list

#### Scenario: Raidable oasis is selected
- **WHEN** a player opens map details for an oasis that can be targeted by troops
- **THEN** the details include an action to add that oasis to a farm list

#### Scenario: Current village is selected
- **WHEN** a player opens map details for the current village
- **THEN** the farm-list action is not displayed

### Requirement: The action respects farm-list availability
The map action SHALL communicate whether the current village can use farm lists and SHALL NOT offer an actionable add flow when the required access or rally point is absent.

#### Scenario: Gold Club is inactive
- **WHEN** the player views an otherwise eligible target without active Gold Club access
- **THEN** the farm-list action is disabled and explains that Gold Club is required

#### Scenario: Rally point is absent
- **WHEN** the player views an otherwise eligible target while the current village has no rally point
- **THEN** the farm-list action is disabled and explains that a rally point is required

### Requirement: Target coordinates are pre-filled
The system SHALL carry the selected target coordinates into the farm-list slot form and allow the player to choose any farm list they own before saving.

#### Scenario: Player has existing lists
- **WHEN** the player activates the map farm-list action and owns at least one farm list
- **THEN** the slot form opens with the target coordinates pre-filled and an owned list selected

#### Scenario: Player changes the selected list
- **WHEN** the player selects a different owned list in the slot form
- **THEN** the target is saved only to that selected list after troop configuration is submitted

### Requirement: Players without lists are guided through list creation
The system SHALL route a player who owns no farm lists to list creation and SHALL preserve the map target through successful creation.

#### Scenario: No farm lists exist
- **WHEN** the player activates the map farm-list action without owning a farm list
- **THEN** the list creation form opens with a message explaining that a list must be created first

#### Scenario: First list is created
- **WHEN** the player successfully creates the first list from the map-target flow
- **THEN** the slot form opens for that new list with the original target coordinates pre-filled

### Requirement: Farm-list writes remain safe and unambiguous
The system MUST validate list ownership on the server and MUST NOT add the same target more than once to a single list.

#### Scenario: Selected list belongs to another player
- **WHEN** a crafted request attempts to add a target to a list not owned by the current player
- **THEN** no farm-list slot is created

#### Scenario: Target already exists in selected list
- **WHEN** the player submits a target already stored in the selected list
- **THEN** no second slot is created and the form displays a duplicate-target message
