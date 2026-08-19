# catapult-resolution Specification

## Purpose
Ensure catapult attacks resolve predictably, respect rally-point targeting rules, and leave every affected village subsystem consistent.
## Requirements
### Requirement: Deterministic wave resolution
The system SHALL resolve attacks sharing an arrival second in movement creation order and SHALL NOT resolve a stale movement after its target village or movement was removed.

#### Scenario: Same-second wave
- **WHEN** multiple attacks arrive in the same second
- **THEN** they resolve by ascending movement identifier

#### Scenario: Earlier attack destroys village
- **WHEN** an earlier attack removes a village and its pending movements
- **THEN** later stale rows from the processing snapshot cause no combat or report

### Requirement: Authorized catapult targets
The system SHALL accept only targets unlocked by the sending village's rally point, SHALL reserve walls for rams, and SHALL redirect a missing selected target to another occupied non-wall slot.

#### Scenario: Forged target
- **WHEN** a player submits a target not unlocked by the rally point
- **THEN** the attack stores a random target instead

#### Scenario: Missing selected building
- **WHEN** the selected building does not exist at impact time
- **THEN** the impact selects another occupied non-wall slot

### Requirement: Consistent impact side effects
The system SHALL accrue resources at the old production rate before production-changing damage and SHALL cancel pending construction for a damaged slot.

#### Scenario: Resource field damaged
- **WHEN** catapults lower a resource field
- **THEN** resources through the arrival time are accrued before its level changes

#### Scenario: Queued upgrade target damaged
- **WHEN** catapults lower a slot with pending construction
- **THEN** pending construction for that slot is removed without later restoring an invalid level

