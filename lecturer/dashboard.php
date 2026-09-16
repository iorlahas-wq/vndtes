<?php

declare(strict_types=1);

require_once '../includes/init.php';
require_once '../includes/auth.php';

/*
|--------------------------------------------------------------------------
| Lecturer Access Control
|--------------------------------------------------------------------------
*/

if (currentUserRole() !== 'Lecturer') {
    redirect(APP_URL);
}

$pageTitle = "Lecturer Dashboard";

/*
|--------------------------------------------------------------------------
| Current Lecturer
|--------------------------------------------------------------------------
*/

$userId = (int)($_SESSION['user_id'] ?? 0);

$lecturer = null;

if ($userId > 0) {

    $stmt = $pdo->prepare("
        SELECT
            l.lecturer_id,
            l.user_id,
            l.staff_id,
            l.department_id,
            l.designation,
            l.specialization,
            l.employment_status,
            u.full_name,
            u.username,
            u.email
        FROM lecturers l
        INNER JOIN users u
            ON u.user_id = l.user_id
        WHERE l.user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$userId]);

    $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| Safe Lecturer Values
|--------------------------------------------------------------------------
*/

$fullName = $lecturer['full_name']
    ?? ($_SESSION['full_name'] ?? 'Lecturer');

$staffId = $lecturer['staff_id'] ?? 'Not assigned';

$designation = $lecturer['designation'] ?? 'Lecturer';

$specialization = $lecturer['specialization'] ?? 'Not specified';

$employmentStatus = $lecturer['employment_status'] ?? 'Active';

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$myScenarios = 0;
$draftScenarios = 0;
$publishedScenarios = 0;
$archivedScenarios = 0;
$myScenarioDevices = 0;
$totalFaults = 0;


/*
|--------------------------------------------------------------------------
| Lecturer Scenario Statistics
|--------------------------------------------------------------------------
*/

if ($userId > 0) {

    // Total scenarios created by this lecturer
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM scenarios
        WHERE created_by = ?
    ");

    $stmt->execute([$userId]);

    $myScenarios = (int)$stmt->fetchColumn();


    // Draft scenarios
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM scenarios
        WHERE created_by = ?
          AND status = 'Draft'
    ");

    $stmt->execute([$userId]);

    $draftScenarios = (int)$stmt->fetchColumn();


    // Published scenarios
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM scenarios
        WHERE created_by = ?
          AND status = 'Published'
    ");

    $stmt->execute([$userId]);

    $publishedScenarios = (int)$stmt->fetchColumn();


    // Archived scenarios
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM scenarios
        WHERE created_by = ?
          AND status = 'Archived'
    ");

    $stmt->execute([$userId]);

    $archivedScenarios = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Devices Used in Lecturer Scenarios
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM scenario_devices sd
        INNER JOIN scenarios s
            ON s.scenario_id = sd.scenario_id
        WHERE s.created_by = ?
    ");

    $stmt->execute([$userId]);

    $myScenarioDevices = (int)$stmt->fetchColumn();
}


/*
|--------------------------------------------------------------------------
| Fault Library
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM faults
    WHERE status = 'Active'
");

$totalFaults = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Recent Lecturer Scenarios
|--------------------------------------------------------------------------
*/

$recentScenarios = [];

if ($userId > 0) {

    $stmt = $pdo->prepare("
        SELECT
            s.scenario_id,
            s.scenario_code,
            s.scenario_title,
            s.category,
            s.difficulty,
            s.estimated_time,
            s.status,
            s.created_at,

            (
                SELECT COUNT(*)
                FROM scenario_devices sd
                WHERE sd.scenario_id = s.scenario_id
            ) AS device_count

        FROM scenarios s

        WHERE s.created_by = ?

        ORDER BY s.created_at DESC

        LIMIT 5
    ");

    $stmt->execute([$userId]);

    $recentScenarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require_once '../includes/layout_start.php';

?>

<div class="container-fluid py-3">

    <!-- ==========================================================
         PAGE HEADER
    =========================================================== -->

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                Lecturer Dashboard
            </h2>

            <p class="text-muted mb-0">
                Manage network scenarios, faults and student training activities.
            </p>

        </div>

        <div class="mt-2 mt-md-0">

            <a href="scenarios.php" class="btn btn-primary">
                + Create Scenario
            </a>

        </div>

    </div>


    <!-- ==========================================================
         WELCOME CARD
    =========================================================== -->

    <div class="card shadow-sm border-0 mb-4">

        <div class="card-body">

            <div class="row align-items-center">

                <div class="col-md-8">

                    <h4 class="mb-1">
                        Welcome,
                        <?= htmlspecialchars($fullName) ?>
                    </h4>

                    <p class="text-muted mb-0">
                        <?= htmlspecialchars($designation) ?>

                        <?php if ($staffId !== 'Not assigned'): ?>

                            · Staff ID:
                            <strong><?= htmlspecialchars($staffId) ?></strong>

                        <?php endif; ?>

                    </p>

                </div>

                <div class="col-md-4 text-md-end mt-3 mt-md-0">

                    <span class="badge bg-success px-3 py-2">
                        <?= htmlspecialchars($employmentStatus) ?>
                    </span>

                </div>

            </div>

        </div>

    </div>


    <!-- ==========================================================
         STATISTICS
    =========================================================== -->

    <div class="row g-3 mb-4">


        <!-- My Scenarios -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card shadow-sm border-0 h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <div class="text-muted small">
                                My Scenarios
                            </div>

                            <h3 class="mb-0">
                                <?= $myScenarios ?>
                            </h3>

                        </div>

                        <div class="fs-2 text-primary">
                            ◈
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Published -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card shadow-sm border-0 h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <div class="text-muted small">
                                Published
                            </div>

                            <h3 class="mb-0">
                                <?= $publishedScenarios ?>
                            </h3>

                        </div>

                        <div class="fs-2 text-success">
                            ✓
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Drafts -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card shadow-sm border-0 h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <div class="text-muted small">
                                Draft Scenarios
                            </div>

                            <h3 class="mb-0">
                                <?= $draftScenarios ?>
                            </h3>

                        </div>

                        <div class="fs-2 text-warning">
                            ✎
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Fault Library -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card shadow-sm border-0 h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <div class="text-muted small">
                                Active Faults
                            </div>

                            <h3 class="mb-0">
                                <?= $totalFaults ?>
                            </h3>

                        </div>

                        <div class="fs-2 text-danger">
                            ⚠
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ==========================================================
         MAIN DASHBOARD AREA
    =========================================================== -->

    <div class="row g-4">


        <!-- ======================================================
             RECENT SCENARIOS
        ======================================================= -->

        <div class="col-12 col-xl-8">

            <div class="card shadow-sm border-0 h-100">

                <div class="card-header bg-white d-flex justify-content-between align-items-center">

                    <h5 class="mb-0">
                        My Recent Scenarios
                    </h5>

                    <a href="scenarios.php"
                       class="btn btn-sm btn-outline-primary">

                        View All

                    </a>

                </div>


                <div class="card-body p-0">

                    <?php if (empty($recentScenarios)): ?>

                        <div class="text-center py-5 px-3">

                            <div class="fs-1 text-muted mb-2">
                                ◈
                            </div>

                            <h5>
                                No scenarios yet
                            </h5>

                            <p class="text-muted">
                                Create your first network troubleshooting scenario.
                            </p>

                            <a href="scenarios.php"
                               class="btn btn-primary">

                                Create Scenario

                            </a>

                        </div>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table class="table table-hover align-middle mb-0">

                                <thead class="table-light">

                                    <tr>

                                        <th>
                                            Scenario
                                        </th>

                                        <th>
                                            Category
                                        </th>

                                        <th>
                                            Difficulty
                                        </th>

                                        <th>
                                            Devices
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                        <th>
                                            Action
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                <?php foreach ($recentScenarios as $scenario): ?>

                                    <tr>

                                        <td>

                                            <div class="fw-semibold">

                                                <?= htmlspecialchars(
                                                    $scenario['scenario_title']
                                                ) ?>

                                            </div>

                                            <small class="text-muted">

                                                <?= htmlspecialchars(
                                                    $scenario['scenario_code']
                                                ) ?>

                                            </small>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $scenario['category']
                                            ) ?>

                                        </td>


                                        <td>

                                            <?php

                                            $difficultyClass = match (
                                                $scenario['difficulty']
                                            ) {

                                                'Beginner' => 'bg-success',

                                                'Intermediate' => 'bg-warning text-dark',

                                                'Advanced' => 'bg-danger',

                                                default => 'bg-secondary'

                                            };

                                            ?>

                                            <span class="badge <?= $difficultyClass ?>">

                                                <?= htmlspecialchars(
                                                    $scenario['difficulty']
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?= (int)$scenario['device_count'] ?>

                                        </td>


                                        <td>

                                            <?php

                                            $statusClass = match (
                                                $scenario['status']
                                            ) {

                                                'Published' => 'bg-success',

                                                'Draft' => 'bg-warning text-dark',

                                                'Archived' => 'bg-secondary',

                                                default => 'bg-secondary'

                                            };

                                            ?>

                                            <span class="badge <?= $statusClass ?>">

                                                <?= htmlspecialchars(
                                                    $scenario['status']
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <a
                                                href="scenario_view.php?id=<?= (int)$scenario['scenario_id'] ?>"
                                                class="btn btn-sm btn-outline-secondary"
                                            >
                                                View
                                            </a>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- ======================================================
             LECTURER PROFILE / QUICK ACTIONS
        ======================================================= -->

        <div class="col-12 col-xl-4">

            <div class="card shadow-sm border-0 mb-4">

                <div class="card-header bg-white">

                    <h5 class="mb-0">
                        Lecturer Profile
                    </h5>

                </div>

                <div class="card-body">

                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Staff ID
                        </small>

                        <strong>
                            <?= htmlspecialchars($staffId) ?>
                        </strong>

                    </div>


                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Designation
                        </small>

                        <strong>
                            <?= htmlspecialchars($designation) ?>
                        </strong>

                    </div>


                    <div class="mb-3">

                        <small class="text-muted d-block">
                            Specialization
                        </small>

                        <strong>
                            <?= htmlspecialchars($specialization) ?>
                        </strong>

                    </div>


                    <div>

                        <small class="text-muted d-block">
                            Scenario Devices
                        </small>

                        <strong>
                            <?= $myScenarioDevices ?>
                        </strong>

                    </div>

                </div>

            </div>


            <!-- Quick Actions -->

            <div class="card shadow-sm border-0">

                <div class="card-header bg-white">

                    <h5 class="mb-0">
                        Quick Actions
                    </h5>

                </div>

                <div class="card-body">

                    <div class="d-grid gap-2">

                        <a href="scenarios.php"
                           class="btn btn-outline-primary text-start">

                            ◈ &nbsp; Scenario Builder

                        </a>


                        <a href="faults.php"
                           class="btn btn-outline-danger text-start">

                            ⚠ &nbsp; Fault Library

                        </a>


                        <a href="students.php"
                           class="btn btn-outline-success text-start">

                            👥 &nbsp; Student Reports

                        </a>


                        <a href="analytics.php"
                           class="btn btn-outline-dark text-start">

                            ◔ &nbsp; Analytics

                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ==========================================================
         WORK AREA PLACEHOLDERS
    =========================================================== -->

    <div class="row g-4 mt-1">


        <div class="col-12 col-md-4">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <h5>
                        Scenario Builder
                    </h5>

                    <p class="text-muted">

                        Create and configure network troubleshooting
                        scenarios, devices, instructions and expected outcomes.

                    </p>

                    <a href="scenarios.php"
                       class="btn btn-primary btn-sm">

                        Manage Scenarios

                    </a>

                </div>

            </div>

        </div>


        <div class="col-12 col-md-4">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <h5>
                        Student Reports
                    </h5>

                    <p class="text-muted">

                        Review student activity, scenario attempts,
                        troubleshooting performance and results.

                    </p>

                    <a href="students.php"
                       class="btn btn-success btn-sm">

                        Student Reports

                    </a>

                </div>

            </div>

        </div>


        <div class="col-12 col-md-4">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <h5>
                        Analytics
                    </h5>

                    <p class="text-muted">

                        Analyse scenario usage, student performance,
                        common faults and training outcomes.

                    </p>

                    <a href="analytics.php"
                       class="btn btn-dark btn-sm">

                        View Analytics

                    </a>

                </div>

            </div>

        </div>

    </div>

</div>

<?php

require_once '../includes/layout_end.php';

?>