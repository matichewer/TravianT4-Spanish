# independent-natar-villages Specification

## Purpose
Makes an independent Natar village answerable to everything a player can do to it.
## Requirements
### Requirement: Building damage persists and is repaired over time
The system SHALL keep a destroyed field or building destroyed, and SHALL raise it back toward the level its age allows at no more than one level per repair interval, which SHALL be shorter than the growth interval.

#### Scenario: Catapults destroy a field
- **WHEN** a field of an independent Natar village is destroyed and the village is brought up to date moments later
- **THEN** the field is still destroyed

#### Scenario: Time passes after the damage
- **WHEN** one repair interval elapses
- **THEN** the field has gained exactly one level
- **AND** it never exceeds the level the village's age allows

### Requirement: Damage reaches the garrison
The system SHALL derive the sustainable garrison and the training rate from the village's actual fields and buildings, not from the ideal for its age.

#### Scenario: The crop fields are destroyed
- **WHEN** an independent Natar village's crop fields are destroyed
- **THEN** its sustainable garrison drops, and the troops above it starve away

#### Scenario: The barracks is destroyed
- **WHEN** the barracks is destroyed
- **THEN** the village retrains more slowly until it is rebuilt

### Requirement: A conquered village hands over no troops
The system SHALL dissolve the garrison of a conquered village rather than transferring it, and SHALL do so only once the transfer of ownership has succeeded.

#### Scenario: A player conquers a Natar village
- **WHEN** an independent Natar village is conquered
- **THEN** it belongs to the player, is no longer an NPC village, and holds no troops

#### Scenario: The conquest does not complete
- **WHEN** the ownership transfer fails
- **THEN** the defender keeps their garrison

### Requirement: Village names repair themselves
The system SHALL rename an independent Natar village whose name does not match the one derived from its identifier, and the derived name SHALL carry the village's coordinates.

#### Scenario: A village named before names carried coordinates
- **WHEN** such a village is brought up to date
- **THEN** it is renamed to its derived name, coordinates included

#### Scenario: A conquered village
- **WHEN** a village has been conquered by a player
- **THEN** its name is left alone

### Requirement: Independent villages are distinguishable
The system SHALL give each independent Natar village a name derived from its own identifier, so that no two share a name and the name does not change when the village's state is recomputed.

#### Scenario: Several villages exist
- **WHEN** the world holds several independent Natar villages
- **THEN** each carries a different name

#### Scenario: The village is recomputed
- **WHEN** an independent village is brought up to date
- **THEN** its name does not change

#### Scenario: A name is shown on the map
- **WHEN** a village name is rendered in the map tooltip
- **THEN** the word for village is not repeated in front of a name that already contains it

### Requirement: Villages carry an explicit NPC kind
The system SHALL record on every village whether it is a player village, a static NPC village or a living NPC village, and SHALL decide NPC behaviour from that record rather than inferring it from ownership, the capital flag or the Wonder marker.

#### Scenario: Existing world is migrated
- **WHEN** the migration runs on a world created before this change
- **THEN** every village owned by a system account is recorded as static
- **AND** every other village is recorded as a player village

#### Scenario: A world without the migration applied
- **WHEN** the kind is unavailable for a village
- **THEN** the village behaves as it did before this change rather than failing

### Requirement: Static NPC villages are unchanged
The system MUST continue to exempt static NPC villages from troop upkeep and starvation, and MUST NOT grow, train or replace their garrison.

#### Scenario: A Wonder village after this change
- **WHEN** production is credited to a Wonder village or the starvation sweep reaches it
- **THEN** its garrison consumes no crop, loses no troop and gains no troop

### Requirement: Living NPC villages obey player economy rules
The system SHALL charge troop upkeep to a living NPC village and SHALL let it starve when its granary empties and its balance is negative.

#### Scenario: A living village outgrows its fields
- **WHEN** a living village's garrison consumes more crop than its fields produce and its granary is empty
- **THEN** troops die of hunger until the balance returns to zero

#### Scenario: Raided down to nothing
- **WHEN** a player kills part of a living village's garrison
- **THEN** the village's crop balance recovers accordingly

### Requirement: Living village state is derived from its age
The system SHALL derive a living village's field levels, production, storage and sustainable garrison from its age and its own identifier, so that recomputing the same village twice yields the same result.

#### Scenario: A newly spawned village
- **WHEN** a living village has just spawned
- **THEN** its fields sit at the configured starting level
- **AND** its garrison is small enough to be defeated by an army of a few hundred troops

#### Scenario: A mature village
- **WHEN** a living village has reached the configured maximum field level
- **THEN** its fields stop growing
- **AND** its garrison stops growing at what its crop can feed

#### Scenario: Recomputed twice
- **WHEN** the same living village is brought up to date twice in a row without time passing
- **THEN** the second pass changes nothing

### Requirement: The garrison advances toward its target, bounded
The system SHALL advance a living village's garrison toward its sustainable target at a rate derived from its own barracks and stable levels and the unit training times, SHALL never exceed the target, and SHALL bound how much a single catch-up can add regardless of how long the village went untouched.

#### Scenario: A village untouched for a long time
- **WHEN** a living village is brought up to date after months without being touched
- **THEN** its garrison does not exceed its sustainable target
- **AND** the single catch-up adds no more than the configured bound

#### Scenario: Concurrent updates
- **WHEN** two requests bring the same living village up to date at the same moment
- **THEN** the same interval is credited only once

#### Scenario: A cleared village rearms
- **WHEN** a player destroys a living village's whole garrison and time passes
- **THEN** the village trains troops again toward its target

### Requirement: Living villages spawn within reach of players
The system SHALL create living NPC villages over time up to a configured cap, placing each on a free village field within a configured distance band of an existing player village, and SHALL leave the world unchanged when no suitable field exists.

#### Scenario: A spawn is due
- **WHEN** the spawn interval has elapsed and the world holds fewer living villages than the cap
- **THEN** one living village is created within the distance band of a player village

#### Scenario: The cap is reached
- **WHEN** the world already holds the configured number of living villages
- **THEN** no further village is created

#### Scenario: No free field in the band
- **WHEN** every field in the band around the chosen village is occupied
- **THEN** no village is created and no field is marked as taken

#### Scenario: Nobody is playing
- **WHEN** no request reaches the server
- **THEN** no village spawns, and the backlog of missed spawns does not create several at once when play resumes

### Requirement: Living villages can be farmed and razed
The system SHALL let players raid a living NPC village for the resources it holds above its cranny, SHALL let catapults raze it, and MUST NOT allow reinforcements to be sent to it.

#### Scenario: A raid on a living village
- **WHEN** a player raids a living NPC village that holds resources above its cranny
- **THEN** the raid returns loot

#### Scenario: Razed to nothing
- **WHEN** catapults reduce a living NPC village to zero population
- **THEN** the village is removed from the map and its field is freed

#### Scenario: The Natar capital is attacked the same way
- **WHEN** catapults reduce the Natar capital to zero population
- **THEN** the village survives, because a capital is never razed

#### Scenario: Reinforcing a living village
- **WHEN** a player sends reinforcements to a living NPC village
- **THEN** the system refuses

### Requirement: NPC villages stay out of the rankings
The system SHALL exclude every village and account owned by the system from the player, village, attack and defence rankings, using the shared account boundary.

#### Scenario: Many living villages exist
- **WHEN** the world holds living NPC villages
- **THEN** they appear in neither the village ranking nor the player ranking

#### Scenario: A player village of comparable size
- **WHEN** a player village has the same population as a living NPC village
- **THEN** only the player village is ranked

