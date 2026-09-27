<?php

declare(strict_types=1);

require_once '../includes/init.php';
require_once '../includes/auth.php';

/*
|--------------------------------------------------------------------------
| Student Access Control
|--------------------------------------------------------------------------
*/

if (currentUserRole() !== 'Student') {
    redirect(APP_URL);
}

/*
|--------------------------------------------------------------------------
| Page State & Input Initialization
|--------------------------------------------------------------------------
*/

$pageTitle = 'Troubleshooting Workspace';

$attemptId = isset($_GET['attempt_id']) ? (int) $_GET['attempt_id'] : 0;
$scenarioId = isset($_GET['scenario_id']) ? (int) $_GET['scenario_id'] : 0;
$studentUserId = currentUserId();

$studentId = 0;
$attempt = null;
$scenario = null;

$scenarioDevices = [];
$scenarioInterfaces = [];
$scenarioConnections = [];
$studentTopologyConnections = [];
$assignedFaults = [];
$activityLog = [];

$selectedDevice = null;
$selectedInterface = null;
$selectedConnection = null;
$selectedActionResult = null;
$selectedDiagnosis = null;
$verificationResult = null;

$targetFault = null;
$targetFaultId = 0;

$message = null;
$messageType = 'info';

/*
|--------------------------------------------------------------------------
| Resolve Logged-in User to Student Record
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT student_id, matric_no
    FROM students
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$studentUserId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    require_once '../includes/layout_start.php';
    ?>
    <div class="container-fluid py-4">
        <div class="card dashboard-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-person-x text-danger" style="font-size:60px;"></i>
                <h3 class="fw-bold mt-3">Student Record Not Found</h3>
                <p class="text-muted mb-0">Your user account is not currently linked to a student record.</p>
            </div>
        </div>
    </div>
    <?php
    require_once '../includes/layout_end.php';
    exit;
}

$studentId = (int) $student['student_id'];

/*
|--------------------------------------------------------------------------
| Create / Resume Attempt Logic
|--------------------------------------------------------------------------
*/

if ($attemptId <= 0 && $scenarioId > 0) {
    // Verify Published Scenario
    $stmt = db()->prepare("
        SELECT scenario_id
        FROM scenarios
        WHERE scenario_id = ? AND status = 'Published'
        LIMIT 1
    ");
    $stmt->execute([$scenarioId]);
    if (!$stmt->fetchColumn()) {
        redirect('exercises.php');
    }

    // Check Existing In-Progress Attempt
    $stmt = db()->prepare("
        SELECT attempt_id
        FROM scenario_attempts
        WHERE student_id = ? AND scenario_id = ? AND status = 'In Progress'
        ORDER BY attempt_id DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId, $scenarioId]);
    $existingAttemptId = (int) $stmt->fetchColumn();

    if ($existingAttemptId > 0) {
        $attemptId = $existingAttemptId;
    } else {
        // Get Next Attempt Number
        $stmt = db()->prepare("
            SELECT COALESCE(MAX(attempt_number), 0) + 1
            FROM scenario_attempts
            WHERE student_id = ? AND scenario_id = ?
        ");
        $stmt->execute([$studentId, $scenarioId]);
        $attemptNumber = (int) $stmt->fetchColumn();

        // Create New Attempt
        $stmt = db()->prepare("
            INSERT INTO scenario_attempts
            (student_id, scenario_id, attempt_number, status, started_at, last_activity_at)
            VALUES (?, ?, ?, 'In Progress', NOW(), NOW())
        ");
        $stmt->execute([$studentId, $scenarioId, $attemptNumber]);
        $attemptId = (int) db()->lastInsertId();
    }

    redirect('troubleshoot.php?attempt_id=' . $attemptId);
}

/*
|--------------------------------------------------------------------------
| Validate Attempt ID
|--------------------------------------------------------------------------
*/

if ($attemptId <= 0) {
    require_once '../includes/layout_start.php';
    ?>
    <div class="container-fluid py-4">
        <div class="card dashboard-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-exclamation-triangle text-warning" style="font-size:60px;"></i>
                <h3 class="fw-bold mt-3">Troubleshooting Attempt Not Found</h3>
                <p class="text-muted">Please return to the available exercises and start an exercise.</p>
                <a href="exercises.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Exercises
                </a>
            </div>
        </div>
    </div>
    <?php
    require_once '../includes/layout_end.php';
    exit;
}

/*
|--------------------------------------------------------------------------
| Load Attempt Data Securely
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT
        sa.attempt_id, sa.student_id, sa.scenario_id, sa.attempt_number,
        sa.status, sa.started_at, sa.submitted_at, sa.completed_at, sa.last_activity_at,
        s.scenario_code, s.scenario_title, s.category, s.difficulty,
        s.estimated_time, s.instructions, s.expected_outcome
    FROM scenario_attempts sa
    INNER JOIN scenarios s ON s.scenario_id = sa.scenario_id
    WHERE sa.attempt_id = ? AND sa.student_id = ?
    LIMIT 1
");
$stmt->execute([$attemptId, $studentId]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    require_once '../includes/layout_start.php';
    ?>
    <div class="container-fluid py-4">
        <div class="card dashboard-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-shield-exclamation text-danger" style="font-size:60px;"></i>
                <h3 class="fw-bold mt-3">Attempt Not Available</h3>
                <p class="text-muted">This troubleshooting attempt could not be found or does not belong to your account.</p>
                <a href="exercises.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Exercises
                </a>
            </div>
        </div>
    </div>
    <?php
    require_once '../includes/layout_end.php';
    exit;
}

$scenarioId = (int) $attempt['scenario_id'];
$scenario = $attempt;

/*
|--------------------------------------------------------------------------
| Initialize / Load Hidden Fault Target
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT action_data
    FROM scenario_attempt_actions
    WHERE attempt_id = ? AND action_type = 'SYSTEM_FAULT_TARGET'
    ORDER BY action_id DESC
    LIMIT 1
");
$stmt->execute([$attemptId]);
$targetRecord = $stmt->fetchColumn();

if ($targetRecord) {
    $targetData = json_decode((string) $targetRecord, true);
    $targetFaultId = (int) ($targetData['fault_id'] ?? 0);
} else {
    // Select Random Active Scenario Fault
    $stmt = db()->prepare("
        SELECT sf.fault_id
        FROM scenario_faults sf
        INNER JOIN faults f ON f.fault_id = sf.fault_id
        WHERE sf.scenario_id = ? AND sf.is_active = 1 AND f.status = 'Active'
        ORDER BY RAND()
        LIMIT 1
    ");
    $stmt->execute([$scenarioId]);
    $targetFaultId = (int) $stmt->fetchColumn();

    if ($targetFaultId > 0) {
        $stmt = db()->prepare("
            INSERT INTO scenario_attempt_actions
            (attempt_id, action_type, target_type, target_id, action_data, action_result)
            VALUES (?, 'SYSTEM_FAULT_TARGET', 'system', ?, ?, ?)
        ");
        $targetData = json_encode(['fault_id' => $targetFaultId], JSON_UNESCAPED_UNICODE);
        $targetResult = json_encode(['result' => 'Hidden fault target initialised.'], JSON_UNESCAPED_UNICODE);
        $stmt->execute([$attemptId, $targetFaultId, $targetData, $targetResult]);
    }
}

if ($targetFaultId > 0) {
    $stmt = db()->prepare("
        SELECT fault_id, fault_code, fault_title, category, difficulty, affected_device_type, symptoms, probable_cause, expected_solution
        FROM faults
        WHERE fault_id = ? AND status = 'Active'
        LIMIT 1
    ");
    $stmt->execute([$targetFaultId]);
    $targetFault = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/*
|--------------------------------------------------------------------------
| Action Processing & Workspace Data Loading (External Integration)
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/troubleshoot_actions.php';
$actionState = handleTroubleshootPost($attempt, $attemptId, $scenarioId, $studentId, $targetFault, $targetFaultId);

$message = $actionState['message'];
$messageType = $actionState['messageType'];
$selectedDevice = $actionState['selectedDevice'];
$selectedInterface = $actionState['selectedInterface'];
$selectedConnection = $actionState['selectedConnection'];
$selectedActionResult = $actionState['selectedActionResult'];
$selectedDiagnosis = $actionState['selectedDiagnosis'];
$verificationResult = $actionState['verificationResult'];

require_once __DIR__ . '/troubleshoot_data.php';
$workspaceData = loadTroubleshootData($scenarioId, $attemptId);

$scenarioDevices = $workspaceData['scenarioDevices'];
$scenarioInterfaces = $workspaceData['scenarioInterfaces'];
$scenarioConnections = $workspaceData['scenarioConnections'];
$studentTopologyConnections = $workspaceData['studentTopologyConnections'];
$assignedFaults = $workspaceData['assignedFaults'];
$activityLog = $workspaceData['activityLog'];

/*
|--------------------------------------------------------------------------
| Page Statistics & Metric Configuration
|--------------------------------------------------------------------------
*/

$requiredDeviceTypes = count($scenarioDevices);
$totalInterfaces = count($scenarioInterfaces);
$studentConnectionCount = count($studentTopologyConnections);
$recordedActionCount = count($activityLog);
$difficulty = $scenario['difficulty'] ?? 'Beginner';

$difficultyClass = match ($difficulty) {
    'Beginner' => 'success',
    'Intermediate' => 'warning text-dark',
    'Advanced' => 'danger',
    default => 'secondary'
};

/*
|--------------------------------------------------------------------------
| Attempt Submission & Latest States Assessment
|--------------------------------------------------------------------------
*/

$submissionAssessment = null;
$submittedScore = 0;
$submittedStatus = 'Submitted';
$submittedReason = '';

if (($attempt['status'] ?? '') === 'Submitted') {
    $stmt = db()->prepare("
        SELECT action_result
        FROM scenario_attempt_actions
        WHERE attempt_id = ? AND action_type = 'SUBMIT_ATTEMPT'
        ORDER BY action_id DESC
        LIMIT 1
    ");
    $stmt->execute([$attemptId]);
    $submissionRecord = $stmt->fetchColumn();

    if ($submissionRecord) {
        $submissionAssessment = json_decode((string) $submissionRecord, true);
    }

    $submittedScore = (int) ($submissionAssessment['score'] ?? 0);
    $submittedStatus = (string) ($submissionAssessment['status'] ?? 'Submitted');
    $submittedReason = (string) ($submissionAssessment['reason'] ?? 'The troubleshooting attempt has been submitted successfully.');
}

// Latest Diagnosis State
$latestDiagnosisCorrect = false;
$latestDiagnosisTitle = '';
$stmt = db()->prepare("
    SELECT action_result FROM scenario_attempt_actions
    WHERE attempt_id = ? AND action_type = 'DIAGNOSE_FAULT'
    ORDER BY action_id DESC LIMIT 1
");
$stmt->execute([$attemptId]);
if ($latestDiagnosisRecord = $stmt->fetchColumn()) {
    $latestDiagnosis = json_decode((string) $latestDiagnosisRecord, true);
    $latestDiagnosisCorrect = !empty($latestDiagnosis['correct']);
    $latestDiagnosisTitle = (string) ($latestDiagnosis['fault_title'] ?? '');
}

// Latest Verification State
$latestVerificationVerified = false;
$stmt = db()->prepare("
    SELECT action_result FROM scenario_attempt_actions
    WHERE attempt_id = ? AND action_type = 'VERIFY_SOLUTION'
    ORDER BY action_id DESC LIMIT 1
");
$stmt->execute([$attemptId]);
if ($latestVerificationRecord = $stmt->fetchColumn()) {
    $latestVerification = json_decode((string) $latestVerificationRecord, true);
    $latestVerificationVerified = !empty($latestVerification['verified']);
}

/*
|--------------------------------------------------------------------------
| Render View Layout
|--------------------------------------------------------------------------
*/

require_once '../includes/layout_start.php';
?>

<link rel="stylesheet" href="../assets/css/topology.css">

<div class="container-fluid py-3">

    <!-- PAGE HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="badge bg-primary"><?= htmlspecialchars((string) $scenario['category']) ?></span>
                <span class="badge bg-<?= $difficultyClass ?>"><?= htmlspecialchars((string) $scenario['difficulty']) ?></span>
                <span class="badge bg-info text-dark"><?= (int) $scenario['estimated_time'] ?> minutes</span>
            </div>
            <h2 class="fw-bold mb-1">Troubleshooting Workspace</h2>
            <div class="text-muted">
                <?= htmlspecialchars((string) $scenario['scenario_title']) ?>
                <span class="mx-1">—</span>
                <strong><?= htmlspecialchars((string) $scenario['scenario_code']) ?></strong>
            </div>
        </div>
        <div class="text-end">
            <div class="mb-2">
                <?php $attemptBadgeClass = (($attempt['status'] ?? '') === 'Submitted') ? 'success' : 'primary'; ?>
                <span class="badge bg-<?= $attemptBadgeClass ?> fs-6"><?= htmlspecialchars((string) $attempt['status']) ?></span>
            </div>
            <div class="small text-muted">Attempt #<?= (int) $attempt['attempt_number'] ?></div>
        </div>
    </div>

    <!-- STATISTICS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="workspace-stat border rounded-3 p-3 bg-white h-100">
                <div class="small text-muted">Required Device Types</div>
                <div class="fs-3 fw-bold"><?= $requiredDeviceTypes ?></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="workspace-stat border rounded-3 p-3 bg-white h-100">
                <div class="small text-muted">Interfaces</div>
                <div class="fs-3 fw-bold"><?= $totalInterfaces ?></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="workspace-stat border rounded-3 p-3 bg-white h-100">
                <div class="small text-muted">Student Network Links</div>
                <div class="fs-3 fw-bold text-success"><?= $studentConnectionCount ?></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="workspace-stat border rounded-3 p-3 bg-white h-100">
                <div class="small text-muted">Recorded Actions</div>
                <div class="fs-3 fw-bold"><?= $recordedActionCount ?></div>
            </div>
        </div>
    </div>

    <!-- INSTRUCTIONS -->
    <div class="alert alert-primary d-flex gap-3 align-items-start mb-4">
        <i class="bi bi-info-circle-fill fs-4"></i>
        <div>
            <div class="fw-bold mb-1">Troubleshooting Instructions</div>
            <div><?= nl2br(htmlspecialchars((string) ($scenario['instructions'] ?? ''))) ?></div>
        </div>
    </div>

    <!-- MAIN NETWORK LAB -->
    <div class="card dashboard-card mb-4">
        <div class="card-header bg-info text-dark">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="fw-bold"><i class="bi bi-diagram-3-fill me-1"></i> Network Troubleshooting Lab</div>
                <a href="exercises.php" class="btn btn-sm btn-light"><i class="bi bi-arrow-left me-1"></i> Exercises</a>
            </div>
        </div>
        <div class="card-body">
           <div
                id="topologyWorkspace"
                class="vndtes-topology-workspace"
                data-attempt-id="<?= (int) $attemptId ?>"
                data-status="<?= htmlspecialchars((string) ($attempt['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                data-can-edit="<?= (($attempt['status'] ?? '') === 'In Progress') ? '1' : '0' ?>"
            >
                <!-- TOPOLOGY TOOLBAR -->
                <div class="vndtes-topology-toolbar">
                    <div class="topology-toolbar-copy">
                        <div class="fw-bold fs-4">Build the Network</div>
                        <div class="text-muted">Add only the equipment required by the scenario. Arrange the equipment on the workspace and connect the appropriate interface ports.</div>
                    </div>
                    <div class="topology-toolbar-actions">
                        <span class="topology-legend-item"><span class="topology-legend-dot student"></span> Student connection</span>
                        <span id="topologyModeBadge" class="badge bg-dark">Connect Mode</span>
                        <button type="button" id="topologyCancelConnection" class="btn btn-sm btn-outline-secondary" disabled>
                            <i class="bi bi-x-circle me-1"></i> Cancel
                        </button>
                        <button type="button" id="topologyResetWorkspace" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Board
                        </button>
                    </div>
                </div>

                <!-- BUILDER SHELL -->
                <div class="topology-builder-shell">
                    <!-- EQUIPMENT PALETTE -->
                    <aside class="topology-equipment-palette">
                        <div class="topology-palette-title">Required Equipment</div>
                        <div id="topologyEquipmentPalette">
                            <div class="topology-palette-empty">
                                <div class="spinner-border spinner-border-sm text-primary mb-2"></div>
                                <div>Loading available equipment…</div>
                            </div>
                        </div>
                        <div class="alert alert-light border mt-3 mb-0 small">
                            <i class="bi bi-lightbulb me-1"></i>
                            <strong>How to build:</strong>
                            <ol class="mb-0 mt-2 ps-3">
                                <li>Add the required equipment.</li>
                                <li>Drag devices into position.</li>
                                <li>Click the first interface port.</li>
                                <li>Click the second interface port.</li>
                            </ol>
                        </div>
                    </aside>

                    <!-- NETWORK CANVAS -->
                    <section class="topology-canvas-panel">
                        <div id="topologyCanvas" class="vndtes-topology-canvas" aria-label="Interactive network topology workspace">
                            <svg id="topologySvg" class="vndtes-topology-svg" aria-hidden="true"></svg>
                            <div id="topologyNodes" class="vndtes-topology-nodes"></div>
                            <div id="topologyEmptyState" class="topology-empty-state">
                                <div class="topology-empty-icon"><i class="bi bi-diagram-3"></i></div>
                                <h5>Network Lab Ready</h5>
                                <p>Select the required equipment from the palette to begin building the network.</p>
                            </div>
                        </div>
                        <div id="topologyStatus" class="topology-status mt-3">Start by adding the required equipment from the Equipment Palette.</div>
                    </section>
                </div>
            </div>
        </div>
    </div>

    <!-- NETWORK INSPECTION -->
    <div class="card dashboard-card mb-4">
        <div class="card-header bg-dark text-white">
            <i class="bi bi-search me-1"></i> Network Inspection
            <span class="small opacity-75 ms-2">Inspect devices, interfaces and configured scenario links.</span>
        </div>
        <div class="card-body">
            <!-- DEVICES -->
            <div class="mb-4">
                <div class="fw-bold mb-3"><i class="bi bi-router me-1"></i> Devices</div>
                <?php if (empty($scenarioDevices)): ?>
                    <div class="alert alert-light border">No scenario devices are available.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($scenarioDevices as $device): ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars((string) $device['device_name']) ?></div>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars((string) $device['device_code']) ?> · <?= htmlspecialchars((string) $device['device_type']) ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-secondary">Qty: <?= (int) $device['quantity'] ?></span>
                                    </div>
                                    <div class="small text-muted mt-2">
                                        <?= htmlspecialchars((string) ($device['vendor'] ?: 'Vendor not specified')) ?> · 
                                        <?= htmlspecialchars((string) ($device['model'] ?: 'Model not specified')) ?>
                                    </div>
                                    <form method="post" class="js-inspection-form mt-3">
                                        <input type="hidden" name="action_type" value="inspect_device">
                                        <input type="hidden" name="target_type" value="scenario_device">
                                        <input type="hidden" name="target_id" value="<?= (int) $device['scenario_device_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-search me-1"></i> Inspect Device
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <hr class="my-4">

            <!-- INTERFACES -->
            <div>
                <div class="fw-bold mb-3"><i class="bi bi-ethernet me-1"></i> Network Interfaces</div>
                <?php if (empty($scenarioInterfaces)): ?>
                    <div class="alert alert-light border">No interfaces are available for inspection.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($scenarioInterfaces as $interface): ?>
                            <?php
                            $interfaceStatus = $interface['interface_status'] ?? 'Unknown';
                            $interfaceStatusClass = match ($interfaceStatus) {
                                'Up' => 'success',
                                'Down' => 'warning text-dark',
                                default => 'danger'
                            };
                            ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars((string) $interface['interface_name']) ?></div>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars((string) $interface['device_name']) ?> · <?= htmlspecialchars((string) $interface['device_code']) ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-<?= $interfaceStatusClass ?>"><?= htmlspecialchars((string) $interfaceStatus) ?></span>
                                    </div>
                                    <div class="small mt-3"><strong>Type:</strong> <?= htmlspecialchars((string) $interface['interface_type']) ?></div>
                                    <div class="small mt-1"><strong>IP:</strong> <?= htmlspecialchars((string) ($interface['ip_address'] ?: 'Not configured')) ?></div>
                                    <div class="small mt-1"><strong>Subnet:</strong> <?= htmlspecialchars((string) ($interface['subnet_mask'] ?: 'Not configured')) ?></div>
                                    <form method="post" class="js-inspection-form mt-3">
                                        <input type="hidden" name="action_type" value="inspect_interface">
                                        <input type="hidden" name="target_type" value="interface">
                                        <input type="hidden" name="target_id" value="<?= (int) $interface['interface_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-search me-1"></i> Inspect Interface
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <hr class="my-4">

            <!-- SCENARIO CONNECTION INSPECTION -->
            <details>
                <summary class="fw-bold" style="cursor:pointer;">
                    <i class="bi bi-share-fill me-1"></i> Scenario Connection Inspection
                    <span class="badge bg-secondary ms-2"><?= count($scenarioConnections) ?></span>
                </summary>
                <div class="mt-3">
                    <div class="alert alert-light border small">
                        These are the configured scenario connections available for inspection. They are <strong>not automatically drawn on your topology board</strong>.
                    </div>
                    <?php if (empty($scenarioConnections)): ?>
                        <div class="text-muted">No scenario connections are configured.</div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($scenarioConnections as $connection): ?>
                                <div class="col-md-6 col-xl-4">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="fw-bold"><?= htmlspecialchars((string) $connection['device_a_name']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars((string) $connection['interface_a_name']) ?></div>
                                        <div class="text-center my-2">
                                            <span class="badge bg-primary"><?= htmlspecialchars((string) $connection['connection_type']) ?></span>
                                            <i class="bi bi-arrow-down-up mx-2"></i>
                                        </div>
                                        <div class="fw-bold"><?= htmlspecialchars((string) $connection['device_b_name']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars((string) $connection['interface_b_name']) ?></div>
                                        <form method="post" class="js-inspection-form mt-3">
                                            <input type="hidden" name="action_type" value="inspect_connection">
                                            <input type="hidden" name="target_type" value="connection">
                                            <input type="hidden" name="target_id" value="<?= (int) $connection['connection_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-search me-1"></i> Inspect Connection
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        </div>
    </div>

    <!-- INVESTIGATION ACTIONS -->
    <div class="card dashboard-card mb-4" id="investigationActions">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-search me-1"></i> Investigation Tools
        </div>
        <div class="card-body">
            <div class="small text-muted mb-3">Use these checks to investigate the network. They do not solve the fault automatically.</div>
            <?php
            $investigationActions = [
                ['check_ip_configuration', 'Check IP Configuration', 'bi-pc-display', 'Inspect IP addresses and subnet configuration.'],
                ['check_interface_status', 'Check Interface Status', 'bi-ethernet', 'Check whether interfaces are operational.'],
                ['check_connectivity', 'Test Connectivity', 'bi-broadcast-pin', 'Investigate whether configured network links can communicate.'],
                ['check_routing', 'Check Routing', 'bi-signpost-split', 'Investigate routing-related problems.'],
                ['check_vlan_configuration', 'Check VLAN Configuration', 'bi-diagram-3', 'Investigate switching and VLAN configuration.'],
                ['check_dhcp_configuration', 'Check DHCP Configuration', 'bi-hdd-network', 'Investigate automatic IP assignment.'],
                ['check_wireless_configuration', 'Check Wireless Configuration', 'bi-wifi', 'Investigate wireless configuration.'],
                ['check_device_configuration', 'Check Device Configuration', 'bi-terminal', 'Inspect general device configuration evidence.'],
            ];
            ?>
            <div class="row g-3">
                <?php foreach ($investigationActions as $tool): ?>
                    <div class="col-md-6 col-xl-3">
                        <form method="post" class="h-100 js-workspace-form">
                            <input type="hidden" name="action_type" value="<?= htmlspecialchars($tool[0]) ?>">
                            <button type="submit" class="btn btn-outline-primary text-start w-100 h-100 p-3">
                                <div class="d-flex align-items-start gap-3">
                                    <i class="bi <?= htmlspecialchars($tool[2]) ?> fs-3"></i>
                                    <div>
                                        <div class="fw-bold mb-1"><?= htmlspecialchars($tool[1]) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($tool[3]) ?></div>
                                    </div>
                                </div>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- DIAGNOSIS / CORRECTION / VERIFICATION -->
    <?php if (($attempt['status'] ?? '') !== 'Submitted'): ?>
        <div class="card dashboard-card mb-4" id="diagnosisSection">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-clipboard2-pulse me-1"></i> Diagnosis and Resolution
            </div>
            <div class="card-body">
                <div class="alert alert-light border mb-4">
                    <strong>Resolution Workflow</strong>
                    <div class="small mt-1">Investigate the network → Diagnose the fault → Apply a correction → Verify the solution → Submit the attempt.</div>
                </div>
                <div class="row g-4">
                    <!-- STEP 1 -->
                    <div class="col-lg-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="fw-bold mb-2"><i class="bi bi-search me-1"></i> Step 1 — Diagnose the Fault</div>
                            <p class="small text-muted">Select the problem you believe is affecting this exercise.</p>
                            <?php if (empty($assignedFaults)): ?>
                                <div class="alert alert-warning mb-0">No faults have been assigned to this scenario yet.</div>
                            <?php else: ?>
                                <form method="post" class="js-workspace-form">
                                    <input type="hidden" name="action_type" value="diagnose_fault">
                                    <select name="fault_id" class="form-select mb-3" required>
                                        <option value="">Select the suspected fault...</option>
                                        <?php foreach ($assignedFaults as $fault): ?>
                                            <option value="<?= (int) $fault['fault_id'] ?>">
                                                <?= htmlspecialchars((string) $fault['fault_code']) ?> — <?= htmlspecialchars((string) $fault['fault_title']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i> Submit Diagnosis</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- STEP 2 -->
                    <div class="col-lg-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="fw-bold mb-2"><i class="bi bi-wrench-adjustable-circle me-1"></i> Step 2 — Apply Correction</div>
                            <?php if ($latestDiagnosisCorrect): ?>
                                <div class="alert alert-success py-2 small">
                                    Correct diagnosis: <strong><?= htmlspecialchars($latestDiagnosisTitle) ?></strong>
                                </div>
                                <form method="post" class="js-workspace-form">
                                    <input type="hidden" name="action_type" value="apply_correction">
                                    <select name="correction_code" class="form-select mb-3" required>
                                        <option value="">Select the corrective action...</option>
                                        <option value="correct_ip_configuration">Correct IP address and subnet configuration</option>
                                        <option value="correct_interface_status">Restore the affected interface to the required operational state</option>
                                        <option value="correct_connectivity">Restore the affected network connection</option>
                                        <option value="correct_routing_configuration">Correct the routing configuration</option>
                                        <option value="correct_vlan_configuration">Correct the VLAN or switching configuration</option>
                                        <option value="correct_dhcp_configuration">Correct the DHCP or automatic addressing configuration</option>
                                        <option value="correct_wireless_configuration">Correct the wireless configuration</option>
                                        <option value="correct_device_configuration">Correct the affected device configuration</option>
                                    </select>
                                    <button type="submit" class="btn btn-success"><i class="bi bi-wrench me-1"></i> Apply Correction</button>
                                </form>
                            <?php else: ?>
                                <div class="text-muted small">A correct diagnosis must be recorded before the correction step becomes available.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- STEP 3 -->
                    <div class="col-lg-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="fw-bold mb-2"><i class="bi bi-shield-check me-1"></i> Step 3 — Verify Solution</div>
                            <p class="small text-muted">Verify whether the troubleshooting path has been completed successfully.</p>
                            <form method="post" class="js-workspace-form">
                                <input type="hidden" name="action_type" value="verify_solution">
                                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-check2-circle me-1"></i> Verify Solution</button>
                            </form>
                        </div>
                    </div>

                    <!-- STEP 4 -->
                    <div class="col-lg-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="fw-bold mb-2"><i class="bi bi-send-check me-1"></i> Step 4 — Submit Attempt</div>
                            <?php if ($latestVerificationVerified): ?>
                                <div class="alert alert-success py-2 small">
                                    Solution verified successfully. The attempt is ready for submission.
                                </div>
                                <form method="post" class="js-workspace-form">
                                    <input type="hidden" name="action_type" value="submit_attempt">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-send-check me-1"></i> Submit Troubleshooting Attempt</button>
                                </form>
                            <?php else: ?>
                                <div class="text-muted small">Complete verification successfully before submitting the attempt.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- SUBMITTED RESULT -->
    <?php if (($attempt['status'] ?? '') === 'Submitted'): ?>
        <div class="card dashboard-card mb-4 border-success">
            <div class="card-header bg-success text-white">
                <i class="bi bi-check-circle-fill me-2"></i> Troubleshooting Attempt Submitted
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <h5 class="fw-bold mb-2"><i class="bi bi-patch-check-fill me-1"></i> Attempt Completed Successfully</h5>
                    <p class="mb-0">Your troubleshooting exercise has been submitted and recorded by VNDTES.</p>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 text-center h-100">
                            <div class="text-muted small">SCORE</div>
                            <div class="display-6 fw-bold text-success"><?= $submittedScore ?>%</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 text-center h-100">
                            <div class="text-muted small">STATUS</div>
                            <div class="fs-4 fw-bold text-success mt-2"><?= htmlspecialchars($submittedStatus) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 text-center h-100">
                            <div class="text-muted small">ATTEMPT</div>
                            <div class="fs-4 fw-bold mt-2">#<?= (int) $attempt['attempt_number'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="border rounded-3 p-3 mb-4">
                    <div class="fw-bold mb-2"><i class="bi bi-clipboard-check me-1"></i> Assessment</div>
                    <p class="mb-0 text-muted"><?= htmlspecialchars($submittedReason) ?></p>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <a href="exercises.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-arrow-left me-1"></i> Back to Available Exercises
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="dashboard.php" class="btn btn-primary w-100">
                            <i class="bi bi-speedometer2 me-1"></i> Student Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ACTIVITY LOG -->
    <div class="card dashboard-card mb-4">
        <div class="card-header bg-secondary text-white">
            <i class="bi bi-clock-history me-1"></i> Troubleshooting Activity
        </div>
        <div class="card-body">
            <?php if (empty($activityLog)): ?>
                <div class="text-muted">No troubleshooting actions have been recorded yet. Begin by inspecting a device or interface.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Action</th>
                                <th>Target</th>
                                <th>Result</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activityLog as $activity): ?>
                                <?php
                                $resultText = 'Action recorded.';
                                if (!empty($activity['action_result'])) {
                                    $decodedResult = json_decode((string) $activity['action_result'], true);
                                    if (is_array($decodedResult) && isset($decodedResult['result'])) {
                                        $resultText = (string) $decodedResult['result'];
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) $activity['action_type']) ?></td>
                                    <td>
                                        <?= htmlspecialchars((string) $activity['target_type']) ?>
                                        <?php if ($activity['target_id'] !== null): ?>
                                            #<?= (int) $activity['target_id'] ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($resultText) ?></td>
                                    <td class="text-nowrap"><?= htmlspecialchars((string) $activity['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- INSPECTION RESULT MODAL -->
    <div class="modal fade" id="inspectionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="inspectionModalTitle">Inspection Result</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="inspectionModalBody"></div>
                <div class="modal-footer">
                    <span class="small text-muted me-auto">
                        <i class="bi bi-check-circle text-success"></i> Inspection recorded in the activity history.
                    </span>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PROCESSING MODAL -->
    <div class="modal fade" id="processingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="spinner-border text-primary mb-3"></div>
                    <div class="fw-semibold">Processing troubleshooting action...</div>
                    <div class="small text-muted mt-1">Recording the troubleshooting action.</div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- JAVASCRIPT GLOBAL STATE -->
<script>
window.VNDTES_TROUBLESHOOT = <?= json_encode([
    'message' => $message,
    'messageType' => $messageType,
    'attemptId' => $attemptId,
    'attemptStatus' => $attempt['status'] ?? '',
    'actionResult' => $selectedActionResult,
    'verificationResult' => $verificationResult,
    'topology' => [
        'interfaces' => $scenarioInterfaces,
        'scenarioConnections' => $scenarioConnections,
        'studentConnections' => $studentTopologyConnections,
    ],
    'inspection' => $selectedDevice !== null ? [
        'type' => 'device',
        'title' => 'Device Inspection',
        'icon' => 'bi-router-fill',
        'data' => [
            'Device Name' => $selectedDevice['device_name'],
            'Device Code' => $selectedDevice['device_code'],
            'Device Type' => $selectedDevice['device_type'],
            'Vendor' => $selectedDevice['vendor'] ?: 'Not specified',
            'Model' => $selectedDevice['model'] ?: 'Not specified',
            'Quantity' => (string) $selectedDevice['quantity'],
            'Notes' => $selectedDevice['notes'] ?: 'No additional notes.',
        ],
    ] : ($selectedInterface !== null ? [
        'type' => 'interface',
        'title' => 'Interface Inspection',
        'icon' => 'bi-ethernet',
        'data' => [
            'Device' => $selectedInterface['device_name'],
            'Device Code' => $selectedInterface['device_code'],
            'Interface' => $selectedInterface['interface_name'],
            'Interface Type' => $selectedInterface['interface_type'],
            'Status' => $selectedInterface['interface_status'] ?: 'Unknown',
            'IP Address' => $selectedInterface['ip_address'] ?: 'Not configured',
            'Subnet Mask' => $selectedInterface['subnet_mask'] ?: 'Not configured',
            'MAC Address' => $selectedInterface['mac_address'] ?: 'Not configured',
            'Notes' => $selectedInterface['notes'] ?: 'No additional notes.',
        ],
    ] : ($selectedConnection !== null ? [
        'type' => 'connection',
        'title' => 'Network Connection Inspection',
        'icon' => 'bi-share-fill',
        'data' => [
            'Device A' => $selectedConnection['device_a_name'],
            'Device A Code' => $selectedConnection['device_a_code'],
            'Interface A' => $selectedConnection['interface_a_name'],
            'Connection Type' => $selectedConnection['connection_type'],
            'Link Status' => $selectedConnection['status'],
            'Interface B' => $selectedConnection['interface_b_name'],
            'Device B' => $selectedConnection['device_b_name'],
            'Device B Code' => $selectedConnection['device_b_code'],
            'Notes' => $selectedConnection['notes'] ?: 'No additional notes.',
        ],
    ] : null))
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<script>
window.VNDTES_TOPOLOGY = <?= json_encode($scenarioInterfaces, JSON_UNESCAPED_UNICODE) ?>;
window.VNDTES_TOPOLOGY_REQUIREMENTS = <?= json_encode($scenarioDevices, JSON_UNESCAPED_UNICODE) ?>;
window.VNDTES_TOPOLOGY_CONNECTIONS = <?= json_encode($scenarioConnections, JSON_UNESCAPED_UNICODE) ?>;
window.VNDTES_TOPOLOGY_STUDENT_CONNECTIONS = <?= json_encode($studentTopologyConnections, JSON_UNESCAPED_UNICODE) ?>;
</script>

<script src="../assets/js/topology.js"></script>
<script src="../assets/js/troubleshoot.js"></script>

<?php
require_once '../includes/layout_end.php';