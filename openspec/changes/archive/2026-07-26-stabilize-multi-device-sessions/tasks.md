## 1. Player token lifecycle

- [x] 1.1 Add atomic database operations that append and remove individual player session tokens in both database adapters
- [x] 1.2 Update login, validation failure, and explicit logout to preserve unrelated device tokens

## 2. Session duration and schema

- [x] 2.1 Configure player cookies, remembered username cookies, and PHP garbage collection for 30 days
- [x] 2.2 Expand the installed and migrated `users.sessid` field for a bounded multi-device token registry

## 3. Admin isolation

- [x] 3.1 Add a shared Admin session bootstrap with a distinct cookie name and use it in live Admin entry points and action validators
- [x] 3.2 Destroy only the Admin session and cookie during Admin logout

## 4. Verification

- [x] 4.1 Add a regression checker for multi-device token addition, stale-token isolation, and per-device removal
- [x] 4.2 Run PHP syntax checks, the regression checker, OpenSpec validation, and inspect the final diff

## 5. Session fixation protection

- [x] 5.1 Regenerate the PHP session identifier after successful player, sitter, and Admin authentication
- [x] 5.2 Extend the regression checker and run the focused validation suite
