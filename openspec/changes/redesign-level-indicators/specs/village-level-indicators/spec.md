## ADDED Requirements

### Requirement: Outer ring communicates upgrade availability
The system SHALL render the level badge outer ring in turquoise blue for a completed maximum level, the existing green palette when the next level is affordable, and reference-matched yellow when the next level is not affordable.

#### Scenario: Completed maximum level
- **WHEN** a displayed resource field or building is at its completed maximum level
- **THEN** its level badge outer ring is turquoise blue

#### Scenario: Affordable next level
- **WHEN** a displayed resource field or building is below maximum level and the next applicable upgrade is affordable
- **THEN** its level badge outer ring is green

#### Scenario: Unaffordable next level
- **WHEN** a displayed resource field or building is below maximum level and the next applicable upgrade is not affordable
- **THEN** its level badge outer ring is yellow

### Requirement: Center communicates construction state
The system SHALL render the level badge center in white when the field has no construction job and in orange when the field has any active or queued construction job.

#### Scenario: No construction job
- **WHEN** a displayed field has no active or queued construction job
- **THEN** its badge center is white

#### Scenario: Active construction job
- **WHEN** a displayed field has an active construction job
- **THEN** its badge center is orange and no hammer marker is displayed

#### Scenario: Queued construction job
- **WHEN** a displayed field has a queued construction job
- **THEN** its badge center is orange and no hammer marker is displayed

### Requirement: Construction badges show the target level
The system SHALL display the highest target level among a field's active and queued construction jobs inside its orange badge center.

#### Scenario: New building construction
- **WHEN** a level 1 building is active or queued on an empty construction site
- **THEN** the orange badge center displays `1`

#### Scenario: Existing building upgrade
- **WHEN** a level 5 building is active or queued to become level 6
- **THEN** the orange badge center displays `6`

#### Scenario: Multiple upgrades on one field
- **WHEN** a field has an active upgrade to level 6 and a queued upgrade to level 7
- **THEN** the orange badge center displays `7`

### Requirement: Badges remain consistent across village views
The system SHALL use the same ring palette, center palette, dimensions, typography, and construction behavior for resource fields, regular city buildings, the rally point, and the wall while preserving their existing positions.

#### Scenario: Resource and building share a state
- **WHEN** a resource field and a city building have the same upgrade and construction states
- **THEN** their badges have the same visual treatment

#### Scenario: City levels are hidden
- **WHEN** the player disables city level badges with the existing toggle
- **THEN** the complete badge is hidden regardless of construction state
