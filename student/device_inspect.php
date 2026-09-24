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

$pageTitle = 'Inspect Network Device';

$studentId = currentUserId();

$attemptId = isset($_GET['attempt_id'])
    ? (int) $_GET['attempt_id']
    : 0;

$instanceId = isset($_GET['instance_id'])
    ? (int) $_GET['instance_id']
    : 0;


/*
|--------------------------------------------------------------------------
| Validate Parameters
|--------------------------------------------------------------------------
*/

if ($attemptId <= 0 || $instanceId <= 0) {

    redirect('exercises.php');

}


/*
|--------------------------------------------------------------------------
| Verify Attempt Belongs To Current Student
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT
        sa.attempt_id,
        sa.student_id,
        sa.scenario_id,
        sa.attempt_number,
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


/*
|--------------------------------------------------------------------------
| Attempt Not Found
|--------------------------------------------------------------------------
*/

if (!$attempt) {

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
                    Inspection Not Available
                </h3>

                <p class="text-muted">
                    The troubleshooting attempt could not be found
                    or does not belong to your account.
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
| Verify Attempt Status
|--------------------------------------------------------------------------
*/

if ($attempt['status'] === 'Completed' ||
    $attempt['status'] === 'Submitted' ||
    $attempt['status'] === 'Abandoned') {

    require_once '../includes/layout_start.php';
    ?>

    <div class="container-fluid">

        <div class="card dashboard-card">

            <div class="card-body text-center py-5">

                <i
                    class="bi bi-lock text-secondary"
                    style="font-size:60px;"
                ></i>

                <h3 class="fw-bold mt-3">
                    Attempt Is Closed
                </h3>

                <p class="text-muted">
                    This troubleshooting attempt is no longer active.
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
| Load Device Instance
|--------------------------------------------------------------------------
|
| The device instance belongs to:
|
| scenario_device_instances
|        ↓
| scenario_devices
|        ↓
| scenarios
|
| This allows us to verify that the device really belongs
| to the scenario being attempted.
|
*/

$stmt = db()->prepare("
    SELECT

        sdi.instance_id,
        sdi.scenario_device_id,
        sdi.instance_name,
        sdi.display_name,
        sdi.instance_status,
        sdi.notes AS instance_notes,

        sd.scenario_id,
        sd.quantity,
        sd.notes AS scenario_device_notes,

        d.device_id,
        d.device_code,
        d.device_name,
        d.device_type,
        d.vendor,
        d.model,
        d.interface_count,
        d.description,
        d.icon,
        d.status AS device_status

    FROM scenario_device_instances sdi

    INNER JOIN scenario_devices sd
        ON sd.scenario_device_id = sdi.scenario_device_id

    INNER JOIN devices d
        ON d.device_id = sd.device_id

    WHERE sdi.instance_id = ?
      AND sd.scenario_id = ?

    LIMIT 1
");

$stmt->execute([
    $instanceId,
    $attempt['scenario_id']
]);

$device = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Device Not Found
|--------------------------------------------------------------------------
*/

if (!$device) {

    require_once '../includes/layout_start.php';
    ?>

    <div class="container-fluid">

        <div class="card dashboard-card">

            <div class="card-body text-center py-5">

                <i
                    class="bi bi-router text-warning"
                    style="font-size:60px;"
                ></i>

                <h3 class="fw-bold mt-3">
                    Device Not Found
                </h3>

                <p class="text-muted">
                    The selected device is not part of this
                    troubleshooting scenario.
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
| Load Device Interfaces
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT

        interface_id,
        interface_name,
        interface_type,
        ip_address,
        subnet_mask,
        mac_address,
        interface_status,
        notes

    FROM scenario_device_interfaces

    WHERE instance_id = ?

    ORDER BY interface_id ASC
");

$stmt->execute([
    $instanceId
]);

$interfaces = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Record Inspection Action
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
    'device',
    $instanceId,
    json_encode([
        'device_id' => (int) $device['device_id'],
        'device_code' => $device['device_code'],
        'instance_name' => $device['instance_name']
    ]),
    'Device inspected'
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
         PAGE HEADER
    ============================================================= -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">

                <i class="bi bi-router"></i>

                Inspect Device

            </h2>

            <p class="text-muted mb-0">

                Examine the device configuration before deciding
                what corrective action is required.

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
         DEVICE INFORMATION
    ============================================================= -->

    <div class="card dashboard-card mb-4">

        <div class="card-header bg-primary text-white">

            <i class="bi bi-router"></i>

            Device Information

        </div>


        <div class="card-body">

            <div class="row g-4">

                <div class="col-lg-8">

                    <h3 class="fw-bold mb-2">

                        <?= htmlspecialchars(
                            $device['display_name']
                            ?: $device['device_name']
                        ) ?>

                    </h3>


                    <p class="text-muted mb-3">

                        <?= htmlspecialchars(
                            $device['device_code']
                        ) ?>

                    </p>


                    <div class="row g-3">

                        <div class="col-md-6">

                            <strong>Device Type</strong>

                            <div class="mt-1">

                                <?= htmlspecialchars(
                                    $device['device_type']
                                ) ?>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <strong>Instance Status</strong>

                            <div class="mt-1">

                                <span class="badge bg-success">

                                    <?= htmlspecialchars(
                                        $device['instance_status']
                                    ) ?>

                                </span>

                            </div>

                        </div>


                        <?php if (!empty($device['vendor'])): ?>

                            <div class="col-md-6">

                                <strong>Vendor</strong>

                                <div class="mt-1">

                                    <?= htmlspecialchars(
                                        $device['vendor']
                                    ) ?>

                                </div>

                            </div>

                        <?php endif; ?>


                        <?php if (!empty($device['model'])): ?>

                            <div class="col-md-6">

                                <strong>Model</strong>

                                <div class="mt-1">

                                    <?= htmlspecialchars(
                                        $device['model']
                                    ) ?>

                                </div>

                            </div>

                        <?php endif; ?>


                        <?php if (!empty($device['interface_count'])): ?>

                            <div class="col-md-6">

                                <strong>Interface Count</strong>

                                <div class="mt-1">

                                    <?= (int) $device['interface_count'] ?>

                                </div>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>


                <div class="col-lg-4">

                    <div class="alert alert-info mb-0">

                        <i class="bi bi-info-circle"></i>

                        <strong>Inspection Tip</strong>

                        <p class="mb-0 mt-2">

                            Examine the device and its interfaces
                            carefully before making any changes.

                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ============================================================
         DEVICE DESCRIPTION
    ============================================================= -->

    <?php if (!empty($device['description'])): ?>

        <div class="card dashboard-card mb-4">

            <div class="card-header bg-dark text-white">

                <i class="bi bi-file-text"></i>

                Device Description

            </div>

            <div class="card-body">

                <?= nl2br(
                    htmlspecialchars(
                        $device['description']
                    )
                ) ?>

            </div>

        </div>

    <?php endif; ?>


    <!-- ============================================================
         INTERFACES
    ============================================================= -->

    <div class="card dashboard-card mb-4">

        <div class="card-header bg-success text-white">

            <i class="bi bi-ethernet"></i>

            Network Interfaces

        </div>


        <div class="card-body">

            <?php if (empty($interfaces)): ?>

                <div class="alert alert-secondary mb-0">

                    No interfaces have been configured for this
                    device instance.

                </div>

            <?php else: ?>

                <div class="row g-3">

                    <?php foreach ($interfaces as $interface): ?>

                        <div class="col-md-6 col-xl-4">

                            <div class="border rounded p-3 h-100">

                                <div class="d-flex justify-content-between align-items-start">

                                    <h5 class="fw-bold mb-0">

                                        <?= htmlspecialchars(
                                            $interface['interface_name']
                                        ) ?>

                                    </h5>


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

                                    <span
                                        class="badge bg-<?= $statusClass ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $interface['interface_status']
                                        ) ?>

                                    </span>

                                </div>


                                <div class="mt-3 small">

                                    <div class="mb-2">

                                        <strong>Type:</strong>

                                        <?= htmlspecialchars(
                                            $interface['interface_type']
                                            ?: 'Not specified'
                                        ) ?>

                                    </div>


                                    <div class="mb-2">

                                        <strong>IP Address:</strong>

                                        <?= htmlspecialchars(
                                            $interface['ip_address']
                                            ?: 'Not configured'
                                        ) ?>

                                    </div>


                                    <div class="mb-2">

                                        <strong>Subnet Mask:</strong>

                                        <?= htmlspecialchars(
                                            $interface['subnet_mask']
                                            ?: 'Not configured'
                                        ) ?>

                                    </div>


                                    <div class="mb-2">

                                        <strong>MAC Address:</strong>

                                        <?= htmlspecialchars(
                                            $interface['mac_address']
                                            ?: 'Not configured'
                                        ) ?>

                                    </div>

                                </div>


                                <a
                                    href="interface_inspect.php?attempt_id=<?= $attemptId ?>&interface_id=<?= (int) $interface['interface_id'] ?>"
                                    class="btn btn-outline-primary btn-sm mt-2"
                                >

                                    <i class="bi bi-search"></i>

                                    Inspect Interface

                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>


</div>


<?php require_once '../includes/layout_end.php'; ?>