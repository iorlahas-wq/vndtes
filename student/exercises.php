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

$pageTitle = 'Available Exercises';

$studentId = null;

/*
|--------------------------------------------------------------------------
| Get Current Student
|--------------------------------------------------------------------------
|
| The logged-in user is stored in users.user_id.
| students.user_id links the student record to that account.
|
*/

$stmt = db()->prepare("
    SELECT
        student_id,
        user_id,
        matric_no,
        current_session_id,
        current_semester,
        academic_status
    FROM students
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([
    currentUserId()
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
                    style="font-size: 60px;"
                ></i>

                <h3 class="fw-bold mt-3">
                    Student Profile Not Found
                </h3>

                <p class="text-muted mb-0">
                    Your student account could not be linked to a student
                    record.
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
| Load Published Scenarios
|--------------------------------------------------------------------------
|
| A scenario must:
|
| 1. Be Published
| 2. Be available now if availability dates are defined
|
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
        s.available_from,
        s.available_until,

        COUNT(DISTINCT sd.scenario_device_id) AS device_count,
        COUNT(DISTINCT sf.scenario_fault_id) AS fault_count

    FROM scenarios s

    LEFT JOIN scenario_devices sd
        ON sd.scenario_id = s.scenario_id

    LEFT JOIN scenario_faults sf
        ON sf.scenario_id = s.scenario_id
        AND sf.is_active = 1

    WHERE s.status = 'Published'

        AND (
            s.available_from IS NULL
            OR s.available_from <= NOW()
        )

        AND (
            s.available_until IS NULL
            OR s.available_until >= NOW()
        )

    GROUP BY
        s.scenario_id,
        s.scenario_code,
        s.scenario_title,
        s.category,
        s.difficulty,
        s.estimated_time,
        s.instructions,
        s.expected_outcome,
        s.available_from,
        s.available_until

    ORDER BY
        s.updated_at DESC
");

$stmt->execute();

$scenarios = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Existing Student Attempts
|--------------------------------------------------------------------------
|
| We use this to tell the student whether an exercise is:
|
| - Not Started
| - In Progress
| - Completed
|
*/

$attempts = [];

$stmt = db()->prepare("
    SELECT
        scenario_id,
        COUNT(*) AS attempt_count,

        MAX(
            CASE
                WHEN status = 'In Progress'
                THEN attempt_id
                ELSE NULL
            END
        ) AS active_attempt_id,

        MAX(
            CASE
                WHEN status = 'Completed'
                THEN completed_at
                ELSE NULL
            END
        ) AS last_completed_at

    FROM scenario_attempts

    WHERE student_id = ?

    GROUP BY scenario_id
");

$stmt->execute([
    $studentId
]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $attempt) {

    $attempts[(int) $attempt['scenario_id']] = $attempt;
}


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
                Available Exercises
            </h2>

            <p class="text-muted mb-0">
                Select a network troubleshooting exercise to begin
                your practical training.
            </p>

        </div>

    </div>


    <!-- ============================================================
         EXERCISES
    ============================================================= -->

    <?php if (empty($scenarios)): ?>

        <div class="card dashboard-card">

            <div class="card-body text-center py-5">

                <i
                    class="bi bi-diagram-3 text-muted"
                    style="font-size: 60px;"
                ></i>

                <h4 class="fw-bold mt-3">
                    No Exercises Available
                </h4>

                <p class="text-muted mb-0">
                    There are currently no published troubleshooting
                    exercises available to you.
                </p>

            </div>

        </div>

    <?php else: ?>

        <div class="row g-4">

            <?php foreach ($scenarios as $scenario): ?>

                <?php

                $scenarioId = (int) $scenario['scenario_id'];

                $attempt = $attempts[$scenarioId] ?? null;

                $statusLabel = 'Not Started';
                $statusClass = 'secondary';

                $buttonText = 'Start Exercise';
                $buttonIcon = 'bi-play-fill';

                $buttonClass = 'btn-primary';

                if ($attempt && !empty($attempt['active_attempt_id'])) {

                    $statusLabel = 'In Progress';
                    $statusClass = 'warning';

                    $buttonText = 'Continue Exercise';
                    $buttonIcon = 'bi-arrow-right-circle-fill';

                    $buttonClass = 'btn-warning';
                }

                elseif (
                    $attempt
                    && (int) $attempt['attempt_count'] > 0
                    && empty($attempt['active_attempt_id'])
                ) {

                    $statusLabel = 'Attempted';
                    $statusClass = 'success';

                    $buttonText = 'Start New Attempt';
                    $buttonIcon = 'bi-arrow-repeat';

                    $buttonClass = 'btn-outline-primary';
                }

                ?>

                <div class="col-xl-4 col-lg-6">

                    <div class="card dashboard-card h-100">

                        <div class="card-body d-flex flex-column">

                            <!-- Scenario badges -->

                            <div class="mb-3">

                                <span class="badge bg-primary">
                                    <?= htmlspecialchars(
                                        $scenario['category']
                                    ) ?>
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


                            <!-- Title -->

                            <h4 class="fw-bold mb-2">

                                <?= htmlspecialchars(
                                    $scenario['scenario_title']
                                ) ?>

                            </h4>


                            <div class="text-muted small mb-3">

                                Scenario Code:

                                <strong>
                                    <?= htmlspecialchars(
                                        $scenario['scenario_code']
                                    ) ?>
                                </strong>

                            </div>


                            <!-- Description -->

                            <div class="mb-4 flex-grow-1">

                                <p class="text-muted mb-0">

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $scenario['instructions']
                                        )
                                    ) ?>

                                </p>

                            </div>


                            <!-- Statistics -->

                            <div class="row g-2 mb-4">

                                <div class="col-6">

                                    <div
                                        class="border rounded p-2 text-center"
                                    >

                                        <div class="fw-bold">

                                            <?= (int) $scenario['device_count'] ?>

                                        </div>

                                        <small class="text-muted">
                                            Devices
                                        </small>

                                    </div>

                                </div>


                                <div class="col-6">

                                    <div
                                        class="border rounded p-2 text-center"
                                    >

                                        <div class="fw-bold">

                                            <?= (int) $scenario['fault_count'] ?>

                                        </div>

                                        <small class="text-muted">
                                            Faults
                                        </small>

                                    </div>

                                </div>

                            </div>


                            <!-- Attempt Status -->

                            <div
                                class="d-flex justify-content-between
                                       align-items-center mb-3"
                            >

                                <span class="text-muted small">
                                    Exercise Status
                                </span>

                                <span
                                    class="badge bg-<?= $statusClass ?>"
                                >

                                    <?= htmlspecialchars(
                                        $statusLabel
                                    ) ?>

                                </span>

                            </div>


                            <!-- Action -->

                            <a
                                href="exercise.php?id=<?= $scenarioId ?>"
                                class="btn <?= $buttonClass ?> w-100"
                            >

                                <i
                                    class="bi <?= $buttonIcon ?>"
                                ></i>

                                <?= htmlspecialchars($buttonText) ?>

                            </a>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>


<?php

require_once '../includes/layout_end.php';