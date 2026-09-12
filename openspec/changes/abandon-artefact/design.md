## Context

See proposal.md for motivation. ArtefactRelease.php already derives garrisons, selects rings and provisions static NPC villages. Several village tables use MyISAM, so transaction rollback cannot provide atomicity. Artefact ownership and activation are read through a request cache.

## Goals / Non-Goals

Goals: reuse village generation, preserve artefact identity, reject unauthorized or stale actions, and clean up failed creation with mixed storage engines.
Non-goals: changing theft rules, deleting the original village or implementing a new artefact release schedule.

## Decisions

- Extract shared village provisioning from the release creator; abandonment updates the existing artefact only after provisioning succeeds.
- Use the existing default release configuration and current world offence reference for defence; construction plans use their dedicated rules. No user-supplied destination or defence values.
- Serialize the short creation and transfer operation with table locks covering the affected MyISAM and InnoDB tables. Validate ownership again under the lock. Clean up only rows created on the selected empty tile upon failure. A transaction alone would not roll back MyISAM.
- Enable SQL exceptions for this bounded operation and restore the previous setting afterward so legacy unchecked SQL failures cannot announce success.
- POST with session token, explicit confirmation checkbox, sitter exclusion, and a redirect after success. Flush artefact caches after transfer. The detail page explains the consequence and links to the new village.
- Accrue the owner's villages through Automation before transferring, using the old effects. Removing any artefact can activate a displaced diet artefact; all villages must close their elapsed production period before that change.

## Risks / Trade-offs

- Table locks briefly pause concurrent village updates → calculate defence before locking and keep only creation and transfer inside the lock.
- Process termination during MyISAM writes cannot be rolled back automatically → transfer ownership last; ordinary SQL failures clean up the reserved tile and rows before unlocking.
- The original Natar village may be occupied → always select a new free tile, including a whole-map fallback.

## Migration Plan

No schema migration is required. Deploy PHP and templates together; rollback removes the action while already relocated artefacts remain normal capturable artefacts.
