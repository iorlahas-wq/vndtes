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


$pageTitle = 'Inspect Network Connection';

$studentId = currentUserId();

$attemptId = isset($_GET['attempt_id'])
    ? (int) $_GET['attempt_id']
    : 0;

$connectionId = isset($_GET['connection_id'])
    ? (int) $_GET['connection_id']
    : 0;


if ($attemptId <= 0 || $connectionId <= 0) {
    redirect('exercises.php');
}


/*
|--------------------------------------------------------------------------
| Verify Attempt
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT
        sa.attempt_id,
        sa.student_id,
        sa.scenario_id,
        sa.status,

        s.scenario_code,
        s.scenario_title

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


if (!$attempt) {
    redirect('exercises.php');
}


if (in_array(
    $attempt['status'],
    ['Completed', 'Submitted', 'Abandoned'],
    true
)) {

    redirect(
        'troubleshoot.php?attempt_id=' .
        $attemptId
    );
}


/*
|--------------------------------------------------------------------------
| Load Connection
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| scenario_connections DOES NOT contain scenario_id.
|
| We therefore establish ownership through:
|
| connection
|     ↓
| interface
|     ↓
| instance
|     ↓
| scenario_device
|     ↓
| scenario
|
*/

$stmt = db()->prepare("
    SELECT

        sc.connection_id,
        sc.connection_type,
        sc.status AS connection_status,
        sc.notes,

        ia.interface_id AS interface_a_id,
        ia.interface_name AS interface_a_name,
        ia.interface_type AS interface_a_type,

        ib.interface_id AS interface_b_id,
        ib.interface_name AS interface_b_name,
        ib.interface_type AS interface_b_type,

        inst_a.instance_id AS instance_a_id,
        inst_a.instance_name AS instance_a_name,
        inst_a.display_name AS instance_a_display_name,

        inst_b.instance_id AS instance_b_id,
        inst_b.instance_name AS instance_b_name,
        inst_b.display_name AS instance_b_display_name,

        da.device_id AS device_a_id,
        da.device_code AS device_a_code,
        da.device_name AS device_a_name,
        da.device_type AS device_a_type,

        db.device_id AS device_b_id,
        db.device_code AS device_b_code,
        db.device_name AS device_b_name,
        db.device_type AS device_b_type

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


    WHERE sc.connection_id = ?

      AND sd_a.scenario_id = ?

      AND sd_b.scenario_id = ?

    LIMIT 1
");

$stmt->execute([
    $connectionId,
    $attempt['scenario_id'],
    $attempt['scenario_id']
]);

$connection = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$connection) {

    require_once '../includes/layout_start.php';
    ?>

    <div class="container-fluid">

        <div class="card dashboard-card">

            <div class="card-body text-center py-5">

                <i
                    class="bi bi-diagram-3 text-warning"
                    style="font-size:60px;"
                ></i>

                <h3 class="fw-bold mt-3">
                    Connection Not Found
                </h3>

                <p class="text-muted">
                    The selected network connection does not belong
                    to this troubleshooting scenario.
                </p>

                <a
                    href="troubleshoot.php?attempt_id=<?= $attemptId ?>"
                    class="btn btn-primary"
                >
                    <i class="bi bi-arrow-left"></i>
                    Return to Troubleshooting
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
| Record Inspection
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    INSERT INTO scenario_attempt_actions (
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
    'inspect',
    'connection',
    $connectionId,
    json_encode([
        'connection_type' =>
            $connection['connection_type'],

        'interface_a' =>
            $connection['interface_a_name'],

        'interface_b' =>
            $connection['interface_b_name'],

        'device_a' =>
            $connection['device_a_code'],

        'device_b' =>
            $connection['device_b_code']
    ]),
    'Connection inspected'
]);


/*
|--------------------------------------------------------------------------
| Update Attempt Activity
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    UPDATE scenario_attempts
    SET last_activity_at = NOW()
    WHERE attempt_id = ?
");

$stmt->execute([
    $attemptId
]);


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">


    <!-- ============================================================
         HEADER
    ============================================================= -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">

                <i class="bi bi-diagram-3"></i>

                Inspect Network Connection

            </h2>

            <p class="text-muted mb-0">

                Examine both ends of the network connection.

            </p>

        </div>


        <a
            href="troubleshoot.php?attempt_id=<?= $attemptId ?>"
            class="btn btn-outline-secondary"
        >

            <i class="bi bi-arrow-left"></i>

            Back to Troubleshooting

        </a>

    </div>


    <!-- ============================================================
         CONNECTION
    ============================================================= -->

    <div class="card dashboard-card mb-4">

        <div class="card-header bg-info text-dark">

            <i class="bi bi-share-fill"></i>

            Network Connection

        </div>


        <div class="card-body">

            <div class="row align-items-center g-4">

                <!-- DEVICE A -->

                <div class="col-md-5">

                    <div class="border rounded p-4 h-100">

                        <h4 class="fw-bold">

                            <?= htmlspecialchars(
                                $connection['device_a_name']
                            ) ?>

                        </h4>


                        <div class="text-muted mb-3">

                            <?= htmlspecialchars(
                                $connection['device_a_code']
                            ) ?>

                        </div>


                        <div class="mb-2">

                            <strong>Device Type:</strong>

                            <?= htmlspecialchars(
                                $connection['device_a_type']
                            ) ?>

                        </div>


                        <hr>


                        <div>

                            <strong>Interface</strong>

                            <div class="fs-5 mt-1">

                                <?= htmlspecialchars(
                                    $connection['interface_a_name']
                                ) ?>

                            </div>

                        </div>


                        <?php if (!empty($connection['interface_a_type'])): ?>

                            <div class="text-muted mt-1">

                                <?= htmlspecialchars(
                                    $connection['interface_a_type']
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- CONNECTION -->

                <div class="col-md-2 text-center">

                    <div
                        class="badge bg-primary px-3 py-3"
                        style="font-size:16px;"
                    >

                        <i class="bi bi-arrow-left-right"></i>

                        <?= htmlspecialchars(
                            $connection['connection_type']
                        ) ?>

                    </div>


                    <div class="mt-3">

                        <span
                            class="badge
                            <?= $connection['connection_status'] === 'Active'
                                ? 'bg-success'
                                : 'bg-secondary'
                            ?>"
                        >

                            <?= htmlspecialchars(
                                $connection['connection_status']
                            ) ?>

                        </span>

                    </div>

                </div>


                <!-- DEVICE B -->

                <div class="col-md-5">

                    <div class="border rounded p-4 h-100">

                        <h4 class="fw-bold">

                            <?= htmlspecialchars(
                                $connection['device_b_name']
                            ) ?>

                        </h4>


                        <div class="text-muted mb-3">

                            <?= htmlspecialchars(
                                $connection['device_b_code']
                            ) ?>

                        </div>


                        <div class="mb-2">

                            <strong>Device Type:</strong>

                            <?= htmlspecialchars(
                                $connection['device_b_type']
                            ) ?>

                        </div>


                        <hr>


                        <div>

                            <strong>Interface</strong>

                            <div class="fs-5 mt-1">

                                <?= htmlspecialchars(
                                    $connection['interface_b_name']
                                ) ?>

                            </div>

                        </div>


                        <?php if (!empty($connection['interface_b_type'])): ?>

                            <div class="text-muted mt-1">

                                <?= htmlspecialchars(
                                    $connection['interface_b_type']
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ============================================================
         TROUBLESHOOTING GUIDANCE
    ============================================================= -->

    <div class="card dashboard-card">

        <div class="card-header bg-dark text-white">

            <i class="bi bi-lightbulb"></i>

            What to Check

        </div>


        <div class="card-body">

            <div class="row g-3">

                <div class="col-md-4">

                    <div class="border rounded p-3 h-100">

                        <h5 class="fw-bold">

                            1. Check Both Interfaces

                        </h5>

                        <p class="text-muted mb-0">

                            Confirm that the interfaces at both
                            ends of the connection are the expected
                            interfaces for the topology.

                        </p>

                    </div>

                </div>


                <div class="col-md-4">

                    <div class="border rounded p-3 h-100">

                        <h5 class="fw-bold">

                            2. Check Status

                        </h5>

                        <p class="text-muted mb-0">

                            Determine whether the interfaces and
                            connection are operational.

                        </p>

                    </div>

                </div>


                <div class="col-md-4">

                    <div class="border rounded p-3 h-100">

                        <h5 class="fw-bold">

                            3. Compare the Configuration

                        </h5>

                        <p class="text-muted mb-0">

                            Compare the connection information with
                            the requirements of the scenario.

                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>


</div>


<?php require_once '../includes/layout_end.php'; ?>