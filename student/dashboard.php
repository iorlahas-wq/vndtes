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

$pageTitle = 'Student Dashboard';

$studentUserId = currentUserId();

$student = null;

$publishedExercises = 0;
$myScenarios = 0;
$myDevices = 0;
$myFaults = 0;


/*
|--------------------------------------------------------------------------
| Load Student Information
|--------------------------------------------------------------------------
|
| Student records are linked to users through user_id.
|
*/

$stmt = db()->prepare("
    SELECT
        u.user_id,
        u.full_name,
        s.student_id,
        s.matric_no
    FROM users u
    INNER JOIN students s
        ON s.user_id = u.user_id
    WHERE u.user_id = ?
    LIMIT 1
");

$stmt->execute([
    $studentUserId
]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Student Record Fallback
|--------------------------------------------------------------------------
*/

if (!$student) {

    $stmt = db()->prepare("
        SELECT
            user_id,
            full_name
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $studentUserId
    ]);

    $student = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'user_id'    => $studentUserId,
        'full_name'  => 'Student',
        'student_id' => null,
        'matric_no'  => null
    ];
}


/*
|--------------------------------------------------------------------------
| Published Training Exercises
|--------------------------------------------------------------------------
|
| Only Published scenarios are available to students.
|
*/

$stmt = db()->query("
    SELECT COUNT(*)
    FROM scenarios
    WHERE status = 'Published'
");

$publishedExercises = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Scenario Device Count
|--------------------------------------------------------------------------
|
| This represents configured scenario-device mappings in the system.
| It is displayed as system information, not as student activity.
|
*/

$stmt = db()->query("
    SELECT COUNT(*)
    FROM scenario_devices sd
    INNER JOIN scenarios s
        ON s.scenario_id = sd.scenario_id
    WHERE s.status = 'Published'
");

$myDevices = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Scenario Fault Count
|--------------------------------------------------------------------------
|
| Count faults attached to published scenarios.
|
*/

$stmt = db()->query("
    SELECT COUNT(*)
    FROM scenario_faults sf
    INNER JOIN scenarios s
        ON s.scenario_id = sf.scenario_id
    WHERE s.status = 'Published'
");

$myFaults = (int) $stmt->fetchColumn();


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
                Student Dashboard
            </h2>

            <p class="text-muted mb-0">
                Welcome to the Virtual Network Diagnosis and Training Environment System.
            </p>

        </div>

    </div>



    <!-- ============================================================
         WELCOME CARD
    ============================================================= -->

    <div class="card dashboard-card mb-4">

        <div class="card-body">

            <div class="row align-items-center">

                <div class="col-lg-8">

                    <div class="d-flex align-items-center gap-3">

                        <div
                            class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                            style="width:65px;height:65px;"
                        >

                            <i class="bi bi-person-fill fs-3"></i>

                        </div>


                        <div>

                            <h4 class="fw-bold mb-1">

                                Welcome,
                                <?= htmlspecialchars(
                                    $student['full_name'] ?? 'Student'
                                ) ?>

                            </h4>


                            <?php if (!empty($student['matric_no'])): ?>

                                <div class="text-muted">

                                    Matriculation Number:

                                    <strong>
                                        <?= htmlspecialchars(
                                            $student['matric_no']
                                        ) ?>
                                    </strong>

                                </div>

                            <?php else: ?>

                                <div class="text-muted">
                                    Student account
                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">

                    <a
                        href="exercises.php"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-play-circle"></i>

                        Available Exercises

                    </a>

                </div>

            </div>

        </div>

    </div>



    <!-- ============================================================
         TRAINING STATISTICS
    ============================================================= -->

    <div class="row g-4 mb-4">


        <!-- AVAILABLE EXERCISES -->

        <div class="col-md-6 col-xl-4">

            <div class="card dashboard-card h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <div class="text-muted small">
                                Available Exercises
                            </div>

                            <div class="fs-2 fw-bold">
                                <?= $publishedExercises ?>
                            </div>

                        </div>

                        <div
                            class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center"
                            style="width:55px;height:55px;"
                        >

                            <i class="bi bi-journal-code fs-4"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- CONFIGURED DEVICES -->

        <div class="col-md-6 col-xl-4">

            <div class="card dashboard-card h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <div class="text-muted small">
                                Training Devices
                            </div>

                            <div class="fs-2 fw-bold">
                                <?= $myDevices ?>
                            </div>

                        </div>

                        <div
                            class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center"
                            style="width:55px;height:55px;"
                        >

                            <i class="bi bi-router fs-4"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- TRAINING FAULTS -->

        <div class="col-md-6 col-xl-4">

            <div class="card dashboard-card h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <div class="text-muted small">
                                Training Faults
                            </div>

                            <div class="fs-2 fw-bold">
                                <?= $myFaults ?>
                            </div>

                        </div>

                        <div
                            class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center"
                            style="width:55px;height:55px;"
                        >

                            <i class="bi bi-bug fs-4"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>



    <!-- ============================================================
         TRAINING AREA
    ============================================================= -->

    <div class="row g-4">


        <!-- AVAILABLE TRAINING -->

        <div class="col-lg-8">

            <div class="card dashboard-card h-100">

                <div class="card-header bg-primary text-white">

                    <i class="bi bi-mortarboard-fill"></i>

                    Network Training

                </div>


                <div class="card-body">

                    <p class="mb-4">

                        Use the training exercises provided by your lecturer
                        to practise network diagnosis and troubleshooting.

                        Each exercise is based on a network scenario containing
                        devices and one or more networking faults.

                    </p>


                    <div class="row g-3">


                        <div class="col-md-4">

                            <div class="border rounded p-3 h-100">

                                <i class="bi bi-diagram-3 text-primary fs-3"></i>

                                <h6 class="fw-bold mt-2">
                                    Network Scenarios
                                </h6>

                                <p class="small text-muted mb-0">

                                    Work with practical networking situations
                                    prepared for troubleshooting exercises.

                                </p>

                            </div>

                        </div>


                        <div class="col-md-4">

                            <div class="border rounded p-3 h-100">

                                <i class="bi bi-router text-success fs-3"></i>

                                <h6 class="fw-bold mt-2">
                                    Network Devices
                                </h6>

                                <p class="small text-muted mb-0">

                                    Examine the devices configured for each
                                    training scenario.

                                </p>

                            </div>

                        </div>


                        <div class="col-md-4">

                            <div class="border rounded p-3 h-100">

                                <i class="bi bi-bug text-danger fs-3"></i>

                                <h6 class="fw-bold mt-2">
                                    Fault Diagnosis
                                </h6>

                                <p class="small text-muted mb-0">

                                    Identify and troubleshoot networking
                                    problems introduced into exercises.

                                </p>

                            </div>

                        </div>

                    </div>


                    <div class="mt-4">

                        <a
                            href="exercises.php"
                            class="btn btn-primary"
                        >

                            <i class="bi bi-arrow-right-circle"></i>

                            View Available Exercises

                        </a>

                    </div>

                </div>

            </div>

        </div>



        <!-- QUICK INFORMATION -->

        <div class="col-lg-4">

            <div class="card dashboard-card h-100">

                <div class="card-header bg-dark text-white">

                    <i class="bi bi-info-circle-fill"></i>

                    How Training Works

                </div>


                <div class="card-body">

                    <div class="d-flex gap-3 mb-3">

                        <div class="text-primary fw-bold">
                            1
                        </div>

                        <div>

                            <strong>
                                Select an Exercise
                            </strong>

                            <div class="small text-muted">
                                Choose an available network troubleshooting scenario.
                            </div>

                        </div>

                    </div>


                    <div class="d-flex gap-3 mb-3">

                        <div class="text-primary fw-bold">
                            2
                        </div>

                        <div>

                            <strong>
                                Study the Network
                            </strong>

                            <div class="small text-muted">
                                Review the scenario instructions and configured devices.
                            </div>

                        </div>

                    </div>


                    <div class="d-flex gap-3 mb-3">

                        <div class="text-primary fw-bold">
                            3
                        </div>

                        <div>

                            <strong>
                                Diagnose the Fault
                            </strong>

                            <div class="small text-muted">
                                Investigate the networking problem presented in the exercise.
                            </div>

                        </div>

                    </div>


                    <div class="d-flex gap-3">

                        <div class="text-primary fw-bold">
                            4
                        </div>

                        <div>

                            <strong>
                                Submit Your Work
                            </strong>

                            <div class="small text-muted">
                                Complete the troubleshooting and assessment process.
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


</div>


<?php require_once '../includes/layout_end.php'; ?>