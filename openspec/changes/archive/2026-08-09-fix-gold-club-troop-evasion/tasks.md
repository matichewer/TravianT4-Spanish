## 1. Return Window

- [x] 1.1 Add ordinary return-window lookups to both database adapters
- [x] 1.2 Use the attack arrival timestamp and processed return history when deciding whether troop evasion is blocked
- [x] 1.3 Add an indexed return-window access path for existing and new worlds

## 2. Independent Hero Behavior

- [x] 2.1 Exclude the hero from every Gold Club troop evasion payload and preserve settler handling
- [x] 2.2 Replace hero hiding GET mutations with a session-protected POST preference control
- [x] 2.3 Clarify in the inventory that hero hiding is independent from capital troop evasion

## 3. Verification

- [x] 3.1 Add regression coverage for the ten-second window, prior-evasion exception, all tribe settlers, reinforcement exclusion, and hero separation
- [x] 3.2 Run PHP syntax checks, focused regression checks, OpenSpec validation, and relevant existing hero/battle checks
