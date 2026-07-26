## Context

Settler training is processed by the generic troop-training endpoint, but expansion units bypass the normal resource affordability check and the endpoint does not prove that the submitted field contains a qualifying Residence or Palace. Village founding validates three settlers and culture points, yet only counts pending settlements from the current village. Settlement completion performs several MyISAM writes without rechecking culture capacity or checking every initialization result.

The implementation must fit the legacy PHP 7.4/global-singleton architecture, preserve the configured tribe-specific costs, and avoid schema changes that would require production migration.

## Goals / Non-Goals

**Goals:**

- Make server-side training authoritative for positive quantity, qualifying building, expansion capacity and resources.
- Serialize settlement-capacity decisions per account without requiring transactional table conversion.
- Treat pending settlements from every owned village as account-wide culture-capacity reservations.
- Revalidate culture capacity at arrival and safely refund failed settlements.
- Keep UI eligibility messages aligned with server rules.
- Use Germano/Germanos consistently in player-facing Spanish text.
- Provide repeatable regression coverage for the corrected rules.

**Non-Goals:**

- Equalizing settler costs across tribes.
- Spending culture points when a village is founded or conquered.
- Redesigning all troop training or converting the legacy MyISAM schema.
- Changing configured culture thresholds except for detecting invalid table data.

## Decisions

1. Add database helpers for account-wide pending settlement counts and MySQL named locks. A per-account named lock serializes launch and arrival decisions even though the legacy tables are MyISAM. This avoids a production schema migration while closing concurrent-request races.

2. Store the founding account id in the settlement movement `data` field. New arrivals no longer infer ownership from the current owner of the source village. Legacy movements whose data is zero continue to fall back to the source owner.

3. Recompute unlocked expansion slots explicitly from Residence/Palace levels, then subtract occupied slots and all settlers/chiefs already present, queued or moving. Returned availability is clamped at zero.

4. Require a valid training token and verify that expansion units are submitted from the actual Residence/Palace field at an eligible level. Deduct resources conditionally and refund them if queue insertion fails.

5. Revalidate culture capacity immediately before claiming a target. Arrivals are processed in end-time order; if capacity is no longer available, the settlers and founding resources are refunded and the movement completes without creating a village.

6. Check every village-initialization write. On partial failure, remove only the newly created settlement rows and release the map field so the movement can retry.

7. Centralize the culture-capacity calculation in a pure helper used by server and templates. This prevents the map, confirmation page and launch endpoint from drifting apart.

## Risks / Trade-offs

- [Named lock cannot be acquired promptly] → Leave the request or movement unchanged so it can be retried safely.
- [Source village changes owner while settlers travel] → Use the account id stored at launch; refund to another village still owned by that account when necessary.
- [Partial MyISAM initialization failure] → Perform explicit compensating cleanup and do not mark the movement processed.
- [Adding CSRF validation breaks an unupdated training form] → Update every troop-training form that posts `ft=t1` or `ft=t3` and cover the forms with repository searches.
- [Legacy pending movements contain no owner id] → Count and process them through a source-owner fallback.

## Migration Plan

No schema migration is required. Deploy the PHP/template changes normally. Existing pending settlement movements with `data=0` remain compatible. Rollback consists of reverting the code; no persisted data transformation is introduced.

## Open Questions

None.
