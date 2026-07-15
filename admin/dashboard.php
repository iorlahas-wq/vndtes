<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

if (currentUserRole() !== 'Administrator') {
    redirect(APP_URL);
}

$pageTitle = 'Administrator Dashboard';

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$totalUsers = db()->query("
    SELECT COUNT(*)
    FROM users
    WHERE deleted_at IS NULL
")->fetchColumn();

$totalStudents = db()->query("
    SELECT COUNT(*)
    FROM students
")->fetchColumn();

$totalLecturers = db()->query("
    SELECT COUNT(*)
    FROM lecturers
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| Scenarios Module
| (Temporary until table is created)
|--------------------------------------------------------------------------
*/

$totalScenarios = 0;

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

    <div class="mb-4">

        <h2 class="fw-bold">

            Administrator Dashboard

        </h2>

        <p class="text-muted">

            Welcome back,

            <strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong>

        </p>

    </div>

    <div class="row g-4">

        <!-- Users -->

        <div class="col-lg-3 col-md-6">

            <div class="card shadow-sm border-0 h-100">

                <div class="card-body">

                    <h5>

                        <i class="bi bi-people-fill text-primary"></i>

                        Users

                    </h5>

                    <h2 class="fw-bold">

                        <?= number_format($totalUsers) ?>

                    </h2>

                </div>

            </div>

        </div>

        <!-- Students -->

        <div class="col-lg-3 col-md-6">

            <div class="card shadow-sm border-0 h-100">

                <div class="card-body">

                    <h5>

                        <i class="bi bi-mortarboard-fill text-success"></i>

                        Students

                    </h5>

                    <h2 class="fw-bold">

                        <?= number_format($totalStudents) ?>

                    </h2>

                </div>

            </div>

        </div>

        <!-- Lecturers -->

        <div class="col-lg-3 col-md-6">

            <div class="card shadow-sm border-0 h-100">

                <div class="card-body">

                    <h5>

                        <i class="bi bi-person-workspace text-warning"></i>

                        Lecturers

                    </h5>

                    <h2 class="fw-bold">

                        <?= number_format($totalLecturers) ?>

                    </h2>

                </div>

            </div>

        </div>

        <!-- Scenarios -->

        <div class="col-lg-3 col-md-6">

            <div class="card shadow-sm border-0 h-100">

                <div class="card-body">

                    <h5>

                        <i class="bi bi-router-fill text-danger"></i>

                        Scenarios

                    </h5>

                    <h2 class="fw-bold">

                        <?= number_format($totalScenarios) ?>

                    </h2>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require_once '../includes/layout_end.php'; ?>