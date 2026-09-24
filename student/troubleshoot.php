<?php

declare(strict_types=1);

require_once '../includes/init.php';
require_once '../includes/auth.php';


/*
|--------------------------------------------------------------------------
| Student Access
|--------------------------------------------------------------------------
*/

if (currentUserRole() !== 'Student') {
    redirect(APP_URL);
}


/*
|--------------------------------------------------------------------------
| Page State
|--------------------------------------------------------------------------
*/

$pageTitle = 'Troubleshooting Workspace';

$attemptId = isset($_GET['attempt_id'])
    ? (int) $_GET['attempt_id']
    : 0;

$scenarioId = isset($_GET['scenario_id'])
    ? (int) $_GET['scenario_id']
    : 0;

$studentUserId = currentUserId();

$studentId = 0;

$attempt = null;
$scenario = null;

$scenarioDevices = [];
$scenarioInterfaces = [];
$scenarioConnections = [];
$activityLog = [];

$selectedDevice = null;
$selectedInterface = null;
$selectedConnection = null;
$selectedActionResult = null;
$selectedDiagnosis = null;
$verificationResult = null;
$targetFault = null;
$assignedFaults = [];

$message = null;
$messageType = 'info';


/*
|--------------------------------------------------------------------------
| Resolve Logged-in User to Student
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT
        student_id,
        matric_no
    FROM students
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([
    $studentUserId
]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {

    require_once '../includes/layout_start.php';
    ?>

    <div class="container-fluid">

        <div class="card dashboard-card">

            <div class="card-body text-center py-5">

                <i
                    class="bi bi-person-x text-danger"
                    style="font-size:60px;"
                ></i>

                <h3 class="fw-bold mt-3">
                    Student Record Not Found
                </h3>

                <p class="text-muted">
                    Your user account is not currently linked to a student record.
                </p>

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
| Create / Resume Attempt When scenario_id Is Supplied
|--------------------------------------------------------------------------
|
| This makes the workspace usable directly with:
|
| troubleshoot.php?scenario_id=8
|
| If an unfinished attempt exists, it is resumed.
| Otherwise a new attempt is created.
|
*/

if ($attemptId <= 0 && $scenarioId > 0) {

    /*
    |----------------------------------------------------------------------
    | Verify Published Scenario
    |----------------------------------------------------------------------
    */

    $stmt = db()->prepare("
        SELECT scenario_id
        FROM scenarios
        WHERE scenario_id = ?
          AND status = 'Published'
        LIMIT 1
    ");

    $stmt->execute([
        $scenarioId
    ]);

    if (!$stmt->fetchColumn()) {

        redirect('exercises.php');
    }


    /*
    |----------------------------------------------------------------------
    | Look for Existing In-Progress Attempt
    |----------------------------------------------------------------------
    */

    $stmt = db()->prepare("
        SELECT attempt_id
        FROM scenario_attempts
        WHERE student_id = ?
          AND scenario_id = ?
          AND status = 'In Progress'
        ORDER BY attempt_id DESC
        LIMIT 1
    ");

    $stmt->execute([
        $studentId,
        $scenarioId
    ]);

    $existingAttemptId = (int) $stmt->fetchColumn();


    if ($existingAttemptId > 0) {

        $attemptId = $existingAttemptId;

    } else {

        /*
        |------------------------------------------------------------------
        | Determine Next Attempt Number
        |------------------------------------------------------------------
        */

        $stmt = db()->prepare("
            SELECT COALESCE(
                MAX(attempt_number),
                0
            ) + 1

            FROM scenario_attempts

            WHERE student_id = ?
              AND scenario_id = ?
        ");

        $stmt->execute([
            $studentId,
            $scenarioId
        ]);

        $attemptNumber = (int) $stmt->fetchColumn();


        /*
        |------------------------------------------------------------------
        | Create Attempt
        |------------------------------------------------------------------
        */

        $stmt = db()->prepare("
            INSERT INTO scenario_attempts
            (
                student_id,
                scenario_id,
                attempt_number,
                status,
                started_at,
                last_activity_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                'In Progress',
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            $studentId,
            $scenarioId,
            $attemptNumber
        ]);

        $attemptId = (int) db()->lastInsertId();
    }


    /*
    |----------------------------------------------------------------------
    | Redirect to Canonical Attempt URL
    |----------------------------------------------------------------------
    */

    redirect(
        'troubleshoot.php?attempt_id=' . $attemptId
    );
}


/*
|--------------------------------------------------------------------------
| Validate Attempt ID
|--------------------------------------------------------------------------
*/

if ($attemptId <= 0) {

    require_once '../includes/layout_start.php';
    ?>

    <div class="container-fluid">

        <div class="card dashboard-card">

            <div class="card-body text-center py-5">

                <i
                    class="bi bi-exclamation-triangle text-warning"
                    style="font-size:60px;"
                ></i>

                <h3 class="fw-bold mt-3">
                    Troubleshooting Attempt Not Found
                </h3>

                <p class="text-muted">
                    Please return to the available exercises and start an exercise.
                </p>

                <a
                    href="exercises.php"
                    class="btn btn-primary"
                >

                    <i class="bi bi-arrow-left"></i>

                    Back to Exercises

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
| Load Attempt
|--------------------------------------------------------------------------
|
| IMPORTANT:
| The student_id check prevents one student from opening
| another student's attempt.
|
*/

$stmt = db()->prepare("
    SELECT
        sa.attempt_id,
        sa.student_id,
        sa.scenario_id,
        sa.attempt_number,
        sa.status,
        sa.started_at,
        sa.submitted_at,
        sa.completed_at,
        sa.last_activity_at,

        s.scenario_code,
        s.scenario_title,
        s.category,
        s.difficulty,
        s.estimated_time,
        s.instructions,
        s.expected_outcome

    FROM scenario_attempts sa

    INNER JOIN scenarios s
        ON s.scenario_id = sa.scenario_id

    WHERE sa.attempt_id = ?
      AND sa.student_id = ?

    LIMIT 1
");

$stmt->execute([
    $attemptId,
    $studentId
]);

$attempt = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Attempt Not Found / Unauthorized
|--------------------------------------------------------------------------
*/

if (!$attempt) {

    require_once '../includes/layout_start.php';
    ?>

    <div class="container-fluid">

        <div class="card dashboard-card">

            <div class="card-body text-center py-5">

                <i
                    class="bi bi-shield-exclamation text-danger"
                    style="font-size:60px;"
                ></i>

                <h3 class="fw-bold mt-3">
                    Attempt Not Available
                </h3>

                <p class="text-muted">
                    This troubleshooting attempt could not be found
                    or does not belong to your student account.
                </p>

                <a
                    href="exercises.php"
                    class="btn btn-primary"
                >

                    <i class="bi bi-arrow-left"></i>

                    Back to Exercises

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
| Set Scenario
|--------------------------------------------------------------------------
*/

$scenarioId = (int) $attempt['scenario_id'];

$scenario = $attempt;


/*
|--------------------------------------------------------------------------
| Initialise Hidden Fault Target
|--------------------------------------------------------------------------
|
| Each attempt receives one mapped fault as its hidden troubleshooting
| target. The target is stored as a SYSTEM action and never displayed in
| the student's activity history.
|
*/

$stmt = db()->prepare("
    SELECT action_data
    FROM scenario_attempt_actions
    WHERE attempt_id = ?
      AND action_type = 'SYSTEM_FAULT_TARGET'
    ORDER BY action_id DESC
    LIMIT 1
");

$stmt->execute([$attemptId]);
$targetRecord = $stmt->fetchColumn();

if ($targetRecord) {
    $targetData = json_decode((string) $targetRecord, true);
    $targetFaultId = (int) ($targetData['fault_id'] ?? 0);
} else {
    $targetFaultId = 0;

    $stmt = db()->prepare("
        SELECT sf.fault_id
        FROM scenario_faults sf
        INNER JOIN faults f
            ON f.fault_id = sf.fault_id
        WHERE sf.scenario_id = ?
          AND sf.is_active = 1
          AND f.status = 'Active'
        ORDER BY RAND()
        LIMIT 1
    ");

    $stmt->execute([$scenarioId]);
    $targetFaultId = (int) $stmt->fetchColumn();

    if ($targetFaultId > 0) {
        $stmt = db()->prepare("
            INSERT INTO scenario_attempt_actions
            (
                attempt_id,
                action_type,
                target_type,
                target_id,
                action_data,
                action_result
            )
            VALUES (?, 'SYSTEM_FAULT_TARGET', 'system', ?, ?, ?)
        ");

        $targetData = json_encode([
            'fault_id' => $targetFaultId
        ], JSON_UNESCAPED_UNICODE);

        $targetResult = json_encode([
            'result' => 'Hidden fault target initialised.'
        ], JSON_UNESCAPED_UNICODE);

        $stmt->execute([
            $attemptId,
            $targetFaultId,
            $targetData,
            $targetResult
        ]);
    }
}

if ($targetFaultId > 0) {
    $stmt = db()->prepare("
        SELECT
            f.fault_id,
            f.fault_code,
            f.fault_title,
            f.category,
            f.difficulty,
            f.affected_device_type,
            f.symptoms,
            f.probable_cause,
            f.expected_solution
        FROM faults f
        WHERE f.fault_id = ?
          AND f.status = 'Active'
        LIMIT 1
    ");

    $stmt->execute([$targetFaultId]);
    $targetFault = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}


/*
|--------------------------------------------------------------------------
| Handle Student Actions
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($attempt['status'] ?? '') === 'In Progress'
) {

    $actionType = trim((string) ($_POST['action_type'] ?? ''));
    $targetType = trim((string) ($_POST['target_type'] ?? ''));
    $targetId = (int) ($_POST['target_id'] ?? 0);
    $correctionCode = trim((string) ($_POST['correction_code'] ?? ''));

    $allowedActions = [
        'inspect_device',
        'inspect_interface',
        'inspect_connection',
        'check_ip_configuration',
        'check_interface_status',
        'check_connectivity',
        'check_routing',
        'check_vlan_configuration',
        'check_dhcp_configuration',
        'check_wireless_configuration',
        'check_device_configuration',
        'diagnose_fault',
        'apply_correction',
        'verify_solution',
        'submit_attempt'
    ];

    if (!in_array($actionType, $allowedActions, true)) {
        $message = 'Invalid troubleshooting action.';
        $messageType = 'danger';
    } else {

        /* ---------------------------------------------------------------
           Helper: record an action
        ---------------------------------------------------------------- */
        $recordAction = function (
            string $type,
            string $target,
            ?int $id,
            array $data,
            array $result
        ) use ($attemptId): void {
            $stmt = db()->prepare("
                INSERT INTO scenario_attempt_actions
                (
                    attempt_id,
                    action_type,
                    target_type,
                    target_id,
                    action_data,
                    action_result
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $attemptId,
                $type,
                $target,
                $id,
                json_encode($data, JSON_UNESCAPED_UNICODE),
                json_encode($result, JSON_UNESCAPED_UNICODE)
            ]);
        };

        /* ---------------------------------------------------------------
           Existing inspection actions
        ---------------------------------------------------------------- */
        if ($actionType === 'inspect_device') {
            $stmt = db()->prepare("
                SELECT
                    sd.scenario_device_id,
                    sd.quantity,
                    sd.notes,
                    d.device_id,
                    d.device_code,
                    d.device_name,
                    d.device_type,
                    d.vendor,
                    d.model
                FROM scenario_devices sd
                INNER JOIN devices d ON d.device_id = sd.device_id
                WHERE sd.scenario_device_id = ?
                  AND sd.scenario_id = ?
                LIMIT 1
            ");
            $stmt->execute([$targetId, $scenarioId]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$device) {
                $message = 'The selected device could not be found.';
                $messageType = 'danger';
            } else {
                $recordAction(
                    'INSPECT_DEVICE',
                    'scenario_device',
                    $targetId,
                    [
                        'device_code' => $device['device_code'],
                        'device_name' => $device['device_name'],
                        'device_type' => $device['device_type']
                    ],
                    ['result' => 'Device information inspected.']
                );

                $selectedDevice = $device;
                $message = 'Device information loaded successfully.';
                $messageType = 'success';
            }
        }

        if ($actionType === 'inspect_interface') {
            $stmt = db()->prepare("
                SELECT
                    sdi.interface_id,
                    sdi.instance_id,
                    sdi.interface_name,
                    sdi.interface_type,
                    sdi.ip_address,
                    sdi.subnet_mask,
                    sdi.mac_address,
                    sdi.interface_status,
                    sdi.notes,
                    sdi_instance.scenario_device_id,
                    d.device_id,
                    d.device_code,
                    d.device_name,
                    d.device_type
                FROM scenario_device_interfaces sdi
                INNER JOIN scenario_device_instances sdi_instance
                    ON sdi_instance.instance_id = sdi.instance_id
                INNER JOIN scenario_devices sd
                    ON sd.scenario_device_id = sdi_instance.scenario_device_id
                INNER JOIN devices d ON d.device_id = sd.device_id
                WHERE sdi.interface_id = ?
                  AND sd.scenario_id = ?
                LIMIT 1
            ");
            $stmt->execute([$targetId, $scenarioId]);
            $interface = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$interface) {
                $message = 'The selected interface could not be found.';
                $messageType = 'danger';
            } else {
                $recordAction(
                    'INSPECT_INTERFACE',
                    'interface',
                    $targetId,
                    [
                        'interface_id' => $interface['interface_id'],
                        'interface_name' => $interface['interface_name'],
                        'device_id' => $interface['device_id']
                    ],
                    ['result' => 'Interface information inspected.']
                );

                $selectedInterface = $interface;
                $message = 'Interface information loaded successfully.';
                $messageType = 'success';
            }
        }

        if ($actionType === 'inspect_connection') {
            $stmt = db()->prepare("
                SELECT
                    sc.connection_id,
                    sc.connection_type,
                    sc.status,
                    sc.notes,
                    ia.interface_id AS interface_a_id,
                    ia.interface_name AS interface_a_name,
                    ib.interface_id AS interface_b_id,
                    ib.interface_name AS interface_b_name,
                    da.device_name AS device_a_name,
                    da.device_code AS device_a_code,
                    db.device_name AS device_b_name,
                    db.device_code AS device_b_code
                FROM scenario_connections sc
                INNER JOIN scenario_device_interfaces ia
                    ON ia.interface_id = sc.interface_a_id
                INNER JOIN scenario_device_instances inst_a
                    ON inst_a.instance_id = ia.instance_id
                INNER JOIN scenario_devices sd_a
                    ON sd_a.scenario_device_id = inst_a.scenario_device_id
                INNER JOIN devices da ON da.device_id = sd_a.device_id
                INNER JOIN scenario_device_interfaces ib
                    ON ib.interface_id = sc.interface_b_id
                INNER JOIN scenario_device_instances inst_b
                    ON inst_b.instance_id = ib.instance_id
                INNER JOIN scenario_devices sd_b
                    ON sd_b.scenario_device_id = inst_b.scenario_device_id
                INNER JOIN devices db ON db.device_id = sd_b.device_id
                WHERE sc.connection_id = ?
                  AND sd_a.scenario_id = ?
                  AND sd_b.scenario_id = ?
                LIMIT 1
            ");
            $stmt->execute([$targetId, $scenarioId, $scenarioId]);
            $connection = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$connection) {
                $message = 'The selected network connection could not be found.';
                $messageType = 'danger';
            } else {
                $recordAction(
                    'INSPECT_CONNECTION',
                    'connection',
                    $targetId,
                    [
                        'connection_id' => $connection['connection_id'],
                        'connection_type' => $connection['connection_type']
                    ],
                    ['result' => 'Network connection inspected.']
                );

                $selectedConnection = $connection;
                $message = 'Network connection information inspected successfully.';
                $messageType = 'success';
            }
        }

        /* ---------------------------------------------------------------
           Investigation checks
        ---------------------------------------------------------------- */
        if ($actionType === 'check_ip_configuration') {
            $stmt = db()->prepare("
                SELECT
                    sdi.interface_name,
                    d.device_name,
                    sdi.ip_address,
                    sdi.subnet_mask
                FROM scenario_device_interfaces sdi
                INNER JOIN scenario_device_instances sdi_instance
                    ON sdi_instance.instance_id = sdi.instance_id
                INNER JOIN scenario_devices sd
                    ON sd.scenario_device_id = sdi_instance.scenario_device_id
                INNER JOIN devices d ON d.device_id = sd.device_id
                WHERE sd.scenario_id = ?
                ORDER BY d.device_name, sdi.interface_id
            ");
            $stmt->execute([$scenarioId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $findings = [];
            foreach ($rows as $row) {
                $findings[] = sprintf(
                    '%s / %s: IP %s, subnet %s.',
                    $row['device_name'],
                    $row['interface_name'],
                    $row['ip_address'] ?: 'not configured',
                    $row['subnet_mask'] ?: 'not configured'
                );
            }

            $selectedActionResult = [
                'title' => 'IP Configuration Check',
                'icon' => 'bi-pc-display',
                'result' => empty($findings)
                    ? 'No interface addressing information is available.'
                    : 'The current interface addressing information has been checked.',
                'findings' => $findings,
                'next' => 'Compare the addresses and subnet masks with the scenario requirements before choosing a diagnosis.'
            ];

            $recordAction(
                'CHECK_IP_CONFIGURATION',
                'scenario',
                $scenarioId,
                [],
                $selectedActionResult
            );
            $message = 'IP configuration check completed.';
            $messageType = 'success';
        }

        if ($actionType === 'check_interface_status') {
            $stmt = db()->prepare("
                SELECT
                    sdi.interface_name,
                    d.device_name,
                    sdi.interface_status
                FROM scenario_device_interfaces sdi
                INNER JOIN scenario_device_instances sdi_instance
                    ON sdi_instance.instance_id = sdi.instance_id
                INNER JOIN scenario_devices sd
                    ON sd.scenario_device_id = sdi_instance.scenario_device_id
                INNER JOIN devices d ON d.device_id = sd.device_id
                WHERE sd.scenario_id = ?
                ORDER BY d.device_name, sdi.interface_id
            ");
            $stmt->execute([$scenarioId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $findings = [];
            foreach ($rows as $row) {
                $findings[] = sprintf(
                    '%s / %s: interface status is %s.',
                    $row['device_name'],
                    $row['interface_name'],
                    $row['interface_status'] ?: 'unknown'
                );
            }

            $selectedActionResult = [
                'title' => 'Interface Status Check',
                'icon' => 'bi-ethernet',
                'result' => empty($findings)
                    ? 'No interface status information is available.'
                    : 'Interface operational states have been checked.',
                'findings' => $findings,
                'next' => 'Look for an interface that is Down or Administratively Down and compare it with the fault symptoms.'
            ];

            $recordAction('CHECK_INTERFACE_STATUS', 'scenario', $scenarioId, [], $selectedActionResult);
            $message = 'Interface status check completed.';
            $messageType = 'success';
        }

        if ($actionType === 'check_connectivity') {
            $stmt = db()->prepare("
                SELECT
                    sc.connection_type,
                    sc.status AS connection_status,
                    ia.interface_name AS interface_a,
                    ib.interface_name AS interface_b,
                    da.device_name AS device_a,
                    db.device_name AS device_b,
                    ia.interface_status AS interface_a_status,
                    ib.interface_status AS interface_b_status
                FROM scenario_connections sc
                INNER JOIN scenario_device_interfaces ia ON ia.interface_id = sc.interface_a_id
                INNER JOIN scenario_device_instances inst_a ON inst_a.instance_id = ia.instance_id
                INNER JOIN scenario_devices sd_a ON sd_a.scenario_device_id = inst_a.scenario_device_id
                INNER JOIN devices da ON da.device_id = sd_a.device_id
                INNER JOIN scenario_device_interfaces ib ON ib.interface_id = sc.interface_b_id
                INNER JOIN scenario_device_instances inst_b ON inst_b.instance_id = ib.instance_id
                INNER JOIN scenario_devices sd_b ON sd_b.scenario_device_id = inst_b.scenario_device_id
                INNER JOIN devices db ON db.device_id = sd_b.device_id
                WHERE sd_a.scenario_id = ?
                  AND sd_b.scenario_id = ?
                ORDER BY sc.connection_id
            ");
            $stmt->execute([$scenarioId, $scenarioId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $findings = [];
            foreach ($rows as $row) {
                $link = ($row['connection_status'] === 'Active'
                    && $row['interface_a_status'] === 'Up'
                    && $row['interface_b_status'] === 'Up')
                    ? 'link appears operational'
                    : 'link requires attention';

                $findings[] = sprintf(
                    '%s ↔ %s: %s (%s).',
                    $row['device_a'],
                    $row['device_b'],
                    $link,
                    $row['connection_type']
                );
            }

            $selectedActionResult = [
                'title' => 'Connectivity Test',
                'icon' => 'bi-broadcast-pin',
                'result' => empty($findings)
                    ? 'No network connections are available for testing.'
                    : 'The configured links and endpoint interface states have been checked.',
                'findings' => $findings,
                'next' => 'A working link does not prove that the whole network is correctly configured. Continue with addressing or configuration checks.'
            ];

            $recordAction('CHECK_CONNECTIVITY', 'scenario', $scenarioId, [], $selectedActionResult);
            $message = 'Connectivity test completed.';
            $messageType = 'success';
        }

        $genericChecks = [
            'check_routing' => [
                'title' => 'Routing Check',
                'icon' => 'bi-signpost-split',
                'result' => 'Routing is an investigation step for determining whether traffic has a valid path between networks.',
                'findings' => [
                    'The current VNDTES data model does not store a separate routing-table state for the student to inspect directly.',
                    'Use the topology, interface addressing and scenario fault symptoms to determine whether routing is involved.'
                ],
                'next' => 'If the scenario concerns different IP networks, inspect the relevant router interfaces and routing-related fault descriptions.'
            ],
            'check_vlan_configuration' => [
                'title' => 'VLAN Configuration Check',
                'icon' => 'bi-diagram-3',
                'result' => 'VLAN checking is used to determine whether switching segmentation is contributing to the connectivity problem.',
                'findings' => [
                    'The current VNDTES data model does not store a separate VLAN runtime configuration table.',
                    'Use the scenario topology and switching-related fault information as the evidence for this check.'
                ],
                'next' => 'For VLAN scenarios, inspect the switch-related information and compare it with the stated exercise requirements.'
            ],
            'check_dhcp_configuration' => [
                'title' => 'DHCP Configuration Check',
                'icon' => 'bi-hdd-network',
                'result' => 'DHCP checking is used to determine whether automatic address assignment is contributing to the problem.',
                'findings' => [
                    'The current VNDTES data model does not store a separate DHCP service runtime table.',
                    'Use interface addressing information and the scenario fault symptoms as evidence.'
                ],
                'next' => 'For a DHCP scenario, compare the observed addressing information with the expected automatic-assignment behaviour.'
            ],
            'check_wireless_configuration' => [
                'title' => 'Wireless Configuration Check',
                'icon' => 'bi-wifi',
                'result' => 'Wireless checking is used to determine whether the wireless portion of the topology is contributing to the fault.',
                'findings' => [
                    'The current VNDTES data model does not store a separate wireless runtime configuration table.',
                    'Use the wireless devices, topology and fault symptoms as evidence.'
                ],
                'next' => 'For a wireless scenario, inspect the access point or wireless router and compare the observed topology with the exercise requirements.'
            ],
            'check_device_configuration' => [
                'title' => 'Device Configuration Check',
                'icon' => 'bi-terminal',
                'result' => 'Device configuration checking is used to determine whether the device itself is contributing to the fault.',
                'findings' => [
                    'Inspect the device record and its connected interfaces before selecting a diagnosis.',
                    'Use the fault symptoms and affected device type to narrow down the likely problem.'
                ],
                'next' => 'After gathering enough evidence, move to Diagnosis and select the fault you believe is present.'
            ]
        ];

        if (isset($genericChecks[$actionType])) {
            $selectedActionResult = $genericChecks[$actionType];
            $recordAction(strtoupper($actionType), 'scenario', $scenarioId, [], $selectedActionResult);
            $message = $selectedActionResult['title'] . ' completed.';
            $messageType = 'success';
        }

        /* ---------------------------------------------------------------
           Diagnosis
        ---------------------------------------------------------------- */
        if ($actionType === 'diagnose_fault') {
            $diagnosisFaultId = (int) ($_POST['fault_id'] ?? 0);

            $stmt = db()->prepare("
                SELECT
                    f.fault_id,
                    f.fault_code,
                    f.fault_title,
                    f.category,
                    f.difficulty,
                    f.affected_device_type
                FROM scenario_faults sf
                INNER JOIN faults f ON f.fault_id = sf.fault_id
                WHERE sf.scenario_id = ?
                  AND sf.fault_id = ?
                  AND sf.is_active = 1
                  AND f.status = 'Active'
                LIMIT 1
            ");
            $stmt->execute([$scenarioId, $diagnosisFaultId]);
            $diagnosisFault = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$diagnosisFault) {
                $message = 'Please select a valid fault from this scenario.';
                $messageType = 'danger';
            } else {
                $isCorrect = $targetFault
                    && (int) $targetFault['fault_id'] === $diagnosisFaultId;

                $diagnosisResult = [
                    'result' => $isCorrect
                        ? 'Diagnosis accepted. The selected fault matches the simulated problem.'
                        : 'Diagnosis not accepted. The selected fault does not match the simulated problem. Review your investigation findings and try again.',
                    'correct' => $isCorrect,
                    'fault_code' => $diagnosisFault['fault_code'],
                    'fault_title' => $diagnosisFault['fault_title']
                ];

                $recordAction(
                    'DIAGNOSE_FAULT',
                    'fault',
                    $diagnosisFaultId,
                    [
                        'fault_code' => $diagnosisFault['fault_code'],
                        'fault_title' => $diagnosisFault['fault_title']
                    ],
                    $diagnosisResult
                );

                $selectedDiagnosis = [
                    'fault_id' => $diagnosisFaultId,
                    'fault_code' => $diagnosisFault['fault_code'],
                    'fault_title' => $diagnosisFault['fault_title'],
                    'correct' => $isCorrect
                ];

                $selectedActionResult = [
                    'title' => $isCorrect ? 'Diagnosis Accepted' : 'Diagnosis Needs Review',
                    'icon' => $isCorrect ? 'bi-check-circle' : 'bi-arrow-repeat',
                    'result' => $diagnosisResult['result'],
                    'findings' => [
                        'Selected fault: ' . $diagnosisFault['fault_code'] . ' — ' . $diagnosisFault['fault_title']
                    ],
                    'next' => $isCorrect
                        ? 'Proceed to Apply Correction, then run Verify Solution.'
                        : 'Return to the investigation checks, review the evidence, and select another diagnosis.'
                ];

                $message = $isCorrect
                    ? 'Correct diagnosis. You can now apply the correction.'
                    : 'Diagnosis needs review.';
                $messageType = $isCorrect ? 'success' : 'warning';
            }
        }

        /* ---------------------------------------------------------------
           Correction
        ---------------------------------------------------------------- */
        if ($actionType === 'apply_correction') {
            $stmt = db()->prepare("
                SELECT action_result
                FROM scenario_attempt_actions
                WHERE attempt_id = ?
                  AND action_type = 'DIAGNOSE_FAULT'
                ORDER BY action_id DESC
                LIMIT 1
            ");
            $stmt->execute([$attemptId]);
            $diagnosisRecord = $stmt->fetchColumn();
            $diagnosisData = $diagnosisRecord
                ? json_decode((string) $diagnosisRecord, true)
                : null;

            if (empty($diagnosisData['correct'])) {
                $message = 'A correct diagnosis is required before applying a correction.';
                $messageType = 'warning';
            } else {
                $validCodes = [
                    'correct_ip_configuration',
                    'correct_interface_status',
                    'correct_connectivity',
                    'correct_routing_configuration',
                    'correct_vlan_configuration',
                    'correct_dhcp_configuration',
                    'correct_wireless_configuration',
                    'correct_device_configuration'
                ];

                if (!in_array($correctionCode, $validCodes, true)) {
                    $message = 'Please select a valid corrective action.';
                    $messageType = 'danger';
                } else {
                    $correctionLabels = [
                        'correct_ip_configuration' => 'Correct IP address and subnet configuration',
                        'correct_interface_status' => 'Restore the affected interface to the required operational state',
                        'correct_connectivity' => 'Restore the affected network connection',
                        'correct_routing_configuration' => 'Correct the routing configuration',
                        'correct_vlan_configuration' => 'Correct the VLAN or switching configuration',
                        'correct_dhcp_configuration' => 'Correct the DHCP or automatic addressing configuration',
                        'correct_wireless_configuration' => 'Correct the wireless configuration',
                        'correct_device_configuration' => 'Correct the affected device configuration'
                    ];

                    $selectedActionResult = [
                        'title' => 'Correction Applied',
                        'icon' => 'bi-wrench-adjustable-circle',
                        'result' => 'The selected correction has been recorded as an action in the simulated troubleshooting environment.',
                        'findings' => [
                            $correctionLabels[$correctionCode]
                        ],
                        'next' => 'Run Verify Solution to test whether the diagnosed problem has been resolved.'
                    ];

                    $recordAction(
                        'APPLY_CORRECTION',
                        'fault',
                        $targetFaultId > 0 ? $targetFaultId : null,
                        [
                            'correction_code' => $correctionCode,
                            'correction_label' => $correctionLabels[$correctionCode]
                        ],
                        $selectedActionResult
                    );

                    $message = 'Correction recorded. Verify the solution now.';
                    $messageType = 'success';
                }
            }
        }

        /* ---------------------------------------------------------------
           Verification
        ---------------------------------------------------------------- */
        if ($actionType === 'verify_solution') {
            $stmt = db()->prepare("
                SELECT action_id, action_result
                FROM scenario_attempt_actions
                WHERE attempt_id = ?
                  AND action_type = 'DIAGNOSE_FAULT'
                ORDER BY action_id DESC
                LIMIT 1
            ");
            $stmt->execute([$attemptId]);
            $diagnosisRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            $diagnosisData = $diagnosisRecord
                ? json_decode((string) $diagnosisRecord['action_result'], true)
                : null;

            $stmt = db()->prepare("
                SELECT action_id, action_result
                FROM scenario_attempt_actions
                WHERE attempt_id = ?
                  AND action_type = 'APPLY_CORRECTION'
                ORDER BY action_id DESC
                LIMIT 1
            ");
            $stmt->execute([$attemptId]);
            $correctionRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            $correctionData = $correctionRecord
                ? json_decode((string) $correctionRecord['action_result'], true)
                : null;

            $verified = !empty($diagnosisData['correct']) && $correctionRecord;

            $verificationResult = [
                'verified' => $verified,
                'title' => $verified ? 'Solution Verified' : 'Verification Failed',
                'result' => $verified
                    ? 'The diagnosis and corrective action completed the rule-based troubleshooting path for this attempt.'
                    : 'The problem has not been verified as resolved. A correct diagnosis and recorded corrective action are required.',
                'next' => $verified
                    ? 'You can now submit the troubleshooting attempt.'
                    : 'Return to Diagnosis, correct the diagnosis if necessary, apply a correction, and verify again.'
            ];

            $recordAction(
                'VERIFY_SOLUTION',
                'scenario',
                $scenarioId,
                [],
                $verificationResult
            );

            $selectedActionResult = [
                'title' => $verificationResult['title'],
                'icon' => $verified ? 'bi-check2-circle' : 'bi-x-circle',
                'result' => $verificationResult['result'],
                'findings' => [
                    $verified
                        ? 'Correct diagnosis recorded.'
                        : 'A correct diagnosis and corrective action were not both confirmed.'
                ],
                'next' => $verificationResult['next']
            ];

            $message = $verified
                ? 'Solution verified. Submit the attempt.'
                : 'Verification did not confirm a resolved fault.';
            $messageType = $verified ? 'success' : 'warning';
        }

        /* ---------------------------------------------------------------
           Submission
        ---------------------------------------------------------------- */
        if ($actionType === 'submit_attempt') {
            $stmt = db()->prepare("
                SELECT action_result
                FROM scenario_attempt_actions
                WHERE attempt_id = ?
                  AND action_type = 'VERIFY_SOLUTION'
                ORDER BY action_id DESC
                LIMIT 1
            ");
            $stmt->execute([$attemptId]);
            $verificationRecord = $stmt->fetchColumn();
            $verificationData = $verificationRecord
                ? json_decode((string) $verificationRecord, true)
                : null;

            if (empty($verificationData['verified'])) {
                $message = 'Verify the solution successfully before submitting the attempt.';
                $messageType = 'warning';
            } else {
                $assessment = [
                    'score' => 100,
                    'status' => 'Passed',
                    'reason' => 'The student identified the simulated fault, applied a corrective action and verified the solution.'
                ];

                $recordAction(
                    'SUBMIT_ATTEMPT',
                    'attempt',
                    $attemptId,
                    [],
                    $assessment
                );

                $stmt = db()->prepare("
                    UPDATE scenario_attempts
                    SET
                        status = 'Submitted',
                        submitted_at = NOW(),
                        completed_at = NOW(),
                        last_activity_at = NOW()
                    WHERE attempt_id = ?
                      AND student_id = ?
                ");
                $stmt->execute([$attemptId, $studentId]);

                $attempt['status'] = 'Submitted';

                $message = 'Troubleshooting attempt submitted successfully.';
                $messageType = 'success';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load Scenario Devices
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT

        sd.scenario_device_id,
        sd.quantity,
        sd.notes,

        d.device_id,
        d.device_code,
        d.device_name,
        d.device_type,
        d.vendor,
        d.model

    FROM scenario_devices sd

    INNER JOIN devices d
        ON d.device_id = sd.device_id

    WHERE sd.scenario_id = ?

    ORDER BY
        d.device_type ASC,
        d.device_name ASC
");

$stmt->execute([
    $scenarioId
]);

$scenarioDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load Scenario Interfaces
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT

        sdi.interface_id,
        sdi.instance_id,
        sdi.interface_name,
        sdi.interface_type,
        sdi.ip_address,
        sdi.subnet_mask,
        sdi.mac_address,
        sdi.interface_status,
        sdi.notes,

        sdi_instance.scenario_device_id,

        d.device_id,
        d.device_code,
        d.device_name,
        d.device_type

    FROM scenario_device_interfaces sdi

    INNER JOIN scenario_device_instances sdi_instance
        ON sdi_instance.instance_id = sdi.instance_id

    INNER JOIN scenario_devices sd
        ON sd.scenario_device_id =
           sdi_instance.scenario_device_id

    INNER JOIN devices d
        ON d.device_id = sd.device_id

    WHERE sd.scenario_id = ?

    ORDER BY
        d.device_name ASC,
        sdi.interface_id ASC
");

$stmt->execute([
    $scenarioId
]);

$scenarioInterfaces = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load Scenario Connections
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT

        sc.connection_id,
        sc.connection_type,
        sc.status,
        sc.notes,

        ia.interface_id AS interface_a_id,
        ia.interface_name AS interface_a_name,

        ib.interface_id AS interface_b_id,
        ib.interface_name AS interface_b_name,

        da.device_name AS device_a_name,
        da.device_code AS device_a_code,

        db.device_name AS device_b_name,
        db.device_code AS device_b_code

    FROM scenario_connections sc

    INNER JOIN scenario_device_interfaces ia
        ON ia.interface_id = sc.interface_a_id

    INNER JOIN scenario_device_instances inst_a
        ON inst_a.instance_id = ia.instance_id

    INNER JOIN scenario_devices sd_a
        ON sd_a.scenario_device_id =
           inst_a.scenario_device_id

    INNER JOIN devices da
        ON da.device_id = sd_a.device_id

    INNER JOIN scenario_device_interfaces ib
        ON ib.interface_id = sc.interface_b_id

    INNER JOIN scenario_device_instances inst_b
        ON inst_b.instance_id = ib.instance_id

    INNER JOIN scenario_devices sd_b
        ON sd_b.scenario_device_id =
           inst_b.scenario_device_id

    INNER JOIN devices db
        ON db.device_id = sd_b.device_id

    WHERE sd_a.scenario_id = ?
      AND sd_b.scenario_id = ?

    ORDER BY
        sc.connection_id ASC
");

$stmt->execute([
    $scenarioId,
    $scenarioId
]);

$scenarioConnections = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load Assigned Faults for Diagnosis
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT
        f.fault_id,
        f.fault_code,
        f.fault_title,
        f.category,
        f.difficulty,
        f.affected_device_type,
        f.symptoms,
        f.estimated_time
    FROM scenario_faults sf
    INNER JOIN faults f ON f.fault_id = sf.fault_id
    WHERE sf.scenario_id = ?
      AND sf.is_active = 1
      AND f.status = 'Active'
    ORDER BY f.category ASC, f.fault_title ASC
");

$stmt->execute([$scenarioId]);
$assignedFaults = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load Student Activity
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT

        action_id,
        action_type,
        target_type,
        target_id,
        action_data,
        action_result,
        created_at

    FROM scenario_attempt_actions

    WHERE attempt_id = ?
      AND action_type <> 'SYSTEM_FAULT_TARGET'

    ORDER BY action_id DESC

    LIMIT 50
");

$stmt->execute([
    $attemptId
]);

$activityLog = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Update Last Activity
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    UPDATE scenario_attempts
    SET last_activity_at = NOW()
    WHERE attempt_id = ?
      AND status = 'In Progress'
");

$stmt->execute([
    $attemptId
]);


/*
|--------------------------------------------------------------------------
| Difficulty Badge
|--------------------------------------------------------------------------
*/

$difficulty = $scenario['difficulty'] ?? 'Beginner';

$difficultyClass = match ($difficulty) {

    'Beginner' =>
        'success',

    'Intermediate' =>
        'warning text-dark',

    'Advanced' =>
        'danger',

    default =>
        'secondary'
};


/*
|--------------------------------------------------------------------------
| Page Layout
|--------------------------------------------------------------------------
*/

require_once '../includes/layout_start.php';

?>

<style>
    .workspace-sticky { position: sticky; top: 1rem; z-index: 5; }
    .workspace-toolbar {
        position: sticky;
        top: 0;
        z-index: 20;
        backdrop-filter: blur(8px);
        background: rgba(255,255,255,.94);
        border-bottom: 1px solid rgba(0,0,0,.08);
    }
    .workspace-stat {
        border: 1px solid rgba(0,0,0,.08);
        border-radius: .85rem;
        padding: .85rem 1rem;
        background: #fff;
        height: 100%;
    }
    .workspace-stat .value { font-size: 1.3rem; font-weight: 700; }
    .device-card, .interface-card, .connection-card, .topology-link {
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .device-card:hover, .interface-card:hover, .connection-card:hover, .topology-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 .45rem 1rem rgba(0,0,0,.07);
    }
    .inspection-item {
        border: 1px solid rgba(0,0,0,.08);
        border-radius: .75rem;
        padding: .85rem;
        height: 100%;
        background: #fff;
    }
    .inspection-label {
        font-size: .72rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: .2rem;
    }
    .inspection-value { font-weight: 600; word-break: break-word; }
    @media (max-width: 1199.98px) {
        .workspace-sticky { position: static; }
    }
</style>

<div class="container-fluid">

    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3">
            <div class="workspace-stat"><div class="small text-muted">Devices</div><div class="value"><?= count($scenarioDevices) ?></div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="workspace-stat"><div class="small text-muted">Interfaces</div><div class="value"><?= count($scenarioInterfaces) ?></div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="workspace-stat"><div class="small text-muted">Connections</div><div class="value"><?= count($scenarioConnections) ?></div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="workspace-stat"><div class="small text-muted">Recorded Actions</div><div class="value"><?= count($activityLog) ?></div></div>
        </div>
    </div>

    <div class="workspace-toolbar py-2 mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong><i class="bi bi-tools me-1"></i> Investigation Tools</strong>
                <span class="small text-muted ms-2">Inspect the network before making a diagnosis.</span>
            </div>
            <a href="exercises.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Exercises
            </a>
        </div>
    </div>


    <!-- ============================================================
         WORKSPACE HEADER
    ============================================================= -->

    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>

            <div class="mb-2">

                <span class="badge bg-primary">

                    <?= htmlspecialchars(
                        $scenario['category']
                    ) ?>

                </span>


                <span class="badge bg-<?= $difficultyClass ?>">

                    <?= htmlspecialchars(
                        $scenario['difficulty']
                    ) ?>

                </span>


                <span class="badge bg-info text-dark">

                    <?= (int) $scenario['estimated_time'] ?>

                    minutes

                </span>

            </div>


            <h2 class="fw-bold mb-1">

                Troubleshooting Workspace

            </h2>


            <div class="text-muted">

                <?= htmlspecialchars(
                    $scenario['scenario_title']
                ) ?>

                —

                <strong>

                    <?= htmlspecialchars(
                        $scenario['scenario_code']
                    ) ?>

                </strong>

            </div>

        </div>


        <div class="text-end">

            <div class="mb-2">

                <span class="badge bg-success fs-6">

                    <?= htmlspecialchars(
                        $attempt['status']
                    ) ?>

                </span>

            </div>


            <div class="small text-muted">

                Attempt #

                <?= (int) $attempt['attempt_number'] ?>

            </div>

        </div>

    </div>



    <!-- ============================================================
         MESSAGE
    ============================================================= -->

    <?php if ($message !== null): ?>
        <div
            id="serverMessage"
            class="d-none"
            data-type="<?= htmlspecialchars($messageType) ?>"
        >
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>



    <!-- ============================================================
         TRAINING NOTICE
    ============================================================= -->

    <div class="alert alert-primary d-flex gap-3 align-items-start mb-4">

        <i class="bi bi-info-circle-fill fs-4"></i>

        <div>

            <strong>
                Troubleshooting Instructions
            </strong>

            <div class="mt-1">

                <?= nl2br(
                    htmlspecialchars(
                        $scenario['instructions'] ?? ''
                    )
                ) ?>

            </div>

        </div>

    </div>



    <!-- ============================================================
         WORKSPACE
    ============================================================= -->

    <div class="row g-4">


        <!-- ========================================================
             LEFT COLUMN
        ========================================================= -->

        <div class="col-xl-4">


            <!-- ====================================================
                 NETWORK DEVICES
            ===================================================== -->

            <div class="card dashboard-card mb-4">

                <div class="card-header bg-success text-white">

                    <i class="bi bi-router-fill"></i>

                    Network Devices

                </div>


                <div class="card-body p-0">


                    <?php if (empty($scenarioDevices)): ?>

                        <div class="p-3 text-muted">

                            No devices are configured for this scenario.

                        </div>

                    <?php else: ?>


                        <div class="list-group list-group-flush">


                            <?php foreach ($scenarioDevices as $device): ?>


                                <div class="list-group-item p-3">


                                    <div
                                        class="d-flex justify-content-between align-items-start"
                                    >

                                        <div>

                                            <div class="fw-bold">

                                                <?= htmlspecialchars(
                                                    $device['device_name']
                                                ) ?>

                                            </div>


                                            <div class="small text-muted">

                                                <?= htmlspecialchars(
                                                    $device['device_code']
                                                ) ?>

                                                ·

                                                <?= htmlspecialchars(
                                                    $device['device_type']
                                                ) ?>

                                            </div>

                                        </div>


                                        <span class="badge bg-secondary">

                                            Qty:

                                            <?= (int) $device['quantity'] ?>

                                        </span>

                                    </div>


                                    <form
                                        method="post"
                                    class="js-inspection-form mt-3"
                                    >

                                        <input
                                            type="hidden"
                                            name="action_type"
                                            value="inspect_device"
                                        >

                                        <input
                                            type="hidden"
                                            name="target_type"
                                            value="scenario_device"
                                        >

                                        <input
                                            type="hidden"
                                            name="target_id"
                                            value="<?= (int) $device['scenario_device_id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-primary"
                                        >

                                            <i class="bi bi-search"></i>

                                            Inspect Device

                                        </button>

                                    </form>

                                </div>


                            <?php endforeach; ?>


                        </div>


                    <?php endif; ?>


                </div>

            </div>



            <!-- ====================================================
                 CONNECTIONS
            ===================================================== -->

            <div class="card dashboard-card mb-4">

                <div class="card-header bg-info text-dark">

                    <i class="bi bi-share-fill"></i>

                    Network Connections

                </div>


                <div class="card-body p-0">


                    <?php if (empty($scenarioConnections)): ?>

                        <div class="p-3 text-muted">

                            No network connections are configured.

                        </div>

                    <?php else: ?>


                        <div class="list-group list-group-flush">


                            <?php foreach ($scenarioConnections as $connection): ?>


                                <div class="list-group-item p-3">


                                    <div class="fw-semibold">

                                        <?= htmlspecialchars(
                                            $connection['device_a_name']
                                        ) ?>

                                        <span class="text-muted">
                                            /
                                        </span>

                                        <?= htmlspecialchars(
                                            $connection['interface_a_name']
                                        ) ?>

                                    </div>


                                    <div class="text-center my-2">

                                        <span class="badge bg-primary">

                                            <?= htmlspecialchars(
                                                $connection['connection_type']
                                            ) ?>

                                        </span>

                                        <i class="bi bi-arrow-down-up mx-2"></i>

                                    </div>


                                    <div class="fw-semibold">

                                        <?= htmlspecialchars(
                                            $connection['device_b_name']
                                        ) ?>

                                        <span class="text-muted">
                                            /
                                        </span>

                                        <?= htmlspecialchars(
                                            $connection['interface_b_name']
                                        ) ?>

                                    </div>


                                    <form
                                        method="post"
                                    class="js-inspection-form mt-3"
                                    >

                                        <input
                                            type="hidden"
                                            name="action_type"
                                            value="inspect_connection"
                                        >

                                        <input
                                            type="hidden"
                                            name="target_type"
                                            value="connection"
                                        >

                                        <input
                                            type="hidden"
                                            name="target_id"
                                            value="<?= (int) $connection['connection_id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-primary"
                                        >

                                            <i class="bi bi-search"></i>

                                            Inspect Connection

                                        </button>

                                    </form>

                                </div>


                            <?php endforeach; ?>


                        </div>


                    <?php endif; ?>


                </div>

            </div>


        </div>



        <!-- ========================================================
             CENTER / RIGHT WORK AREA
        ========================================================= -->

        <div class="col-xl-8">


            <!-- ====================================================
                 NETWORK TOPOLOGY
            ===================================================== -->

            <div class="card dashboard-card mb-4">

                <div class="card-header bg-info text-dark">

                    <i class="bi bi-diagram-3-fill"></i>

                    Network Topology

                </div>


                <div class="card-body">


                    <?php if (empty($scenarioConnections)): ?>

                        <div class="alert alert-light border mb-0">

                            No topology connections are currently configured.

                        </div>

                    <?php else: ?>


                        <div class="table-responsive">

                            <table
                                class="table table-bordered align-middle mb-0"
                            >

                                <thead class="table-light">

                                    <tr>

                                        <th>
                                            Device A
                                        </th>

                                        <th>
                                            Interface
                                        </th>

                                        <th class="text-center">
                                            Link
                                        </th>

                                        <th>
                                            Interface
                                        </th>

                                        <th>
                                            Device B
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>


                                    <?php foreach (
                                        $scenarioConnections
                                        as $connection
                                    ): ?>


                                        <tr>

                                            <td>

                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $connection['device_a_name']
                                                    ) ?>

                                                </strong>

                                                <div class="small text-muted">

                                                    <?= htmlspecialchars(
                                                        $connection['device_a_code']
                                                    ) ?>

                                                </div>

                                            </td>


                                            <td>

                                                <?= htmlspecialchars(
                                                    $connection['interface_a_name']
                                                ) ?>

                                            </td>


                                            <td class="text-center">

                                                <span class="badge bg-primary">

                                                    <?= htmlspecialchars(
                                                        $connection['connection_type']
                                                    ) ?>

                                                </span>

                                            </td>


                                            <td>

                                                <?= htmlspecialchars(
                                                    $connection['interface_b_name']
                                                ) ?>

                                            </td>


                                            <td>

                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $connection['device_b_name']
                                                    ) ?>

                                                </strong>

                                                <div class="small text-muted">

                                                    <?= htmlspecialchars(
                                                        $connection['device_b_code']
                                                    ) ?>

                                                </div>

                                            </td>

                                        </tr>


                                    <?php endforeach; ?>


                                </tbody>

                            </table>

                        </div>


                    <?php endif; ?>


                </div>

            </div>



            <!-- ====================================================
                 INTERFACE INSPECTION
            ===================================================== -->

            <div class="card dashboard-card mb-4">

                <div class="card-header bg-dark text-white">

                    <i class="bi bi-ethernet"></i>

                    Network Interfaces

                </div>


                <div class="card-body">


                    <?php if (empty($scenarioInterfaces)): ?>

                        <div class="alert alert-light border mb-0">

                            No interfaces are available for inspection.

                        </div>

                    <?php else: ?>


                        <div class="row g-3">


                            <?php foreach (
                                $scenarioInterfaces
                                as $interface
                            ): ?>


                                <div class="col-md-6">


                                    <div class="border rounded p-3 h-100">


                                        <div
                                            class="d-flex justify-content-between align-items-start gap-2"
                                        >

                                            <div>

                                                <div class="fw-bold">

                                                    <?= htmlspecialchars(
                                                        $interface['interface_name']
                                                    ) ?>

                                                </div>


                                                <div class="small text-muted">

                                                    <?= htmlspecialchars(
                                                        $interface['device_name']
                                                    ) ?>

                                                    ·

                                                    <?= htmlspecialchars(
                                                        $interface['device_code']
                                                    ) ?>

                                                </div>

                                            </div>


                                            <?php

                                            $status =
                                                $interface['interface_status']
                                                ?? 'Unknown';

                                            $statusClass =
                                                $status === 'Up'
                                                ? 'success'
                                                : (
                                                    $status === 'Down'
                                                    ? 'warning text-dark'
                                                    : 'danger'
                                                );

                                            ?>


                                            <span
                                                class="badge bg-<?= $statusClass ?>"
                                            >

                                                <?= htmlspecialchars(
                                                    $status
                                                ) ?>

                                            </span>

                                        </div>


                                        <div class="small mt-3">

                                            <strong>
                                                Type:
                                            </strong>

                                            <?= htmlspecialchars(
                                                $interface['interface_type']
                                            ) ?>

                                        </div>


                                        <div class="small mt-1">

                                            <strong>
                                                IP Address:
                                            </strong>

                                            <?= htmlspecialchars(
                                                $interface['ip_address']
                                                    ?: 'Not configured'
                                            ) ?>

                                        </div>


                                        <div class="small mt-1">

                                            <strong>
                                                Subnet Mask:
                                            </strong>

                                            <?= htmlspecialchars(
                                                $interface['subnet_mask']
                                                    ?: 'Not configured'
                                            ) ?>

                                        </div>


                                        <form
                                            method="post"
                                    class="js-inspection-form mt-3"
                                        >

                                            <input
                                                type="hidden"
                                                name="action_type"
                                                value="inspect_interface"
                                            >

                                            <input
                                                type="hidden"
                                                name="target_type"
                                                value="interface"
                                            >

                                            <input
                                                type="hidden"
                                                name="target_id"
                                                value="<?= (int) $interface['interface_id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-primary"
                                            >

                                                <i class="bi bi-search"></i>

                                                Inspect Interface

                                            </button>

                                        </form>


                                    </div>

                                </div>


                            <?php endforeach; ?>


                        </div>


                    <?php endif; ?>


                </div>

            </div>



            <!-- ====================================================
                 INVESTIGATION ACTIONS
            ===================================================== -->

            <div class="card dashboard-card mb-4" id="investigationActions">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-search me-1"></i>
                    Investigation Actions
                </div>
                <div class="card-body">
                    <div class="small text-muted mb-3">
                        These are diagnostic checks. They do not solve the fault by themselves.
                        Use the findings to reach the Diagnosis step below.
                    </div>

                    <div class="row g-3">
                        <?php
                        $investigationActions = [
                            ['check_ip_configuration', 'Check IP Configuration', 'bi-pc-display', 'Inspect IP address and subnet configuration.'],
                            ['check_interface_status', 'Check Interface Status', 'bi-ethernet', 'Check whether network interfaces are operational.'],
                            ['check_connectivity', 'Test Connectivity', 'bi-broadcast-pin', 'Check whether configured links and endpoint interfaces can communicate.'],
                            ['check_routing', 'Check Routing', 'bi-signpost-split', 'Investigate whether routing may be involved in the fault.'],
                            ['check_vlan_configuration', 'Check VLAN Configuration', 'bi-diagram-3', 'Investigate switching and VLAN-related problems.'],
                            ['check_dhcp_configuration', 'Check DHCP Configuration', 'bi-hdd-network', 'Investigate automatic IP address assignment.'],
                            ['check_wireless_configuration', 'Check Wireless Configuration', 'bi-wifi', 'Investigate wireless network configuration.'],
                            ['check_device_configuration', 'Check Device Configuration', 'bi-terminal', 'Inspect the general device configuration evidence.']
                        ];
                        ?>

                        <?php foreach ($investigationActions as $tool): ?>
                            <div class="col-md-6 col-xl-4">
                                <form method="post" class="h-100 js-workspace-form">
                                    <input type="hidden" name="action_type" value="<?= htmlspecialchars($tool[0]) ?>">
                                    <button type="submit" class="btn btn-outline-primary text-start w-100 h-100 p-3 investigation-action-card">
                                        <div class="d-flex align-items-start gap-3">
                                            <i class="bi <?= htmlspecialchars($tool[2]) ?> fs-2"></i>
                                            <div>
                                                <div class="fw-bold fs-5 mb-1"><?= htmlspecialchars($tool[1]) ?></div>
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


            <!-- ====================================================
                 INSPECTION RESULT MODAL
            ===================================================== -->

            <div
                class="modal fade"
                id="inspectionModal"
                tabindex="-1"
                aria-hidden="true"
            >
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">

                        <div class="modal-header bg-primary text-white">
                            <h5
                                class="modal-title fw-bold"
                                id="inspectionModalTitle"
                            >
                                Inspection Result
                            </h5>

                            <button
                                type="button"
                                class="btn-close btn-close-white"
                                data-bs-dismiss="modal"
                                aria-label="Close"
                            ></button>
                        </div>

                        <div
                            class="modal-body"
                            id="inspectionModalBody"
                        ></div>

                        <div class="modal-footer">
                            <span class="small text-muted me-auto">
                                <i class="bi bi-check-circle text-success"></i>
                                Inspection recorded in the activity history below.
                            </span>

                            <button
                                type="button"
                                class="btn btn-primary"
                                data-bs-dismiss="modal"
                            >
                                Close
                            </button>
                        </div>

                    </div>
                </div>
            </div>


            <!-- ====================================================
                 PROCESSING MODAL
            ===================================================== -->

            <div
                class="modal fade"
                id="processingModal"
                tabindex="-1"
                aria-hidden="true"
                data-bs-backdrop="static"
                data-bs-keyboard="false"
            >
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content">
                        <div class="modal-body text-center py-4">
                            <div class="spinner-border text-primary mb-3"></div>
                            <div class="fw-semibold">Inspecting network element...</div>
                            <div class="small text-muted mt-1">
                                Recording the troubleshooting action.
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- ====================================================
                 ACTIVITY LOG
            ===================================================== -->

            <div class="card dashboard-card mb-4">

                <div class="card-header bg-secondary text-white">

                    <i class="bi bi-clock-history"></i>

                    Troubleshooting Activity

                </div>


                <div class="card-body">


                    <?php if (empty($activityLog)): ?>


                        <div class="text-muted">

                            No troubleshooting actions have been
                            recorded yet.

                            Begin by inspecting a device or interface.

                        </div>


                    <?php else: ?>


                        <div class="table-responsive">

                            <table
                                class="table table-sm table-bordered align-middle mb-0"
                            >

                                <thead class="table-light">

                                    <tr>

                                        <th>
                                            Action
                                        </th>

                                        <th>
                                            Target
                                        </th>

                                        <th>
                                            Result
                                        </th>

                                        <th>
                                            Time
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>


                                    <?php foreach (
                                        $activityLog
                                        as $activity
                                    ): ?>


                                        <tr>

                                            <td>

                                                <?= htmlspecialchars(
                                                    $activity['action_type']
                                                ) ?>

                                            </td>


                                            <td>

                                                <?= htmlspecialchars(
                                                    $activity['target_type']
                                                ) ?>

                                                <?php if (
                                                    $activity['target_id']
                                                    !== null
                                                ): ?>

                                                    #

                                                    <?= (int) $activity['target_id'] ?>

                                                <?php endif; ?>

                                            </td>


                                            <td>

                                                <?php

                                                $resultText =
                                                    'Action recorded.';

                                                if (
                                                    !empty(
                                                        $activity['action_result']
                                                    )
                                                ) {

                                                    $decoded =
                                                        json_decode(
                                                            $activity[
                                                                'action_result'
                                                            ],
                                                            true
                                                        );

                                                    if (
                                                        is_array($decoded)
                                                        && isset(
                                                            $decoded['result']
                                                        )
                                                    ) {

                                                        $resultText =
                                                            (string)
                                                            $decoded['result'];
                                                    }
                                                }

                                                ?>

                                                <?= htmlspecialchars(
                                                    $resultText
                                                ) ?>

                                            </td>


                                            <td class="text-nowrap">

                                                <?= htmlspecialchars(
                                                    $activity['created_at']
                                                ) ?>

                                            </td>

                                        </tr>


                                    <?php endforeach; ?>


                                </tbody>

                            </table>

                        </div>


                    <?php endif; ?>


                </div>

            </div>

    <?php if (($attempt['status'] ?? '') === 'Submitted'): ?>

    <?php
    /*
    |--------------------------------------------------------------------------
    | Submitted Attempt Result
    |--------------------------------------------------------------------------
    */

    $submissionAssessment = null;

    $stmt = db()->prepare("
        SELECT action_result
        FROM scenario_attempt_actions
        WHERE attempt_id = ?
          AND action_type = 'SUBMIT_ATTEMPT'
        ORDER BY action_id DESC
        LIMIT 1
    ");

    $stmt->execute([
        $attemptId
    ]);

    $submissionRecord = $stmt->fetchColumn();

    if ($submissionRecord) {
        $submissionAssessment = json_decode(
            (string) $submissionRecord,
            true
        );
    }

    $submittedScore =
        (int) (
            $submissionAssessment['score']
            ?? 0
        );

    $submittedStatus =
        (string) (
            $submissionAssessment['status']
            ?? 'Submitted'
        );

    $submittedReason =
        (string) (
            $submissionAssessment['reason']
            ?? 'The troubleshooting attempt has been submitted successfully.'
        );
    ?>

    <div class="card dashboard-card mb-4 border-success">

        <div class="card-header bg-success text-white">

            <i class="bi bi-check-circle-fill me-2"></i>

            Troubleshooting Attempt Submitted

        </div>

        <div class="card-body">

            <div class="alert alert-success">

                <h5 class="fw-bold mb-2">

                    <i class="bi bi-patch-check-fill me-1"></i>

                    Attempt Completed Successfully

                </h5>

                <p class="mb-0">

                    Your troubleshooting exercise has been submitted
                    and recorded by VNDTES.

                </p>

            </div>


            <div class="row g-3 mb-4">

                <div class="col-md-4">

                    <div class="border rounded p-3 text-center h-100">

                        <div class="text-muted small">
                            SCORE
                        </div>

                        <div class="display-6 fw-bold text-success">

                            <?= $submittedScore ?>%

                        </div>

                    </div>

                </div>


                <div class="col-md-4">

                    <div class="border rounded p-3 text-center h-100">

                        <div class="text-muted small">
                            STATUS
                        </div>

                        <div class="fs-4 fw-bold text-success mt-2">

                            <?= htmlspecialchars(
                                $submittedStatus
                            ) ?>

                        </div>

                    </div>

                </div>


                <div class="col-md-4">

                    <div class="border rounded p-3 text-center h-100">

                        <div class="text-muted small">
                            ATTEMPT
                        </div>

                        <div class="fs-4 fw-bold mt-2">

                            #<?= (int) $attempt['attempt_number'] ?>

                        </div>

                    </div>

                </div>

            </div>


            <div class="border rounded p-3 mb-4">

                <div class="fw-bold mb-2">

                    <i class="bi bi-clipboard-check me-1"></i>

                    Assessment

                </div>

                <p class="mb-0 text-muted">

                    <?= htmlspecialchars(
                        $submittedReason
                    ) ?>

                </p>

            </div>


            <div class="row g-3">

                <div class="col-md-6">

                    <a
                        href="exercises.php"
                        class="btn btn-outline-primary w-100"
                    >

                        <i class="bi bi-arrow-left me-1"></i>

                        Back to Available Exercises

                    </a>

                </div>


                <div class="col-md-6">

                    <a
                        href="dashboard.php"
                        class="btn btn-primary w-100"
                    >

                        <i class="bi bi-speedometer2 me-1"></i>

                        Student Dashboard

                    </a>

                </div>

            </div>

        </div>

    </div>


<?php else: ?>

            
            <!-- ====================================================
                 DIAGNOSIS / CORRECTION / VERIFICATION
            ===================================================== -->

            <div class="card dashboard-card mb-4" id="diagnosisSection">

                <div class="card-header bg-primary text-white">
                    <i class="bi bi-clipboard2-pulse me-1"></i>
                    Diagnosis and Resolution
                </div>

                <div class="card-body">

                    <div class="alert alert-light border mb-4">
                        <strong>What happens next?</strong>
                        <div class="small mt-1">
                            The checks above are investigation tools. Use their findings to identify the fault,
                            apply the appropriate correction, verify the result, and finally submit the attempt.
                        </div>
                    </div>

                    <div class="row g-4">

                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <div class="fw-bold mb-2">
                                    <i class="bi bi-search me-1"></i>
                                    Step 1 — Diagnose the Fault
                                </div>

                                <p class="small text-muted">
                                    Select the problem you believe is affecting this exercise.
                                    Your choice is checked against the hidden simulated fault.
                                </p>

                                <?php if (empty($assignedFaults)): ?>
                                    <div class="alert alert-warning mb-0">
                                        No faults have been assigned to this scenario yet.
                                    </div>
                                <?php else: ?>
                                    <form method="post" class="js-workspace-form">
                                        <input type="hidden" name="action_type" value="diagnose_fault">

                                        <select name="fault_id" class="form-select mb-3" required>
                                            <option value="">Select the suspected fault...</option>
                                            <?php foreach ($assignedFaults as $fault): ?>
                                                <option value="<?= (int) $fault['fault_id'] ?>">
                                                    <?= htmlspecialchars($fault['fault_code']) ?> —
                                                    <?= htmlspecialchars($fault['fault_title']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-search"></i>
                                            Submit Diagnosis
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <div class="fw-bold mb-2">
                                    <i class="bi bi-wrench-adjustable-circle me-1"></i>
                                    Step 2 — Apply Correction
                                </div>

                                <?php
                                $latestDiagnosisCorrect = false;
                                $latestDiagnosisTitle = '';

                                $stmt = db()->prepare("
                                    SELECT action_result
                                    FROM scenario_attempt_actions
                                    WHERE attempt_id = ?
                                      AND action_type = 'DIAGNOSE_FAULT'
                                    ORDER BY action_id DESC
                                    LIMIT 1
                                ");
                                $stmt->execute([$attemptId]);
                                $latestDiagnosisRecord = $stmt->fetchColumn();
                                if ($latestDiagnosisRecord) {
                                    $latestDiagnosis = json_decode((string) $latestDiagnosisRecord, true);
                                    $latestDiagnosisCorrect = !empty($latestDiagnosis['correct']);
                                    $latestDiagnosisTitle = (string) ($latestDiagnosis['fault_title'] ?? '');
                                }
                                ?>

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

                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-wrench"></i>
                                            Apply Correction
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="text-muted small">
                                        A correct diagnosis must be recorded before the correction step becomes available.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <div class="fw-bold mb-2">
                                    <i class="bi bi-shield-check me-1"></i>
                                    Step 3 — Verify Solution
                                </div>

                                <p class="small text-muted">
                                    Verification checks that the required diagnosis and corrective action have both been completed.
                                </p>

                                <form method="post" class="js-workspace-form">
                                    <input type="hidden" name="action_type" value="verify_solution">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="bi bi-check2-circle"></i>
                                        Verify Solution
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <div class="fw-bold mb-2">
                                    <i class="bi bi-send-check me-1"></i>
                                    Step 4 — Submit Attempt
                                </div>

                                <?php
                                $latestVerificationVerified = false;
                                $stmt = db()->prepare("
                                    SELECT action_result
                                    FROM scenario_attempt_actions
                                    WHERE attempt_id = ?
                                      AND action_type = 'VERIFY_SOLUTION'
                                    ORDER BY action_id DESC
                                    LIMIT 1
                                ");
                                $stmt->execute([$attemptId]);
                                $latestVerificationRecord = $stmt->fetchColumn();
                                if ($latestVerificationRecord) {
                                    $latestVerification = json_decode((string) $latestVerificationRecord, true);
                                    $latestVerificationVerified = !empty($latestVerification['verified']);
                                }
                                ?>

                                <?php if ($latestVerificationVerified): ?>
                                    <div class="alert alert-success py-2 small">
                                        Solution verified successfully. The attempt is ready for submission.
                                    </div>
                                    <form method="post" class="js-workspace-form">
                                        <input type="hidden" name="action_type" value="submit_attempt">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-send-check"></i>
                                            Submit Troubleshooting Attempt
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="text-muted small">
                                        Complete verification successfully before submitting the attempt.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

        <?php endif; ?>

        </div>

    </div>


</div>


<script>
window.VNDTES_TROUBLESHOOT = <?= json_encode(
    [
        'message' => $message,
        'messageType' => $messageType,
        'attemptId' => $attemptId,
        'attemptStatus' => $attempt['status'] ?? '',
        'actionResult' => $selectedActionResult,
        'verificationResult' => $verificationResult,
        'inspection' => $selectedDevice !== null
            ? [
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
                    'Notes' => $selectedDevice['notes'] ?: 'No additional notes.'
                ]
            ]
            : ($selectedInterface !== null
                ? [
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
                        'Notes' => $selectedInterface['notes'] ?: 'No additional notes.'
                    ]
                ]
                : ($selectedConnection !== null
                    ? [
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
                            'Notes' => $selectedConnection['notes'] ?: 'No additional notes.'
                        ]
                    ]
                    : null
                )
            )
    ],
    JSON_UNESCAPED_UNICODE
) ?>;
</script>
<script src="../assets/js/troubleshoot.js"></script>

<?php require_once '../includes/layout_end.php'; ?>