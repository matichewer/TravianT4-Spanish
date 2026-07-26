## 1. Siege Calculation

- [x] 1.1 Add explicit catapult-unit recognition and shared firing-power calculation to `Battle`
- [x] 1.2 Add a shared target-level outcome calculation and use it in simulator and live battle results

## 2. Dispatch and Target Selection

- [x] 2.1 Show target controls for real catapult armies and normalize submitted targets server-side
- [x] 2.2 Make troop travel-time calculation safe for zero or malformed unit speeds
- [x] 2.3 Replace unsafe random target indexing with valid occupied-slot selection

## 3. Damage Persistence

- [x] 3.1 Consolidate single and double catapult impacts into one live resolution path
- [x] 3.2 Preserve building/resource type, capacity, alliance, population, report, and village cleanup side effects

## 4. Verification

- [x] 4.1 Add regression coverage for tribe recognition, firing, target selection, and partial/full damage
- [x] 4.2 Run PHP syntax checks, combat regressions, OpenSpec validation, and inspect the final diff
