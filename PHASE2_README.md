# VNDTES — Troubleshoot Phase 2 Refactor

This package is based on the exact `student/troubleshoot.php` supplied on 2026-09-24.

## What changed

Phase 2 extracts two responsibilities without intentionally changing troubleshooting behaviour:

1. `student/troubleshoot_actions.php`
   - Contains the existing POST action handler.
   - Handles the same allowed actions, inspections, checks, diagnosis, correction, verification, and submission.
   - Preserves the existing hidden-fault target inputs and action logging.
   - Returns the same page-state values to the main page.
   - Passes `$attempt` by reference because submission updates its status.

2. `student/troubleshoot_data.php`
   - Contains the existing scenario device/interface/connection queries.
   - Contains assigned-fault loading and student activity loading.
   - Preserves the existing `last_activity_at` update.

3. `student/troubleshoot.php`
   - Now loads the two helpers and calls them.
   - Presentation HTML and inline CSS are intentionally left in place for Phase 2.

## Deliberately NOT changed

- No new troubleshooting rules were introduced.
- No verification/evaluation logic was redesigned.
- No topology/effective-network-state verification was added.
- No database schema was changed.
- No presentation/CSS extraction was performed.

## Installation

Replace the current `student/troubleshoot.php` with the supplied Phase 2 version and copy the two helper files into the same `student/` directory.

Before replacing a working copy, keep a backup of the original file.


## Phase 2 topology integration

The interactive topology from Phase 1 is now wired into `student/troubleshoot.php`.
The page renders a real topology workspace from `scenario_device_interfaces`, draws master
`scenario_connections`, loads active student links from `scenario_attempt_connections`, and
uses `student/topology_action.php` for student connection creation/removal.

Added:
- `assets/js/topology.js`
- `assets/css/topology.css`
- `student/topology_action.php`

The existing troubleshooting action logic remains unchanged.
