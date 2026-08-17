## 1. Waves stop feeding the capital

- [x] 1.1 Skip the return movement when the attacking village is a static NPC village, and delete the orphaned `attacks` row
- [x] 1.2 Reset a conquered village's NPC kind to player in the conquest write

## 2. Impossible rates are loud

- [x] 2.1 Add `tools/check_production_sanity.php`: static NPC villages in the red, NPC deficits above the absolute crop ceiling, and villages of system accounts recorded as player villages

## 3. Regression coverage

- [x] 3.1 Behavioural proof that a wave leaves the capital's garrison and the `attacks` table unchanged
- [x] 3.2 Assert the conquest write resets the NPC kind
- [x] 3.3 Run strict OpenSpec validation, PHP syntax checks and the full checker battery
