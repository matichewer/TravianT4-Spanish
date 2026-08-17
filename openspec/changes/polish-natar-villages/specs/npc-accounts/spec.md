## Purpose

Closes the last creation paths that bypass the shared Natar helpers.

## ADDED Requirements

### Requirement: Every Natar village is created through the shared helpers
The system SHALL create Natar villages through the shared garrison and provisioning helpers regardless of which entry point triggers it, so that owner, economy and NPC kind can never diverge between creation paths.

#### Scenario: An admin adds Wonder villages from the panel
- **WHEN** Wonder villages are created from the administration panel
- **THEN** they belong to the Natar account, carry the Natar economy and are recorded as static NPC villages

#### Scenario: The installer creates the Natar world
- **WHEN** a new world is installed
- **THEN** the capital and the Wonder villages are created the same way, and the capital carries a proper name
