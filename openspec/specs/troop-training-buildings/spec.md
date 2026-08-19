# troop-training-buildings Specification

## Purpose
Defines reliable, secure troop ordering and queue behavior for the Barracks, Stable, and Workshop across every playable tribe.
## Requirements
### Requirement: Building-specific training eligibility
The system SHALL accept a troop order only when its unit belongs to the current player's tribe and to the family trained by the completed building slot that submitted it: infantry for a Barracks, cavalry for a Stable, and siege for a Workshop. Non-default units MUST also be researched in the order's village.

#### Scenario: Valid researched unit
- **WHEN** a player submits a positive quantity of an eligible researched unit from its completed training building
- **THEN** the system accepts the order subject to resource and free-crop limits

#### Scenario: Forged building or unit
- **WHEN** a request names a slot containing another building, an unfinished building, a unit from another family, or a unit from another tribe
- **THEN** the system rejects the order without deducting resources or changing a queue

#### Scenario: Missing research
- **WHEN** a non-default troop has not been researched in the order's village
- **THEN** the system rejects the order without deducting resources or changing a queue

### Requirement: Valid and bounded order input
The system SHALL require a valid session request token, a valid village field identifier, and a positive integer quantity that does not exceed the amount affordable with the village's current resources and free crop.

#### Scenario: Invalid request token or field
- **WHEN** an order has a missing or invalid request token or field identifier
- **THEN** no troop order is processed

#### Scenario: Non-positive or malformed quantity
- **WHEN** an order quantity is empty, malformed, zero, or negative
- **THEN** that quantity is ignored and no resources are deducted for it

#### Scenario: Quantity exceeds current capacity
- **WHEN** an order exceeds any available resource or free-crop limit
- **THEN** the system rejects that order without a partial deduction or partial queue entry

### Requirement: Failure-safe serialized ordering
The system SHALL serialize troop orders for the same village and SHALL leave resources and queues consistent if any part of ordering fails.

#### Scenario: Successful order
- **WHEN** a valid order is submitted and the queue write succeeds
- **THEN** the exact troop cost is deducted once and the exact quantity is added once

#### Scenario: Queue write failure
- **WHEN** resources were reserved but the queue cannot be written
- **THEN** the reserved resources are restored and no queue entry remains

#### Scenario: Simultaneous orders
- **WHEN** multiple orders for the same village are processed concurrently
- **THEN** each accepted order is based on state left by the preceding accepted order and the village cannot overspend or lose a queue entry

### Requirement: Stable order-time duration
Each accepted order SHALL retain the per-unit duration calculated from the building level, server speed, applicable hero equipment, Horse Drinking Trough, and active training artefact at submission time.

#### Scenario: Adjacent compatible batches
- **WHEN** adjacent orders use the same queue, unit, and per-unit duration
- **THEN** the system MAY combine them without changing their total quantity, next-completion time, or final-completion time

#### Scenario: Training modifier changes
- **WHEN** a new order for the same unit has a different per-unit duration from the last queued order
- **THEN** the system stores it as a separate batch and preserves both durations

### Requirement: Accurate queue presentation and completion
Each building SHALL present only its own queue in enqueue order, with valid markup, non-negative countdowns, the correct next unit completion, and the correct batch completion. Completed quantities SHALL be credited exactly once and exhausted batches SHALL be removed.

#### Scenario: Multiple queued batches
- **WHEN** a building has multiple queued troop batches
- **THEN** every batch appears as a distinct valid table row and the next-unit timer refers to the first pending batch

#### Scenario: Overdue processing
- **WHEN** one or more units are overdue when training completion runs
- **THEN** all elapsed whole units up to the remaining batch amount are credited exactly once and queue state advances by the corresponding elapsed duration

#### Scenario: Fully completed batch
- **WHEN** the final unit of a batch completes
- **THEN** the batch is removed and the next batch becomes the active one

