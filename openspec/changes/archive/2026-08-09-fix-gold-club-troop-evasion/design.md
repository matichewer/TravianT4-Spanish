## Context

Gold Club troop evasion is resolved when an incoming attack is processed. The current implementation reads only pending return movements relative to wall-clock processing time, even though ordinary returns are processed earlier in the same automation bootstrap. It also adds the local hero to the same temporary return movement as all ten tribe units.

The hero table already has a `hide` preference and the hero inventory exposes it. Later battle preparation honors that preference by removing a hidden local hero from the defending force, but the earlier Gold Club block currently removes the hero first regardless of the preference.

## Goals / Non-Goals

**Goals:**

- Evaluate the ten-second restriction against the attack's scheduled arrival and retain processed return rows as evidence.
- Exclude automatic-evasion returns while detecting ordinary returns.
- Evade all ten locally owned tribe units, including settlers, while leaving reinforcements untouched.
- Never couple the hero to the Gold Club troop movement; let the existing `hide` preference decide whether the hero defends.
- Protect hero preference updates with the existing session checker.
- Provide deterministic regression coverage without mutating the game database.

**Non-Goals:**

- Changing combat formulas or general movement timing.
- Adding a second hero preference or changing the hero schema.
- Making reinforcement troops eligible for capital evasion.

## Decisions

1. Add a focused database lookup for ordinary type-4 return movements whose scheduled arrival falls inclusively between `attack_endtime - 10` and `attack_endtime`. The lookup ignores `proc`, because a return may already have been processed, and excludes rows with `from = 0`, which identify automatic-evasion returns. A compound index beginning with destination, movement type, and arrival time supports the lookup. This is safer than reordering the automation bootstrap, which could affect every movement type.

2. Put the time-window predicate in a small public Automation helper that accepts the database dependency. The live attack path and a stub-based regression test will exercise the same decision without requiring MyISAM test fixtures.

3. Build automatic evasion with tribe positions 1 through 10 only and write zero into the hero position of its attack record. The existing later `hide` handling remains the single source of truth for whether the local hero participates in combat.

4. Replace the hero preference's GET mutations with a POST form using `mchecker`. The form writes only normalized values `0` or `1` for the authenticated account and redirects after success.

## Risks / Trade-offs

- [Processed movement rows are retained indefinitely] → The lookup uses the destination and a narrow indexed-time range without changing cleanup behavior.
- [Legacy MySQL and MySQLi adapters can diverge] → Add the same focused lookup to both adapters and cover their source shape in regression checks.
- [The word “hide” can be confused with Gold Club evasion] → Label the inventory option explicitly as independent and state whether the hero will defend.

## Migration Plan

Add the compound movement index through `tools/migrations.sql` on existing worlds, then deploy the PHP and template changes normally. New installations receive the index from the installer schema. Rollback may revert the code while leaving the harmless index in place; the existing `hero.hide` values remain compatible.

## Open Questions

None.
