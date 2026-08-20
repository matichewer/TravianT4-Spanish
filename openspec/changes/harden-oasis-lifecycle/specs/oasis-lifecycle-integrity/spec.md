## Purpose

Ensures every oasis state transition preserves correct resources, troops, loyalty timing, ownership history, and player-facing navigation.

## ADDED Requirements

### Requirement: Exact loyalty regeneration
An occupied oasis SHALL regenerate loyalty at the holding village's configured building and world-speed rate without losing fractional elapsed time between automation runs.

#### Scenario: Frequent automation sweeps
- **WHEN** multiple sweeps occur before a whole loyalty point has accumulated
- **THEN** the unused elapsed time is preserved and eventually produces the exact whole-point gain

### Requirement: Production boundary on ownership changes
The system SHALL accrue a holding village's production at its old oasis bonus before an oasis is gained, lost, or released.

#### Scenario: Player releases an oasis
- **WHEN** a village releases an oasis after an uncredited production interval
- **THEN** that interval is credited using the oasis bonus and only later time uses the reduced rate

### Requirement: Reinforcements leave freed oases
The system SHALL return every valid player reinforcement from an oasis before that oasis becomes free, regardless of the release path.

#### Scenario: Holding village disappears
- **WHEN** deletion or destruction releases all oasis holdings
- **THEN** stationed player troops receive normal timed return movements and no reinforcement remains on a free oasis

### Requirement: Stable conquest history
An occupied oasis SHALL retain the timestamp of its latest conquest independently from production, loyalty, raid, and animal clocks.

#### Scenario: Oasis produces resources after conquest
- **WHEN** the oasis production clock advances
- **THEN** the displayed conquest timestamp remains unchanged

### Requirement: Correct oasis presentation and navigation
Player-facing oasis bonuses and coordinate links SHALL match the oasis type and actual map coordinates.

#### Scenario: Mixed clay and crop oasis
- **WHEN** a type 6 oasis is shown in either profile view
- **THEN** only clay and crop icons are rendered

#### Scenario: Mansion coordinate link
- **WHEN** a player follows an owned oasis coordinate link
- **THEN** the map opens the oasis's actual X and Y position
