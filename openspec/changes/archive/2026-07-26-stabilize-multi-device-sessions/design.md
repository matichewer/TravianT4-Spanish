## Context

The legacy game combines a PHP file-backed session with a custom token stored in `users.sessid`. Login overwrites that database field, and validation failure calls the same global logout path as an explicit logout, so an older device can erase the newer device's token. PHP currently uses its 24-minute default garbage-collection lifetime, while the remembered username cookie lasts seven days. The Admin panel uses the same PHP cookie name as the player session.

The repository is a single-node PHP 7.4 application with no framework or test runner. The implementation should remain compatible with both database adapters and the existing installer/migration workflow.

## Goals / Non-Goals

**Goals:**

- Support multiple simultaneous player sessions per account.
- Preserve other devices when one session is stale or explicitly logs out.
- Configure player and Admin PHP sessions for 30 days and align remembered-user cookies to the same duration.
- Isolate Admin authentication state from player authentication state.
- Keep the change small and compatible with the current `users.sessid` model.

**Non-Goals:**

- Persist PHP session files across container replacement; this remains a separately explainable infrastructure improvement.
- Replace MD5 passwords, rewrite legacy SQL, add login throttling, or perform the broader authentication modernization discussed during analysis.
- Add a user-facing device/session management screen.

## Decisions

### Store a bounded token list in the existing account field

`users.sessid` will continue to contain `+`-delimited session tokens, matching the existing validation code. Login will atomically append a new token instead of overwriting the field, and the field will be expanded to `varchar(2048)`. The list will retain the 20 most recent tokens to remain bounded while comfortably supporting several devices.

Alternative considered: a dedicated session table would provide per-device metadata and individual expiry, but it introduces more schema and migration complexity than required for the requested behavior. It remains the preferred direction for a future device-management feature.

### Revoke by exact token

Explicit player logout will remove only the current `username`/`sessid` pair from the account token list. Validation failure will destroy only the local PHP session and cookie and will not modify the database. Atomic SQL expressions will be used for append and removal so concurrent requests do not perform a lossy read-modify-write cycle.

### Enforce the duration in application and image configuration

`COOKIE_EXPIRE` will become 30 days. The player bootstrap will apply that value to the PHP session cookie and server garbage-collection lifetime before `session_start()`, while `docker/php.ini` will set the same values for all entry points. This keeps Docker and direct application behavior aligned.

### Give Admin a distinct cookie name

All live Admin entry points and Admin action validators will start PHP through one helper using `TRAVIANADMINSESSID`. Admin logout will clear and destroy only that named session. The cookie remains path `/` because Admin form actions also target `/GameEngine/Admin/Mods/`; isolation comes from the distinct cookie name.

### Rotate the PHP session identifier after authentication

Successful player, sitter, and Admin authentication will call `session_regenerate_id(true)` before storing authenticated state in the PHP session. This prevents a pre-authentication identifier from remaining usable after login, while preserving multi-device support because each device rotates only its own PHP session.

## Risks / Trade-offs

- [Existing installed databases still have `varchar(100)`] → Add an idempotent migration and update the installer schema; apply the migration before relying on more than three simultaneous tokens.
- [Old expired tokens have no individual timestamps] → Bound the registry to the 20 most recently created tokens; PHP still enforces the actual 30-day lifetime.
- [Container recreation still deletes `/tmp` sessions] → Document this limitation separately; persistence can be added later without changing the token semantics.
- [Admin users are logged out once when the cookie name changes] → Accept the one-time transition; player sessions are no longer disturbed afterward.
- [Session identifier rotation deletes the pre-authentication session file] → Perform it only after credentials are accepted and before authenticated values are written.

## Migration Plan

1. Expand `users.sessid` to `varchar(2048)` through `tools/migrations.sql` and update the installer schema.
2. Deploy the token append/removal behavior and 30-day configuration.
3. Rebuild the web image because `docker/php.ini` changes.
4. Existing single tokens remain valid because the list format is backward compatible.
5. Rollback can restore the old code; the first token remains readable, although shrinking the column should only be done after reviewing stored lengths.

## Open Questions

None for the requested scope.
