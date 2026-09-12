## Purpose

Allow players to relinquish unwanted artefacts while preserving them as defended objectives available for capture in the world.

## ADDED Requirements

### Requirement: Confirmed owner abandonment
The treasury SHALL offer abandonment for artefacts owned by the logged-in account. The server MUST require a POST, a valid session token and explicit confirmation; sitters MUST NOT abandon artefacts.

#### Scenario: Owner confirms
- **WHEN** the owner confirms abandonment from the artefact detail
- **THEN** the artefact leaves the account, frees its treasury and ceases contributing effects to that account

#### Scenario: Account had an upkeep effect
- **WHEN** abandonment changes the account's active artefact effects
- **THEN** elapsed resource production in all its villages is credited with the previous effects, without retroactively increasing crop consumption

#### Scenario: Invalid request
- **WHEN** a request lacks confirmation or a valid token, or comes from another owner or a sitter
- **THEN** no artefact or village is changed

### Requirement: Defended relocation preserves identity
An abandoned artefact SHALL reappear immediately in a newly created static Natar village on an unoccupied non-oasis tile, with treasury and defenders generated using the existing artefact release rules. Its identifier, type and size MUST be preserved, including construction plans. The result SHALL link to its new location. Subsequent capture SHALL use the existing capture and activation rules.

#### Scenario: Successful relocation
- **WHEN** an artefact is abandoned and a suitable free tile exists
- **THEN** exactly one defended Natar village is created and the same artefact becomes available there without altering an existing village

### Requirement: Failure and repetition preserve world state
The system MUST retain the artefact in its current location if creation fails or no suitable tile exists. Repeated or concurrent abandonment requests MUST NOT duplicate the artefact or create extra villages.

#### Scenario: No room or failed creation
- **WHEN** the relocation cannot finish
- **THEN** the player retains the artefact and its effects and no partially created village remains

#### Scenario: Replayed confirmation
- **WHEN** the successful abandonment request is submitted again
- **THEN** no additional village is created and the artefact stays at its new location
