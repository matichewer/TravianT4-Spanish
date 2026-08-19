# hero-revival Specification

## Purpose
Ensures every hero revival produces one immediately usable hero in the correct village without leaving a conflicting paid revival pending.
## Requirements
### Requirement: Water bucket completes a pending paid revival
When a dead hero has a paid revival in progress, the system SHALL make the water bucket complete that revival immediately in the village where it was purchased.

#### Scenario: Bucket used during paid revival
- **WHEN** a player uses an owned water bucket while the player's dead hero has a paid revival queued
- **THEN** the hero is alive at full health in the revival queue's village
- **AND** the completed revival queue entry no longer exists
- **AND** exactly one of the player's villages contains the hero unit
- **AND** the water bucket is consumed

### Requirement: Water bucket revives without a paid revival
When no paid revival is pending, the system SHALL revive the dead hero in the player's currently selected village.

#### Scenario: Bucket used without paid revival
- **WHEN** a player uses an owned water bucket while the player's hero is dead and has no paid revival queued
- **THEN** the hero is alive at full health in the currently selected village
- **AND** exactly one of the player's villages contains the hero unit
- **AND** the water bucket is consumed

### Requirement: Invalid bucket use does not mutate revival state
The system MUST NOT consume a water bucket or change hero placement when the hero is already alive, the item is unavailable, or the destination village is not owned by the player.

#### Scenario: Bucket cannot validly revive the hero
- **WHEN** a player attempts to use a water bucket without satisfying all revival preconditions
- **THEN** the hero state, hero placement, revival queue, and item remain unchanged

