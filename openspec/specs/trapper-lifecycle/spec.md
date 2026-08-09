# trapper-lifecycle Specification

## Purpose

Defines the full Gaul trapper lifecycle: building trap capacity, atomically capturing a proportional share of an attacking force, keeping captured troops fed, and safely releasing, disbanding, or battle-liberating prisoners.

## Requirements

### Requirement: Gaul trap construction and capacity
The system SHALL allow only Gaul villages with a completed trapper to train traps, and SHALL prevent built plus queued traps from exceeding the sum of completed trapper capacities.

#### Scenario: Valid trap training
- **WHEN** a Gaul submits trap training from a completed trapper with sufficient resources and capacity
- **THEN** the traps enter the training queue and become available after completion

#### Scenario: Forged training field
- **WHEN** a trap training request names a field that is not a completed trapper
- **THEN** the system rejects the request without spending resources or adding training

### Requirement: Atomic proportional capture
The system MUST reserve available traps and persist the matching prisoner troops as one consistent operation, and SHALL distribute a partial capture proportionally across the attacking troop composition.

#### Scenario: Partial mixed capture
- **WHEN** a mixed attacking force is larger than the number of available traps
- **THEN** the captured total equals the reserved traps, no slot exceeds its sent troops, and allocation follows the force proportions rather than fixed slot order

#### Scenario: Concurrent arrivals
- **WHEN** multiple attacks reach the same trap village concurrently
- **THEN** their combined captured troops and occupied-trap count never exceed built traps or completed trapper capacity

#### Scenario: Capture persistence failure
- **WHEN** the prisoner group cannot be persisted
- **THEN** no traps remain newly occupied for that failed capture

### Requirement: Trapped troop upkeep
Captured troops MUST continue to consume crop from the village that sent them for as long as their prisoner row exists.

#### Scenario: Captured army upkeep
- **WHEN** a village has troops stored in one or more prisoner groups
- **THEN** those troops are included in that village's total units and crop upkeep

### Requirement: Safe manual prisoner actions
The trap owner SHALL be able to release a prisoner group intact and recover its occupied traps, while the troop owner SHALL be able to disband the group; both actions MUST be authorized, replay-safe, and failure-safe.

#### Scenario: Trap owner releases prisoners
- **WHEN** the current trap-village owner submits a valid release with the correct checker token
- **THEN** one complete return movement is queued, the prisoner row is removed once, and the traps become available

#### Scenario: Troop owner disbands prisoners
- **WHEN** the current origin-village owner submits a valid disband with the correct checker token
- **THEN** the prisoner row is removed, its traps become available, no return is queued, and any trapped hero is marked dead

#### Scenario: Unauthorized or replayed action
- **WHEN** another player, an invalid token, or a repeated request attempts to manage the same prisoner group
- **THEN** no prisoner, trap, hero, attack, or movement state changes

#### Scenario: Return creation fails
- **WHEN** the database cannot create the attack payload or movement for a manual release
- **THEN** the prisoner row and occupied traps remain unchanged

### Requirement: Battle-driven liberation
A successful normal attack SHALL free the attacker's own and friendly-alliance prisoners, kill exactly floor one quarter of each released aggregate, destroy the traps that held successfully released prisoners, and queue separate returns for friendly troops.

#### Scenario: Own and allied liberation
- **WHEN** a normal attack survives and the trap village holds own and friendly prisoner groups
- **THEN** own survivors join the attacking return, allied survivors receive separate returns, released rows are removed, and their used traps are destroyed

#### Scenario: Allied return fails
- **WHEN** a friendly prisoner's return cannot be queued
- **THEN** that prisoner row and its used traps remain unchanged while other independently successful releases may complete

#### Scenario: Raid does not liberate
- **WHEN** a raid survives a trap village
- **THEN** it does not free existing prisoners

### Requirement: Trapper reporting
Battle reports SHALL display captured troop counts and any prisoner-liberation summary from their correct report fields without undefined-index warnings.

#### Scenario: Liberation report
- **WHEN** a normal attack frees any own or allied prisoners
- **THEN** both attacker and defender report views display the released and killed totals
