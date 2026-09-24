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


$pageTitle = 'Inspect Network Interface';

$studentId = currentUserId();

$attemptId = isset($_GET['attempt_id'])
    ? (int) $_GET['attempt_id']
    : 0;

$interfaceId = isset($_GET['interface_id'])
    ? (int) $_GET['interface_id']
    : 0;


if ($attemptId <= 0 || $interfaceId <= 0) {
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


/*
|--------------------------------------------------------------------------
| Check Attempt Status
|--------------------------------------------------------------------------
*/

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
| Load Interface
|--------------------------------------------------------------------------
|
| Interface belongs to:
|
| interface
|    ↓
| instance
|    ↓
| scenario_device
|    ↓
| scenario
|
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

        sdi_instance.instance_name,
        sdi_instance.display_name,

        sd.scenario_id,

        d.device_id,
        d.device_code,
        d.device_name,
        d.device_type,
        d.vendor,
        d.model

    FROM scenario_device_interfaces sdi

    INNER JOIN scenario_device_instances sdi_instance
        ON sdi_instance.instance_id = sdi.instance_id

    INNER JOIN scenario_devices sd
        ON sd.scenario_device_id =
           sdi_instance.scenario_device_id

    INNER JOIN devices d
        ON d.device_id = sd.device_id

    WHERE sdi.interface_id = ?
      AND sd.scenario_id = ?

    LIMIT 1
");

$stmt->execute([
    $interfaceId,
    $attempt['scenario_id']
]);

$interface = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$interface) {

    require_once '../includes/layout_start.php';
    ?>

    <div class="container-fluid">

        <div class="card dashboard-card">

            <div class="card-body text-center py-5">

                <i
                    class="bi bi-ethernet text-warning"
                    style="font-size:60px;"
                ></i>

                <h3 class="fw-bold mt-3">
                    Interface Not Found
                </h3>

                <p class="text-muted">
                    The selected interface does not belong to
                    this troubleshooting scenario.
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
    'interface',
    $interfaceId,
    json_encode([
        'interface_name' =>
            $interface['interface_name'],

        'device_id' =>
            (int) $interface['device_id'],

        'device_code' =>
            $interface['device_code'],

        'interface_status' =>
            $interface['interface_status'],

        'ip_address' =>
            $interface['ip_address'],

        'subnet_mask' =>
            $interface['subnet_mask']
    ]),
    'Interface inspected'
]);


/*
|--------------------------------------------------------------------------
| Update Activity
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

                <i class="bi bi-ethernet"></i>

                Inspect Interface

            </h2>

            <p class="text-muted mb-0">

                Examine the interface configuration and operational
                status.

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
         INTERFACE INFORMATION
    ============================================================= -->

    <div class="card dashboard-card mb-4">

        <div class="card-header bg-dark text-white">

            <i class="bi bi-ethernet"></i>

            Interface Information

        </div>


        <div class="card-body">

            <div class="row g-4">

                <div class="col-lg-8">

                    <h3 class="fw-bold">

                        <?= htmlspecialchars(
                            $interface['interface_name']
                        ) ?>

                    </h3>


                    <p class="text-muted">

                        <?= htmlspecialchars(
                            $interface['device_name']
                        ) ?>

                        ·

                        <?= htmlspecialchars(
                            $interface['device_code']
                        ) ?>

                    </p>


                    <?php

                    $statusClass = match (
                        $interface['interface_status']
                    ) {

                        'Up' =>
                            'success',

                        'Administratively Down' =>
                            'danger',

                        default =>
                            'warning text-dark'
                    };

                    ?>


                    <div class="mb-4">

                        <span
                            class="badge bg-<?= $statusClass ?>"
                            style="font-size:15px;"
                        >

                            <?= htmlspecialchars(
                                $interface['interface_status']
                            ) ?>

                        </span>

                    </div>


                    <div class="row g-4">

                        <div class="col-md-6">

                            <strong>Interface Name</strong>

                            <div class="mt-1">

                                <?= htmlspecialchars(
                                    $interface['interface_name']
                                ) ?>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <strong>Interface Type</strong>

                            <div class="mt-1">

                                <?= htmlspecialchars(
                                    $interface['interface_type']
                                    ?: 'Not specified'
                                ) ?>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <strong>IP Address</strong>

                            <div class="mt-1">

                                <?= htmlspecialchars(
                                    $interface['ip_address']
                                    ?: 'Not configured'
                                ) ?>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <strong>Subnet Mask</strong>

                            <div class="mt-1">

                                <?= htmlspecialchars(
                                    $interface['subnet_mask']
                                    ?: 'Not configured'
                                ) ?>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <strong>MAC Address</strong>

                            <div class="mt-1">

                                <?= htmlspecialchars(
                                    $interface['mac_address']
                                    ?: 'Not configured'
                                ) ?>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <strong>Device Type</strong>

                            <div class="mt-1">

                                <?= htmlspecialchars(
                                    $interface['device_type']
                                ) ?>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="col-lg-4">

                    <div class="alert alert-info">

                        <i class="bi bi-info-circle"></i>

                        <strong>What should you check?</strong>

                        <ul class="mb-0 mt-2">

                            <li>Interface status</li>

                            <li>IP address</li>

                            <li>Subnet mask</li>

                            <li>Interface type</li>

                            <li>Connection to the network</li>

                        </ul>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ============================================================
         NOTES
    ============================================================= -->

    <?php if (!empty($interface['notes'])): ?>

        <div class="card dashboard-card mb-4">

            <div class="card-header bg-secondary text-white">

                <i class="bi bi-sticky"></i>

                Interface Notes

            </div>

            <div class="card-body">

                <?= nl2br(
                    htmlspecialchars(
                        $interface['notes']
                    )
                ) ?>

            </div>

        </div>

    <?php endif; ?>


    <!-- ============================================================
         NEXT STEP
    ============================================================= -->

    <div class="card dashboard-card">

        <div class="card-body">

            <div class="alert alert-warning mb-0">

                <i class="bi bi-lightbulb"></i>

                <strong>Continue your diagnosis.</strong>

                Compare this interface information with the
                scenario requirements and the connected interface
                before making any correction.

            </div>

        </div>

    </div>


</div>


<?php require_once '../includes/layout_end.php'; ?>