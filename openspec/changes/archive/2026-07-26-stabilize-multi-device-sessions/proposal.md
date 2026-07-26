## Why

Player sessions currently become eligible for garbage collection after 24 minutes of inactivity, and the account-level token allows an invalid or older client to revoke a newer login. This causes frequent, seemingly random logouts and prevents reliable use from multiple devices.

## What Changes

- Keep authenticated player sessions valid for 30 days in both the browser cookie and server-side storage.
- Allow multiple browsers and devices to hold independent valid sessions for the same account.
- Revoke only the current device token during explicit logout; an invalid or stale client must not revoke other sessions.
- Preserve existing valid sessions when a new player or sitter login is created.
- Give the Admin panel an independent PHP session cookie and logout lifecycle so Admin and game logins cannot invalidate one another.
- Regenerate the PHP session identifier after every successful player, sitter, or Admin login.
- Add focused regression coverage for multi-device login, stale-session rejection, per-device logout, and session configuration.

## Capabilities

### New Capabilities

- `player-session-management`: Defines multi-device player authentication, 30-day persistence, isolated token revocation, login-time session identifier rotation, and Admin session separation.

### Modified Capabilities

None.

## Impact

- Player authentication and logout logic in `GameEngine/Session.php`, `GameEngine/Account.php`, and the MySQLi/MySQL database adapters.
- PHP session configuration in `docker/php.ini`.
- Admin bootstrap and logout behavior under `Admin/` and `GameEngine/Admin/`.
- A lightweight regression checker under `tools/`; no new runtime dependency is required.
