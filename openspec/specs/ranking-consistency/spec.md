# ranking-consistency Specification

## Purpose
Ensure weekly ranking scores remain consistent between players and their current alliances and present each ranked entry only once.
## Requirements
### Requirement: Alliance combat scores equal current member scores
The system SHALL keep each alliance's weekly attack, defense, and net-raiding totals equal to the sum of those scores for its current ranked members.

#### Scenario: Combat score changes
- **WHEN** a ranked player's weekly attack, defense, or net-raiding score changes
- **THEN** the same delta is applied to the alliance to which that player currently belongs

#### Scenario: Player changes alliance
- **WHEN** a player joins, leaves, or is expelled from an alliance
- **THEN** the player's existing weekly scores are removed from the former alliance and added to the new alliance

### Requirement: Raiding is a net balance
The system SHALL define weekly raiding score as resources stolen minus resources lost to raids.

#### Scenario: Resources are stolen
- **WHEN** one player steals resources from another player
- **THEN** the stolen amount is added to the attacker and attacker's alliance and subtracted from the victim and victim's alliance

### Requirement: Membership does not count as population growth
The system SHALL preserve earned alliance population growth when membership changes without treating the transferred population as growth or loss.

#### Scenario: Player joins or leaves
- **WHEN** alliance membership changes without any village population changing
- **THEN** the alliance growth score changes only by transferring the player's already-earned weekly growth score

### Requirement: Top 10 entries are unique
The system SHALL show the current player or alliance below a Top 10 only when that entry is outside the displayed Top 10.

#### Scenario: Current entry is already ranked
- **WHEN** the current player or alliance is already in the displayed Top 10
- **THEN** the ranking table contains that entry exactly once

#### Scenario: Current entry is outside the Top 10
- **WHEN** the current player or alliance is outside the displayed Top 10
- **THEN** its position is shown once after the Top 10

