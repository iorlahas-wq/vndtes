# VNDTES Topology Refactor — Phase 1

This pack is the first controlled refactoring step for the student troubleshooting workspace.

## Files

- `student/topology_action.php`
  - Validates the logged-in student.
  - Validates ownership of the attempt.
  - Validates that both interfaces belong to the attempt's scenario.
  - Creates student connections in `scenario_attempt_connections`.
  - Removes only student-created connections.
  - Records topology mutations in `scenario_attempt_actions`.
  - Updates `scenario_attempts.last_activity_at`.
  - Never modifies `scenario_connections`.

- `assets/js/topology.js`
  - Renders devices/interfaces.
  - Draws master scenario links.
  - Draws student-created links.
  - Sends connection mutations to `topology_action.php`.
  - Does not contain SQL or database logic.

- `assets/css/topology.css`
  - Contains topology-only styling moved out of `troubleshoot.php`.

## Existing page integration

The current topology-enabled `troubleshoot.php` already provides:

```php
window.VNDTES_TOPOLOGY = ...;
window.VNDTES_TOPOLOGY_CONNECTIONS = ...;
window.VNDTES_TOPOLOGY_STUDENT_CONNECTIONS = ...;
```

and:

```html
<link rel="stylesheet" href="../assets/css/topology.css">
<script src="../assets/js/topology.js"></script>
<script src="../assets/js/troubleshoot.js"></script>
```

Keep that data contract.

## Important design rule

`scenario_connections` is the lecturer/master scenario topology.

`scenario_attempt_connections` is student attempt topology.

This phase deliberately does not alter `scenario_connections`.

## Current limitation

The existing schema information available to this refactor confirms these
columns on `scenario_attempt_connections`:

- `student_connection_id`
- `attempt_id`
- `interface_a_id`
- `interface_b_id`
- `connection_type`
- `status`

The exact full CREATE TABLE definition was not available in the supplied
source, so this code intentionally uses only those confirmed columns.

Before extending the topology model to support disconnecting a master scenario
connection, we should inspect the exact table definition and decide how an
attempt records an override/removal of a master link.

## Next phase

After this phase is tested:

1. Move the large data-loading section out of `troubleshoot.php`.
2. Move the POST action controller out of `troubleshoot.php`.
3. Move the inline workspace CSS out of `troubleshoot.php`.
4. Convert the remaining page sections into partials.
5. Then implement effective-topology verification.
