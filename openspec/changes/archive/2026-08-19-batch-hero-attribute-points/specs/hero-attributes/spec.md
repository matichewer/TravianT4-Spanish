## MODIFIED Requirements

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
