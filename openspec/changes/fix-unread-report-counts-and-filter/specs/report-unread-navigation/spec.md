## Purpose

Provide an accurate, visually distinguishable summary of unread reports and a direct list containing only reports the player has not read.

## ADDED Requirements

### Requirement: Unread report badges reflect visible owned reports
The system SHALL count each undeleted unread report owned by the current player exactly once and SHALL classify route, trade, adventure, and other report kinds independently.

#### Scenario: Mixed unread reports
- **WHEN** a player owns unread route, trade, adventure, and other reports
- **THEN** each report contributes exactly once to its matching badge and the badge total equals the unread report total

#### Scenario: Hidden or foreign reports
- **WHEN** reports are read, deleted, or owned by another player
- **THEN** they do not contribute to the current player's unread badges

### Requirement: Semantic unread badge colors
The system SHALL display route badges in a muted semitransparent gray, trade badges in dark gray, and adventure badges in green; no other report category SHALL use the adventure green.

#### Scenario: Route, trade, and adventure badges are present
- **WHEN** all three unread categories are displayed
- **THEN** each uses its required distinct semantic color

### Requirement: Unread-only report navigation
The system SHALL provide a "No leídos" tab that lists all and only the current player's undeleted, unarchived unread reports, with normal pagination and report actions.

#### Scenario: Open unread tab
- **WHEN** the player selects "No leídos"
- **THEN** the list contains unread unarchived reports across every report category and excludes read, archived, deleted, and foreign reports

#### Scenario: Report navigation remains within the report panel
- **WHEN** all report tabs available to the player are rendered
- **THEN** the navigation distributes the tabs across the available bar width without clipping a tab outside the fixed-width report panel or leaving large unused side gaps

### Requirement: Report detail preserves its source filter
The system SHALL keep the active report category when a report is opened and SHALL navigate to newer or older reports within that category.

#### Scenario: Open and navigate a route report
- **WHEN** a player opens a report from the Rutas tab and uses "Más reciente" or "Más antiguo"
- **THEN** the Rutas tab stays active and the destination is the adjacent route report
