<?php

declare(strict_types=1);

require_once '../includes/init.php';
require_once '../includes/auth.php';


/*
|--------------------------------------------------------------------------
| Lecturer Access
|--------------------------------------------------------------------------
*/

if (currentUserRole() !== 'Lecturer') {
    redirect(APP_URL);
}


/*
|--------------------------------------------------------------------------
| Page State
|--------------------------------------------------------------------------
*/

$pageTitle = 'Scenario Details';

$lecturerId = currentUserId();

$scenarioId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

$scenario = null;

$scenarioDevices = [];


/*
|--------------------------------------------------------------------------
| Validate Scenario ID
|--------------------------------------------------------------------------
*/

if ($scenarioId <= 0) {

    redirect('scenarios.php');

}


/*
|--------------------------------------------------------------------------
| Load Scenario
|--------------------------------------------------------------------------
|
| IMPORTANT:
| A lecturer can ONLY view scenarios created by that lecturer.
|
*/

$stmt = db()->prepare("
    SELECT
        s.*,

        u.full_name AS creator_name,

        l.staff_id,
        l.designation,
        l.specialization

    FROM scenarios s

    LEFT JOIN users u
        ON u.user_id = s.created_by

    LEFT JOIN lecturers l
        ON l.user_id = s.created_by

    WHERE s.scenario_id = ?
      AND s.created_by = ?

    LIMIT 1
");

$stmt->execute([
    $scenarioId,
    $lecturerId
]);

$scenario = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Scenario Not Found
|--------------------------------------------------------------------------
*/

if (!$scenario) {

    echo alert(
        'Scenario not found or you do not have permission to view it.',
        'danger'
    );

    require_once '../includes/layout_start.php';
    ?>

    <div class="container-fluid">

        <div class="text-center py-5">

            <i
                class="bi bi-exclamation-triangle text-warning"
                style="font-size: 60px;"
            ></i>

            <h3 class="fw-bold mt-3">
                Scenario Not Found
            </h3>

            <p class="text-muted">
                The requested scenario does not exist or you do not have
                permission to access it.
            </p>

            <a
                href="scenarios.php"
                class="btn btn-primary"
            >
                <i class="bi bi-arrow-left"></i>
                Back to My Scenarios
            </a>

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

    ORDER BY d.device_type ASC,
             d.device_name ASC
");

$stmt->execute([
    $scenarioId
]);

$scenarioDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Status Presentation
|--------------------------------------------------------------------------
*/

$status = $scenario['status'] ?? 'Draft';

$statusClass = 'secondary';

if ($status === 'Draft') {
    $statusClass = 'warning';
}

if ($status === 'Published') {
    $statusClass = 'success';
}

if ($status === 'Archived') {
    $statusClass = 'dark';
}


/*
|--------------------------------------------------------------------------
| Student Availability
|--------------------------------------------------------------------------
|
| Availability controls when a published scenario may appear on the
| student-facing scenario library.
|
*/

$availableFrom = $scenario['available_from'] ?? null;
$availableUntil = $scenario['available_until'] ?? null;

$availabilityLabel = 'Not Available to Students';
$availabilityClass = 'secondary';

try {

    $now = new DateTimeImmutable();

    $fromDate = !empty($availableFrom)
        ? new DateTimeImmutable($availableFrom)
        : null;

    $untilDate = !empty($availableUntil)
        ? new DateTimeImmutable($availableUntil)
        : null;

    if ($status !== 'Published') {

        $availabilityLabel = 'Awaiting Publication';
        $availabilityClass = 'warning';

    } elseif ($fromDate && $now < $fromDate) {

        $availabilityLabel = 'Scheduled';
        $availabilityClass = 'info';

    } elseif ($untilDate && $now > $untilDate) {

        $availabilityLabel = 'Expired';
        $availabilityClass = 'dark';

    } else {

        $availabilityLabel = 'Available to Students';
        $availabilityClass = 'success';

    }

} catch (Throwable $e) {

    $fromDate = null;
    $untilDate = null;

}


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

            <h2 class="fw-bold mb-1">
                Scenario Details
            </h2>

            <p class="text-muted mb-0">
                View your network troubleshooting scenario and its
                required network devices.
            </p>

        </div>


        <div class="d-flex gap-2">

            <a
                href="scenarios.php"
                class="btn btn-outline-secondary"
            >
                <i class="bi bi-arrow-left"></i>
                Back to My Scenarios
            </a>

            <a
                href="scenario_add.php?id=<?= $scenarioId ?>"
                class="btn btn-primary"
            >
                <i class="bi bi-pencil-square"></i>
                Edit Scenario
            </a>

        </div>

    </div>


    <!-- ============================================================
         SCENARIO HEADER
    ============================================================= -->

    <div class="card dashboard-card mb-4">

        <div class="card-body">

            <div class="row align-items-center">

                <div class="col-lg-8">

                    <div class="mb-2">

                        <span class="badge bg-primary">
                            <?= htmlspecialchars(
                                $scenario['category']
                            ) ?>
                        </span>

                        <span class="badge bg-<?= $statusClass ?>">
                            <?= htmlspecialchars($status) ?>
                        </span>

                        <span class="badge bg-secondary">
                            <?= htmlspecialchars(
                                $scenario['difficulty']
                            ) ?>
                        </span>

                        <span class="badge bg-info text-dark">
                            <?= (int) $scenario['estimated_time'] ?>
                            mins
                        </span>

                    </div>


                    <h2 class="fw-bold mb-2">

                        <?= htmlspecialchars(
                            $scenario['scenario_title']
                        ) ?>

                    </h2>


                    <div class="text-muted">

                        <strong>Scenario Code:</strong>

                        <?= htmlspecialchars(
                            $scenario['scenario_code']
                        ) ?>

                    </div>

                </div>


                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">

                    <div class="text-muted small">
                        Created
                    </div>

                    <div class="fw-semibold">

                        <?= htmlspecialchars(
                            $scenario['created_at'] ?? '—'
                        ) ?>

                    </div>


                    <?php if (!empty($scenario['updated_at'])): ?>

                        <div class="text-muted small mt-2">
                            Last Updated
                        </div>

                        <div class="fw-semibold">

                            <?= htmlspecialchars(
                                $scenario['updated_at']
                            ) ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>


    <!-- ============================================================
         SCENARIO CONTENT
    ============================================================= -->

    <div class="row">


        <!-- ========================================================
             INSTRUCTIONS
        ========================================================= -->

        <div class="col-lg-7">

            <div class="card dashboard-card mb-4">

                <div class="card-header bg-success text-white">

                    <i class="bi bi-file-text-fill"></i>

                    Troubleshooting Instructions

                </div>


                <div class="card-body">

                    <div
                        class="p-3 bg-light rounded"
                        style="white-space: normal;"
                    >

                        <?= nl2br(
                            htmlspecialchars(
                                $scenario['instructions']
                            )
                        ) ?>

                    </div>

                </div>

            </div>


            <!-- ====================================================
                 EXPECTED OUTCOME
            ===================================================== -->

            <div class="card dashboard-card mb-4">

                <div class="card-header bg-primary text-white">

                    <i class="bi bi-check-circle-fill"></i>

                    Expected Outcome

                </div>


                <div class="card-body">

                    <?php if (!empty($scenario['expected_outcome'])): ?>

                        <div class="p-3 bg-light rounded">

                            <?= nl2br(
                                htmlspecialchars(
                                    $scenario['expected_outcome']
                                )
                            ) ?>

                        </div>

                    <?php else: ?>

                        <p class="text-muted mb-0">

                            No expected outcome has been defined yet.

                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- ========================================================
             SCENARIO SUMMARY
        ========================================================= -->

        <div class="col-lg-5">

            <div class="card dashboard-card mb-4">

                <div class="card-header bg-dark text-white">

                    <i class="bi bi-info-circle-fill"></i>

                    Scenario Information

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

                        <strong>
                            <?= htmlspecialchars(
                                $scenario['difficulty']
                            ) ?>
                        </strong>

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


                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Status
                        </small>

                        <span class="badge bg-<?= $statusClass ?>">

                            <?= htmlspecialchars($status) ?>

                        </span>

                    </div>


                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Student Access
                        </small>

                        <span class="badge bg-<?= $availabilityClass ?>">

                            <?= htmlspecialchars($availabilityLabel) ?>

                        </span>

                    </div>


                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Available From
                        </small>

                        <strong>
                            <?php
                            if ($fromDate) {
                                echo htmlspecialchars(
                                    $fromDate->format('d M Y, h:i A')
                                );
                            } else {
                                echo 'Immediately after publication';
                            }
                            ?>
                        </strong>

                    </div>


                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Available Until
                        </small>

                        <strong>
                            <?php
                            if ($untilDate) {
                                echo htmlspecialchars(
                                    $untilDate->format('d M Y, h:i A')
                                );
                            } else {
                                echo 'No expiry';
                            }
                            ?>
                        </strong>

                    </div>


                    <div>

                        <small class="text-muted d-block">
                            Scenario Owner
                        </small>

                        <strong>
                            <?= htmlspecialchars(
                                $scenario['creator_name']
                                ?? currentUserName()
                            ) ?>
                        </strong>

                    </div>


                </div>

            </div>

        </div>

    </div>


    <!-- ============================================================
         REQUIRED DEVICES
    ============================================================= -->

    <div class="card dashboard-card mb-4">

        <div class="card-header bg-dark text-white">

            <i class="bi bi-hdd-network-fill"></i>

            Required Network Devices

            <span class="badge bg-primary ms-2">
                <?= count($scenarioDevices) ?>
            </span>

        </div>


        <div class="card-body">


            <?php if (empty($scenarioDevices)): ?>

                <div class="text-center py-5 text-muted">

                    <i
                        class="bi bi-hdd-network"
                        style="font-size: 50px;"
                    ></i>

                    <h5 class="mt-3">
                        No Devices Configured
                    </h5>

                    <p>
                        This scenario does not have any network devices
                        assigned yet.
                    </p>

                    <a
                        href="scenario_add.php?id=<?= $scenarioId ?>"
                        class="btn btn-dark"
                    >
                        <i class="bi bi-plus-circle"></i>
                        Configure Devices
                    </a>

                </div>

            <?php else: ?>


                <div class="row">


                    <?php foreach ($scenarioDevices as $device): ?>

                        <div class="col-md-6 col-xl-4 mb-4">

                            <div class="border rounded h-100 p-3">


                                <div
                                    class="d-flex
                                           justify-content-between
                                           align-items-start
                                           mb-2"
                                >

                                    <div>

                                        <h5 class="fw-bold mb-1">

                                            <?= htmlspecialchars(
                                                $device['device_name']
                                            ) ?>

                                        </h5>

                                        <small class="text-muted">

                                            <?= htmlspecialchars(
                                                $device['device_code']
                                            ) ?>

                                        </small>

                                    </div>


                                    <span class="badge bg-primary">

                                        Qty:
                                        <?= (int) $device['quantity'] ?>

                                    </span>

                                </div>


                                <div class="mb-2">

                                    <span class="badge bg-secondary">

                                        <?= htmlspecialchars(
                                            $device['device_type']
                                        ) ?>

                                    </span>

                                </div>


                                <?php if (!empty($device['vendor'])): ?>

                                    <div class="small mb-1">

                                        <strong>Vendor:</strong>

                                        <?= htmlspecialchars(
                                            $device['vendor']
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <?php if (!empty($device['model'])): ?>

                                    <div class="small mb-2">

                                        <strong>Model:</strong>

                                        <?= htmlspecialchars(
                                            $device['model']
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <?php if (!empty($device['notes'])): ?>

                                    <div class="small text-muted mt-3">

                                        <strong>
                                            Notes:
                                        </strong>

                                        <div class="mt-1">

                                            <?= nl2br(
                                                htmlspecialchars(
                                                    $device['notes']
                                                )
                                            ) ?>

                                        </div>

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
         STUDENT AVAILABILITY
    ============================================================= -->

    <div class="card dashboard-card mb-4">

        <div class="card-header bg-info text-dark">

            <i class="bi bi-calendar-check-fill"></i>

            Student Access & Availability

        </div>


        <div class="card-body">

            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">

                <strong>Current Student Access:</strong>

                <span class="badge bg-<?= $availabilityClass ?>">

                    <?= htmlspecialchars($availabilityLabel) ?>

                </span>

            </div>


            <div class="row g-3">

                <div class="col-md-6">

                    <div class="border rounded p-3 h-100">

                        <small class="text-muted d-block">
                            Available From
                        </small>

                        <strong>
                            <?php
                            if ($fromDate) {
                                echo htmlspecialchars(
                                    $fromDate->format('d M Y, h:i A')
                                );
                            } else {
                                echo 'Immediately after publication';
                            }
                            ?>
                        </strong>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="border rounded p-3 h-100">

                        <small class="text-muted d-block">
                            Available Until
                        </small>

                        <strong>
                            <?php
                            if ($untilDate) {
                                echo htmlspecialchars(
                                    $untilDate->format('d M Y, h:i A')
                                );
                            } else {
                                echo 'No expiry';
                            }
                            ?>
                        </strong>

                    </div>

                </div>

            </div>


            <div class="alert alert-light border mt-3 mb-0">

                <i class="bi bi-info-circle"></i>

                Students can access this scenario only when it is
                <strong>Published</strong> and the current time falls within
                the configured availability period.

            </div>

        </div>

    </div>


    <!-- ============================================================
         STUDENT PREVIEW
    ============================================================= -->

    <div class="card dashboard-card mb-4">

        <div class="card-header">

            <h5 class="mb-0 fw-bold">

                <i class="bi bi-eye"></i>

                Student Scenario Preview

            </h5>

        </div>


        <div class="card-body">


            <div class="alert alert-light border mb-3">

                <i class="bi bi-eye"></i>

                This is a preview of the information a student will see.
                Student access is still controlled by the scenario's
                publication status and availability period.

            </div>


            <div class="mb-3">

                <span class="badge bg-primary">
                    <?= htmlspecialchars($scenario['category']) ?>
                </span>

                <span class="badge bg-secondary">
                    <?= htmlspecialchars($scenario['difficulty']) ?>
                </span>

                <span class="badge bg-info text-dark">
                    <?= (int) $scenario['estimated_time'] ?> mins
                </span>

            </div>


            <h3 class="fw-bold">

                <?= htmlspecialchars(
                    $scenario['scenario_title']
                ) ?>

            </h3>


            <hr>


            <h5 class="fw-bold">
                Troubleshooting Instructions
            </h5>


            <div class="p-3 bg-light rounded">

                <?= nl2br(
                    htmlspecialchars(
                        $scenario['instructions']
                    )
                ) ?>

            </div>


            <?php if (!empty($scenario['expected_outcome'])): ?>

                <h5 class="fw-bold mt-4">
                    Expected Outcome
                </h5>

                <div class="p-3 bg-light rounded">

                    <?= nl2br(
                        htmlspecialchars(
                            $scenario['expected_outcome']
                        )
                    ) ?>

                </div>

            <?php endif; ?>


            <h5 class="fw-bold mt-4">
                Required Network Devices
            </h5>


            <?php if (empty($scenarioDevices)): ?>

                <p class="text-muted">
                    No devices have been configured yet.
                </p>

            <?php else: ?>

                <div class="row">

                    <?php foreach ($scenarioDevices as $device): ?>

                        <div class="col-md-6 col-xl-4 mb-3">

                            <div class="border rounded p-3">

                                <div class="fw-bold">

                                    <?= htmlspecialchars(
                                        $device['device_name']
                                    ) ?>

                                </div>

                                <small class="text-muted">

                                    <?= htmlspecialchars(
                                        $device['device_type']
                                    ) ?>

                                    · Quantity:

                                    <?= (int) $device['quantity'] ?>

                                </small>


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


</div>


<?php require_once '../includes/layout_end.php'; ?>