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

$pageTitle = 'Training Exercise';

$scenarioId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

$scenario = null;

$scenarioDevices = [];
$scenarioFaults = [];
$scenarioConnections = [];


/*
|--------------------------------------------------------------------------
| Validate Scenario ID
|--------------------------------------------------------------------------
*/

if ($scenarioId <= 0) {
    redirect('exercises.php');
}


/*
|--------------------------------------------------------------------------
| Load Published Scenario
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT
        s.scenario_id,
        s.scenario_code,
        s.scenario_title,
        s.category,
        s.difficulty,
        s.estimated_time,
        s.instructions,
        s.expected_outcome,
        s.status,
        s.created_at,
        s.updated_at,

        u.full_name AS lecturer_name

    FROM scenarios s

    LEFT JOIN users u
        ON u.user_id = s.created_by

    WHERE s.scenario_id = ?
      AND s.status = 'Published'

    LIMIT 1
");

$stmt->execute([
    $scenarioId
]);

$scenario = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Scenario Not Available
|--------------------------------------------------------------------------
*/

if (!$scenario) {

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
                    Exercise Not Available
                </h3>

                <p class="text-muted">

                    The requested exercise does not exist,
                    is not available to students, or has not yet
                    been published.

                </p>

                <a
                    href="exercises.php"
                    class="btn btn-primary"
                >

                    <i class="bi bi-arrow-left"></i>

                    Back to Available Exercises

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
| Load Scenario Faults
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT

        sf.scenario_fault_id,

        f.fault_id,
        f.fault_code,
        f.fault_title,
        f.category,
        f.difficulty,
        f.affected_device_type,
        f.symptoms,
        f.estimated_time

    FROM scenario_faults sf

    INNER JOIN faults f
        ON f.fault_id = sf.fault_id

    WHERE sf.scenario_id = ?

      AND f.status = 'Active'

    ORDER BY
        f.category ASC,
        f.fault_title ASC
");

$stmt->execute([
    $scenarioId
]);

$scenarioFaults = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load Scenario Topology Connections
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| scenario_connections does NOT contain scenario_id.
|
| The relationship is:
|
| scenario_connections
|        ↓ interface_a_id / interface_b_id
| scenario_device_interfaces
|        ↓ instance_id
| scenario_device_instances
|        ↓ scenario_device_id
| scenario_devices
|        ↓ scenario_id
| scenarios
|
| Therefore the selected scenario is resolved through both
| connection endpoints.
|
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT

        sc.connection_id,
        sc.connection_type,
        sc.status,
        sc.notes,
        sc.created_at,
        sc.updated_at,

        /* ------------------------------------------------------------
           ENDPOINT A
        ------------------------------------------------------------ */

        ia.interface_id AS interface_a_id,
        ia.interface_name AS interface_a_name,
        ia.interface_type AS interface_a_type,
        ia.ip_address AS interface_a_ip,
        ia.subnet_mask AS interface_a_subnet,
        ia.mac_address AS interface_a_mac,
        ia.interface_status AS interface_a_status,

        inst_a.instance_id AS instance_a_id,

        sd_a.scenario_device_id AS scenario_device_a_id,
        sd_a.quantity AS device_a_quantity,

        da.device_id AS device_a_id,
        da.device_code AS device_a_code,
        da.device_name AS device_a_name,
        da.device_type AS device_a_type,

        /* ------------------------------------------------------------
           ENDPOINT B
        ------------------------------------------------------------ */

        ib.interface_id AS interface_b_id,
        ib.interface_name AS interface_b_name,
        ib.interface_type AS interface_b_type,
        ib.ip_address AS interface_b_ip,
        ib.subnet_mask AS interface_b_subnet,
        ib.mac_address AS interface_b_mac,
        ib.interface_status AS interface_b_status,

        inst_b.instance_id AS instance_b_id,

        sd_b.scenario_device_id AS scenario_device_b_id,
        sd_b.quantity AS device_b_quantity,

        db.device_id AS device_b_id,
        db.device_code AS device_b_code,
        db.device_name AS device_b_name,
        db.device_type AS device_b_type

    FROM scenario_connections sc

    /* ================================================================
       ENDPOINT A
    ================================================================ */

    INNER JOIN scenario_device_interfaces ia
        ON ia.interface_id = sc.interface_a_id

    INNER JOIN scenario_device_instances inst_a
        ON inst_a.instance_id = ia.instance_id

    INNER JOIN scenario_devices sd_a
        ON sd_a.scenario_device_id = inst_a.scenario_device_id

    INNER JOIN devices da
        ON da.device_id = sd_a.device_id


    /* ================================================================
       ENDPOINT B
    ================================================================ */

    INNER JOIN scenario_device_interfaces ib
        ON ib.interface_id = sc.interface_b_id

    INNER JOIN scenario_device_instances inst_b
        ON inst_b.instance_id = ib.instance_id

    INNER JOIN scenario_devices sd_b
        ON sd_b.scenario_device_id = inst_b.scenario_device_id

    INNER JOIN devices db
        ON db.device_id = sd_b.device_id


    /* ================================================================
       SCENARIO FILTER
    ================================================================ */

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
| Start / Resume Student Attempt
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['start_attempt'])) {

    $studentUserId = currentUserId();

    /*
     * Resolve logged-in user to student record.
     */
    $stmt = db()->prepare("
        SELECT student_id
        FROM students
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $studentUserId
    ]);

    $studentId = (int) $stmt->fetchColumn();

    if ($studentId <= 0) {
        die('Student record could not be found.');
    }


    /*
     * Check whether this student already has an
     * unfinished attempt for this scenario.
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


    /*
     * Resume existing attempt.
     */
    if ($existingAttemptId > 0) {

        $stmt = db()->prepare("
            UPDATE scenario_attempts
            SET last_activity_at = NOW()
            WHERE attempt_id = ?
        ");

        $stmt->execute([
            $existingAttemptId
        ]);

        redirect(
            'troubleshoot.php?attempt_id=' .
            $existingAttemptId
        );

    }


    /*
     * Determine next attempt number.
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
     * Create new attempt.
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


    /*
     * Open the troubleshooting workspace.
     */
    redirect(
        'troubleshoot.php?attempt_id=' .
        $attemptId
    );
}

/*
|--------------------------------------------------------------------------
| Difficulty Presentation
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

<div class="container-fluid">


    <!-- ============================================================
         PAGE HEADER
    ============================================================= -->

    <div class="d-flex justify-content-between align-items-center mb-4">

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

                <?= htmlspecialchars(
                    $scenario['scenario_title']
                ) ?>

            </h2>


            <p class="text-muted mb-0">

                Scenario Code:

                <strong>

                    <?= htmlspecialchars(
                        $scenario['scenario_code']
                    ) ?>

                </strong>

            </p>

        </div>


        <div>

            <a
                href="exercises.php"
                class="btn btn-outline-secondary"
            >

                <i class="bi bi-arrow-left"></i>

                Back to Exercises

            </a>

        </div>

    </div>



    <!-- ============================================================
         TRAINING STATUS
    ============================================================= -->

    <div class="alert alert-primary d-flex align-items-start gap-3">

        <i class="bi bi-info-circle-fill fs-4"></i>

        <div>

            <strong>
                Training Exercise
            </strong>

            <div class="small mt-1">

                Study the network scenario and follow the
                troubleshooting instructions provided below.

            </div>

        </div>

    </div>



    <!-- ============================================================
         SCENARIO INFORMATION
    ============================================================= -->

    <div class="row g-4 mb-4">


        <!-- SCENARIO DETAILS -->

        <div class="col-lg-8">

            <div class="card dashboard-card h-100">

                <div class="card-header bg-dark text-white">

                    <i class="bi bi-diagram-3-fill"></i>

                    Scenario Information

                </div>


                <div class="card-body">


                    <div class="mb-4">

                        <h5 class="fw-bold">
                            Scenario
                        </h5>

                        <p class="mb-0">

                            <?= htmlspecialchars(
                                $scenario['scenario_title']
                            ) ?>

                        </p>

                    </div>


                    <div class="mb-4">

                        <h5 class="fw-bold">
                            Troubleshooting Instructions
                        </h5>


                        <div class="p-3 bg-light rounded">

                            <?= nl2br(
                                htmlspecialchars(
                                    $scenario['instructions'] ?? ''
                                )
                            ) ?>

                        </div>

                    </div>


                    <?php if (!empty($scenario['expected_outcome'])): ?>

                        <div>

                            <h5 class="fw-bold">
                                Expected Outcome
                            </h5>


                            <div class="p-3 bg-light rounded">

                                <?= nl2br(
                                    htmlspecialchars(
                                        $scenario['expected_outcome']
                                    )
                                ) ?>

                            </div>

                        </div>

                    <?php endif; ?>


                </div>

            </div>

        </div>



        <!-- EXERCISE SUMMARY -->

        <div class="col-lg-4">

            <div class="card dashboard-card h-100">

                <div class="card-header bg-primary text-white">

                    <i class="bi bi-clipboard-data-fill"></i>

                    Exercise Summary

                </div>


                <div class="card-body">


                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Scenario Code
                        </small>

                        <strong>

                            <?= htmlspecialchars(
                                $scenario['scenario_code']
                            ) ?>

                        </strong>

                    </div>


                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Category
                        </small>

                        <strong>

                            <?= htmlspecialchars(
                                $scenario['category']
                            ) ?>

                        </strong>

                    </div>


                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Difficulty
                        </small>

                        <span class="badge bg-<?= $difficultyClass ?>">

                            <?= htmlspecialchars(
                                $scenario['difficulty']
                            ) ?>

                        </span>

                    </div>


                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Estimated Time
                        </small>

                        <strong>

                            <?= (int) $scenario['estimated_time'] ?>

                            minutes

                        </strong>

                    </div>


                    <hr>


                    <div class="d-flex justify-content-between">

                        <span class="text-muted">
                            Configured Devices
                        </span>

                        <strong>

                            <?= count($scenarioDevices) ?>

                        </strong>

                    </div>


                    <div class="d-flex justify-content-between mt-2">

                        <span class="text-muted">
                            Assigned Faults
                        </span>

                        <strong>

                            <?= count($scenarioFaults) ?>

                        </strong>

                    </div>


                    <div class="d-flex justify-content-between mt-2">

                        <span class="text-muted">
                            Connections
                        </span>

                        <strong>

                            <?= count($scenarioConnections) ?>

                        </strong>

                    </div>

                </div>

            </div>

        </div>

    </div>



    <!-- ============================================================
         CONFIGURED DEVICES
    ============================================================= -->

    <div class="card dashboard-card mb-4">

        <div class="card-header bg-success text-white">

            <i class="bi bi-router-fill"></i>

            Network Devices

        </div>


        <div class="card-body">


            <?php if (empty($scenarioDevices)): ?>

                <div class="alert alert-light border mb-0">

                    No network devices have been configured
                    for this exercise.

                </div>

            <?php else: ?>


                <div class="row g-3">


                    <?php foreach ($scenarioDevices as $device): ?>


                        <div class="col-md-6 col-xl-4">

                            <div class="border rounded p-3 h-100">


                                <div
                                    class="d-flex justify-content-between align-items-start gap-2"
                                >

                                    <strong>

                                        <?= htmlspecialchars(
                                            $device['device_name']
                                        ) ?>

                                    </strong>


                                    <span class="badge bg-secondary">

                                        Qty:

                                        <?= (int) $device['quantity'] ?>

                                    </span>

                                </div>


                                <div class="small text-muted mt-2">

                                    <?= htmlspecialchars(
                                        $device['device_code']
                                    ) ?>

                                </div>


                                <div class="small mt-1">

                                    <?= htmlspecialchars(
                                        $device['device_type']
                                    ) ?>

                                </div>


                                <?php if (!empty($device['vendor'])): ?>

                                    <div class="small text-muted mt-1">

                                        Vendor:

                                        <?= htmlspecialchars(
                                            $device['vendor']
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <?php if (!empty($device['model'])): ?>

                                    <div class="small text-muted mt-1">

                                        Model:

                                        <?= htmlspecialchars(
                                            $device['model']
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <?php if (!empty($device['notes'])): ?>

                                    <div class="small mt-2">

                                        <?= nl2br(
                                            htmlspecialchars(
                                                $device['notes']
                                            )
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                            </div>

                        </div>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </div>

    </div>



    <!-- ============================================================
         NETWORK TOPOLOGY
    ============================================================= -->

    <div class="card dashboard-card mb-4">

        <div class="card-header bg-info text-dark">

            <i class="bi bi-share-fill"></i>

            Network Topology

        </div>


        <div class="card-body">


            <?php if (empty($scenarioConnections)): ?>


                <div class="alert alert-light border mb-0">

                    <i class="bi bi-info-circle"></i>

                    No network connections have been configured
                    for this exercise.

                </div>


            <?php else: ?>


                <div class="table-responsive">

                    <table class="table table-bordered table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th style="width:70px;">
                                    #
                                </th>

                                <th>
                                    Device A
                                </th>

                                <th>
                                    Interface A
                                </th>

                                <th class="text-center">
                                    Connection
                                </th>

                                <th>
                                    Interface B
                                </th>

                                <th>
                                    Device B
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach ($scenarioConnections as $connection): ?>


                                <tr>


                                    <td>

                                        <?= (int) $connection['connection_id'] ?>

                                    </td>


                                    <!-- DEVICE A -->

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


                                    <!-- INTERFACE A -->

                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $connection['interface_a_name']
                                            ) ?>

                                        </strong>


                                        <div class="small text-muted">

                                            <?= htmlspecialchars(
                                                $connection['interface_a_type']
                                            ) ?>

                                        </div>

                                    </td>


                                    <!-- CONNECTION TYPE -->

                                    <td class="text-center">

                                        <span class="badge bg-primary">

                                            <?= htmlspecialchars(
                                                $connection['connection_type']
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- INTERFACE B -->

                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $connection['interface_b_name']
                                            ) ?>

                                        </strong>


                                        <div class="small text-muted">

                                            <?= htmlspecialchars(
                                                $connection['interface_b_type']
                                            ) ?>

                                        </div>

                                    </td>


                                    <!-- DEVICE B -->

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


                                    <!-- STATUS -->

                                    <td>

                                        <?php if (
                                            ($connection['status'] ?? '') === 'Active'
                                        ): ?>

                                            <span class="badge bg-success">

                                                Active

                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-secondary">

                                                <?= htmlspecialchars(
                                                    $connection['status'] ?? 'Unknown'
                                                ) ?>

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php endif; ?>


        </div>

    </div>



    <!-- ============================================================
         TROUBLESHOOTING FAULTS
    ============================================================= -->

    <div class="card dashboard-card mb-4">

        <div class="card-header bg-danger text-white">

            <i class="bi bi-bug-fill"></i>

            Troubleshooting Faults

        </div>


        <div class="card-body">


            <?php if (empty($scenarioFaults)): ?>


                <div class="alert alert-light border mb-0">

                    No troubleshooting faults have been assigned
                    to this exercise yet.

                </div>


            <?php else: ?>


                <div class="row g-3">


                    <?php foreach ($scenarioFaults as $fault): ?>


                        <div class="col-lg-6">

                            <div class="border rounded p-3 h-100">


                                <div
                                    class="d-flex justify-content-between align-items-start gap-3"
                                >


                                    <div>

                                        <div class="fw-bold">

                                            <?= htmlspecialchars(
                                                $fault['fault_title']
                                            ) ?>

                                        </div>


                                        <small class="text-muted">

                                            <?= htmlspecialchars(
                                                $fault['fault_code']
                                            ) ?>

                                        </small>

                                    </div>


                                    <span class="badge bg-secondary">

                                        <?= htmlspecialchars(
                                            $fault['difficulty']
                                        ) ?>

                                    </span>


                                </div>


                                <div class="small mt-3">

                                    <strong>
                                        Category:
                                    </strong>

                                    <?= htmlspecialchars(
                                        $fault['category']
                                    ) ?>

                                </div>


                                <div class="small mt-1">

                                    <strong>
                                        Affected Device:
                                    </strong>

                                    <?= htmlspecialchars(
                                        $fault['affected_device_type']
                                    ) ?>

                                </div>


                                <?php if (!empty($fault['symptoms'])): ?>


                                    <div class="small mt-2">

                                        <strong>
                                            Symptoms:
                                        </strong>

                                        <?= nl2br(
                                            htmlspecialchars(
                                                $fault['symptoms']
                                            )
                                        ) ?>

                                    </div>


                                <?php endif; ?>


                                <?php if (!empty($fault['estimated_time'])): ?>


                                    <div class="small text-muted mt-2">

                                        Estimated troubleshooting time:

                                        <?= (int) $fault['estimated_time'] ?>

                                        minutes

                                    </div>


                                <?php endif; ?>


                            </div>

                        </div>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </div>

    </div>



    <!-- ============================================================
         TROUBLESHOOTING WORKSPACE
    ============================================================= -->

    <div class="card dashboard-card mb-4">

        <div class="card-body text-center py-5">

            <i
                class="bi bi-tools text-primary"
                style="font-size:55px;"
            ></i>


            <h4 class="fw-bold mt-3">

                Troubleshooting Workspace

            </h4>


            <p class="text-muted mb-4">

                The network topology for this exercise has been
                loaded successfully.

                The interactive troubleshooting, student actions,
                hints, feedback and assessment functions will be
                connected to this workspace in the next development
                stage.

            </p>


           <form method="post" class="d-inline">

    <input
        type="hidden"
        name="start_attempt"
        value="1"
    >

    <button
        type="submit"
        class="btn btn-primary"
    >

        <i class="bi bi-play-fill"></i>

        Start Troubleshooting

    </button>

</form>

        </div>

    </div>


</div>


<?php require_once '../includes/layout_end.php'; ?>