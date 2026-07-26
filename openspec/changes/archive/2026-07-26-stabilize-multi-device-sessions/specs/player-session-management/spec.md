## ADDED Requirements

### Requirement: Multiple player devices remain authenticated
The system SHALL allow an account to have multiple simultaneous valid player session tokens without a newer login invalidating older devices.

#### Scenario: Second device logs in
- **WHEN** an account with a valid player session logs in from another device
- **THEN** both the existing token and the new token remain valid

### Requirement: Stale sessions cannot revoke valid devices
The system MUST clear only local PHP state when a presented player token is absent from the account token registry.

#### Scenario: Rejected old token makes another request
- **WHEN** a stale device presents a token that is not registered for the account
- **THEN** the stale device is redirected to login and all other registered tokens remain unchanged

### Requirement: Logout revokes only the current player device
The system SHALL remove only the token presented by the player session that explicitly logs out.

#### Scenario: One of multiple devices logs out
- **WHEN** one authenticated device requests player logout
- **THEN** that device becomes unauthenticated and the account's other registered tokens remain valid

### Requirement: Authentication state lasts 30 days
The system SHALL configure the PHP session cookie, server-side session garbage-collection lifetime, and remembered username cookie for 2,592,000 seconds.

#### Scenario: Browser remains open or is reopened within the duration
- **WHEN** a valid session is revisited before 30 days have elapsed and its server-side session data still exists
- **THEN** the browser remains authenticated without submitting credentials again

### Requirement: Admin and player sessions are isolated
The system SHALL use a distinct PHP session cookie name for the live Admin panel and its action endpoints.

#### Scenario: Admin logs out while player is authenticated
- **WHEN** a browser with both player and Admin authentication logs out of the Admin panel
- **THEN** only the Admin session is destroyed and the player session remains valid

#### Scenario: Player logs out while Admin is authenticated
- **WHEN** a browser with both player and Admin authentication logs out of the game
- **THEN** only the player session is destroyed and the Admin session remains valid

### Requirement: Successful authentication rotates the PHP session identifier
The system MUST regenerate the active PHP session identifier after credentials are accepted and before authenticated state is stored.

#### Scenario: Player or sitter logs in successfully
- **WHEN** valid player or sitter credentials are accepted
- **THEN** the pre-authentication PHP session identifier is invalidated and the authenticated session uses a new identifier

#### Scenario: Admin logs in successfully
- **WHEN** valid Admin credentials are accepted
- **THEN** the pre-authentication Admin PHP session identifier is invalidated and the authenticated Admin session uses a new identifier
