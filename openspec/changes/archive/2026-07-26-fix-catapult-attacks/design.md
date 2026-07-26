## Context

Catapult handling is split across the attack confirmation template, troop dispatch, battle calculation, simulator calculation, and a large duplicated persistence block in `Automation.php`. The code assumes every tribe's position-eight unit is a catapult, which is valid for the three player tribes and Natars but incorrectly classifies Nature's Crocodile. Real combat and the simulator also use different formulas for partial damage.

The project is legacy PHP 7.4 without a test framework. Changes must retain the procedural bootstrap and shared singleton style, avoid schema changes, and preserve the report payload consumed by existing notice templates.

## Goals / Non-Goals

**Goals:**

- Make target selection visible and server-authoritative for armies containing a real catapult unit.
- Make explicit and random target resolution safe for buildings, resource fields, and the World Wonder slot.
- Centralize firing-power and level-damage calculations so simulator and live combat agree.
- Apply one or two impacts without duplicated persistence code.
- Preserve capacity, alliance, population, and village-destruction side effects.
- Support Roman, Teuton, Gaul, and Natar catapult units while excluding Nature animals.
- Prevent invalid unit speed data from crashing troop dispatch.
- Provide repeatable PHP regression checks.

**Non-Goals:**

- Redesign the broader combat system or report UI.
- Make Nature or Natars selectable during player registration.
- Change building prerequisites, training costs, or intended tribe balance.
- Introduce a framework, autoloader, or database migration.

## Decisions

### Use explicit catapult-unit mapping

Add a shared mapping for catapult-capable global unit IDs (`8`, `18`, `28`, `48`) instead of treating every position-eight unit as siege. This preserves the existing position-based attack payload while preventing `u38` Crocodiles from damaging buildings.

Alternative considered: keep the generic position-eight rule. Rejected because unit 38 is explicitly a Nature animal and not a siege weapon.

### Centralize siege arithmetic in `Battle`

Expose small deterministic methods that calculate surviving firing power and the resulting target level. Both `simulate()` and `calculateBattle()` will call them, and live target application will call the same level-outcome method for each resolved target.

The damage result will include required firing power, effective damage, and remaining level. Destruction uses `>=`; partial damage applies smithy upgrade, moral bonus, and durability exactly as the simulator does.

Alternative considered: patch each duplicated formula independently. Rejected because it would retain drift between simulator and live combat.

### Resolve target slots before applying impacts

`Automation` will build an array of eligible occupied slots and select from it with `array_rand()`. An explicit target means a building/resource type, while `0` means any occupied slot. A missing explicit type falls back to a valid random slot. The second impact re-reads field data after the first so destroyed slots are not selected again accidentally.

### Consolidate persistence side effects

A single impact method will update the field level, clear the building type only for building slots destroyed to zero, adjust storage/granary/alliance derived values, recount population, and trigger existing village-destruction cleanup when applicable.

### Validate dispatch inputs and speed

Catapult targets will be normalized to the allowed building type range before insertion. A second target is accepted only when the rally point is level 20. Travel-speed calculation will use a positive fallback for malformed unit data so dispatch cannot divide by zero.

## Risks / Trade-offs

- [Legacy reports depend on loosely formatted catapult strings] → Preserve the existing single-target payload shape and verify notice rendering inputs in regression checks.
- [Refactoring a large live-combat block could alter secondary side effects] → Extract existing capacity, alliance, population, and village cleanup behavior rather than removing it.
- [Random tests can be flaky] → Test membership and validity of selected slots rather than expecting a specific random slot.
- [Existing Natar Ballistas have zero speed in static data] → Apply a dispatch fallback without changing current stored armies or upkeep balance.
- [Concurrent impacts can observe changing field state] → Reload target fields before every impact.

## Migration Plan

Deploy the PHP/template changes directly; no database migration is required. Existing in-flight attacks retain their stored `ctar1`/`ctar2` values and are normalized during resolution. Rollback consists of reverting the PHP/template files.

## Open Questions

None.
