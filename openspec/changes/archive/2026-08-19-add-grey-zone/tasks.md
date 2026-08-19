## 1. The zone

- [x] 1.1 Add `GameEngine/GreyZone.php` with the ring geometry and the two radii as constants
- [x] 1.2 Compose the fourteen waves and schedule them from a Natar village

## 2. Trigger and visibility

- [x] 2.1 Schedule the assault when a settlement inside the zone completes
- [x] 2.2 Mark the zone on free valleys in both map tooltips

## 3. The volcano

- [x] 3.1 Wire the official 5×4 volcano art, offset so it never covers a village
- [x] 3.2 Reserve its footprint as scenery: no oases, not settleable
- [x] 3.3 `tools/reserve_volcano.php` for worlds whose terrain is already written

## 4. Loose ends this feature exposed

- [x] 3.1 Delete the `attacks` rows of movements a razed village takes with it

## 4. Terrain for a fresh world

- [x] 4.1 Wire the map generator to the shared grey-zone definition, replacing the dead `isgrayfield()`
- [x] 4.2 Generate 15-croppers and 50% oases inside the zone, from one shared function

## 5. Regression coverage

- [x] 4.1 Ring boundaries, and the official five-inside/eight-outside Wonder split read from the installer
- [x] 4.2 Founding inside schedules fourteen waves; founding outside schedules none
- [x] 4.3 The waves arrive, destroy real buildings, report to the player, and leave the Natar garrison unchanged
- [x] 4.4 Run strict OpenSpec validation, PHP syntax checks and the full checker battery
