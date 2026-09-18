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


$pageTitle = 'Scenario Fault Mapping';

$lecturerId = currentUserId();

$scenarioId = isset($_GET['id'])
    ? (int) $_GET['id']
    : (int) ($_POST['scenario_id'] ?? 0);

$message = '';


/*
|--------------------------------------------------------------------------
| Validate Scenario
|--------------------------------------------------------------------------
|
| A lecturer may only manage faults belonging to a scenario
| created by that lecturer.
|
*/

if ($scenarioId <= 0) {
    redirect('scenarios.php');
}


$stmt = db()->prepare("
    SELECT
        scenario_id,
        scenario_code,
        scenario_title,
        category,
        difficulty,
        status
    FROM scenarios
    WHERE scenario_id = ?
      AND created_by = ?
    LIMIT 1
");

$stmt->execute([
    $scenarioId,
    $lecturerId
]);

$scenario = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$scenario) {

    echo alert(
        'Scenario not found or you do not have permission to manage it.',
        'danger'
    );

    require_once '../includes/layout_start.php';
    ?>

    <div class="container-fluid">

        <div class="text-center py-5">

            <i
                class="bi bi-exclamation-triangle text-warning"
                style="font-size:60px;"
            ></i>

            <h3 class="fw-bold mt-3">
                Scenario Not Found
            </h3>

            <p class="text-muted">
                The requested scenario does not exist or you do not
                have permission to access it.
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
| Handle Add Fault
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Add Fault
    |--------------------------------------------------------------------------
    */

    if ($action === 'add_fault') {

        $faultId = (int) ($_POST['fault_id'] ?? 0);

        $notes = trim($_POST['notes'] ?? '');

        if ($faultId <= 0) {

            $message = alert(
                'Please select a fault.',
                'danger'
            );

        } else {

            /*
            | Verify that the selected fault exists and is active.
            */

            $stmt = db()->prepare("
                SELECT
                    fault_id
                FROM faults
                WHERE fault_id = ?
                  AND status = 'Active'
                LIMIT 1
            ");

            $stmt->execute([
                $faultId
            ]);

            if (!$stmt->fetchColumn()) {

                $message = alert(
                    'Selected fault is not available.',
                    'danger'
                );

            } else {

                /*
                | Prevent duplicate assignment.
                */

                $stmt = db()->prepare("
                    SELECT scenario_fault_id
                    FROM scenario_faults
                    WHERE scenario_id = ?
                      AND fault_id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $scenarioId,
                    $faultId
                ]);

                $existing = $stmt->fetchColumn();


                if ($existing) {

                    $message = alert(
                        'This fault is already assigned to the scenario.',
                        'warning'
                    );

                } else {

                    /*
                    | Determine next display order.
                    */

                    $stmt = db()->prepare("
                        SELECT COALESCE(MAX(display_order), 0) + 1
                        FROM scenario_faults
                        WHERE scenario_id = ?
                    ");

                    $stmt->execute([
                        $scenarioId
                    ]);

                    $displayOrder = (int) $stmt->fetchColumn();


                    $stmt = db()->prepare("
                        INSERT INTO scenario_faults (
                            scenario_id,
                            fault_id,
                            display_order,
                            notes,
                            is_active
                        )
                        VALUES (?, ?, ?, ?, 1)
                    ");

                    $stmt->execute([
                        $scenarioId,
                        $faultId,
                        $displayOrder,
                        $notes !== '' ? $notes : null
                    ]);


                    redirect(
                        "scenario_faults.php?id=" .
                        $scenarioId .
                        "&fault_added=1"
                    );
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Fault
    |--------------------------------------------------------------------------
    */

    if ($action === 'remove_fault') {

        $scenarioFaultId = (int) (
            $_POST['scenario_fault_id'] ?? 0
        );


        /*
        | Ownership check.
        */

        $stmt = db()->prepare("
            SELECT
                sf.scenario_fault_id
            FROM scenario_faults sf

            INNER JOIN scenarios s
                ON s.scenario_id = sf.scenario_id

            WHERE sf.scenario_fault_id = ?
              AND sf.scenario_id = ?
              AND s.created_by = ?

            LIMIT 1
        ");

        $stmt->execute([
            $scenarioFaultId,
            $scenarioId,
            $lecturerId
        ]);


        if ($stmt->fetchColumn()) {

            $stmt = db()->prepare("
                DELETE FROM scenario_faults
                WHERE scenario_fault_id = ?
                  AND scenario_id = ?
            ");

            $stmt->execute([
                $scenarioFaultId,
                $scenarioId
            ]);


            redirect(
                "scenario_faults.php?id=" .
                $scenarioId .
                "&fault_removed=1"
            );

        } else {

            $message = alert(
                'Fault assignment could not be removed.',
                'danger'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Update Fault Assignment
    |--------------------------------------------------------------------------
    */

    if ($action === 'update_fault') {

        $scenarioFaultId = (int) (
            $_POST['scenario_fault_id'] ?? 0
        );

        $notes = trim($_POST['notes'] ?? '');

        $isActive = isset($_POST['is_active'])
            ? 1
            : 0;


        /*
        | Verify ownership.
        */

        $stmt = db()->prepare("
            SELECT
                sf.scenario_fault_id
            FROM scenario_faults sf

            INNER JOIN scenarios s
                ON s.scenario_id = sf.scenario_id

            WHERE sf.scenario_fault_id = ?
              AND sf.scenario_id = ?
              AND s.created_by = ?

            LIMIT 1
        ");

        $stmt->execute([
            $scenarioFaultId,
            $scenarioId,
            $lecturerId
        ]);


        if ($stmt->fetchColumn()) {

            $stmt = db()->prepare("
                UPDATE scenario_faults
                SET
                    notes = ?,
                    is_active = ?
                WHERE scenario_fault_id = ?
                  AND scenario_id = ?
            ");

            $stmt->execute([
                $notes !== '' ? $notes : null,
                $isActive,
                $scenarioFaultId,
                $scenarioId
            ]);


            redirect(
                "scenario_faults.php?id=" .
                $scenarioId .
                "&fault_updated=1"
            );

        } else {

            $message = alert(
                'Fault assignment could not be updated.',
                'danger'
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| Success Messages
|--------------------------------------------------------------------------
*/

if (isset($_GET['fault_added'])) {

    $message = alert(
        'Fault successfully assigned to the scenario.',
        'success'
    );
}


if (isset($_GET['fault_removed'])) {

    $message = alert(
        'Fault assignment removed successfully.',
        'success'
    );
}


if (isset($_GET['fault_updated'])) {

    $message = alert(
        'Fault assignment updated successfully.',
        'success'
    );
}


/*
|--------------------------------------------------------------------------
| Load Assigned Faults
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT
        sf.scenario_fault_id,
        sf.display_order,
        sf.notes,
        sf.is_active,
        sf.created_at,

        f.fault_id,
        f.fault_code,
        f.fault_title,
        f.category,
        f.difficulty,
        f.affected_device_type,
        f.symptoms,
        f.probable_cause,
        f.expected_solution,
        f.estimated_time,
        f.status

    FROM scenario_faults sf

    INNER JOIN faults f
        ON f.fault_id = sf.fault_id

    WHERE sf.scenario_id = ?

    ORDER BY
        sf.display_order ASC,
        f.fault_title ASC
");

$stmt->execute([
    $scenarioId
]);

$assignedFaults = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load Available Faults
|--------------------------------------------------------------------------
|
| Only active faults that have not already been assigned.
|
*/

$stmt = db()->prepare("
    SELECT
        f.fault_id,
        f.fault_code,
        f.fault_title,
        f.category,
        f.difficulty,
        f.affected_device_type

    FROM faults f

    WHERE f.status = 'Active'

      AND NOT EXISTS (
          SELECT 1
          FROM scenario_faults sf
          WHERE sf.scenario_id = ?
            AND sf.fault_id = f.fault_id
      )

    ORDER BY
        f.category ASC,
        f.fault_title ASC
");

$stmt->execute([
    $scenarioId
]);

$availableFaults = $stmt->fetchAll(PDO::FETCH_ASSOC);


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
                Scenario-Fault Mapping
            </h2>

            <p class="text-muted mb-0">
                Assign relevant troubleshooting faults to this
                networking scenario.
            </p>

        </div>


        <div class="d-flex gap-2">

            <a
                href="scenario_view.php?id=<?= $scenarioId ?>"
                class="btn btn-outline-primary"
            >
                <i class="bi bi-eye"></i>
                View Scenario
            </a>

            <a
                href="scenarios.php"
                class="btn btn-outline-secondary"
            >
                <i class="bi bi-arrow-left"></i>
                Back to My Scenarios
            </a>

        </div>

    </div>


    <?= $message ?>


    <!-- ============================================================
         SCENARIO SUMMARY
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

                        <span class="badge bg-secondary">
                            <?= htmlspecialchars(
                                $scenario['difficulty']
                            ) ?>
                        </span>

                        <span class="badge bg-light text-dark">
                            <?= htmlspecialchars(
                                $scenario['status']
                            ) ?>
                        </span>

                    </div>

                    <h3 class="fw-bold mb-1">

                        <?= htmlspecialchars(
                            $scenario['scenario_title']
                        ) ?>

                    </h3>

                    <div class="text-muted">

                        <strong>Code:</strong>

                        <?= htmlspecialchars(
                            $scenario['scenario_code']
                        ) ?>

                    </div>

                </div>


                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">

                    <div class="text-muted">
                        Assigned Faults
                    </div>

                    <span class="badge bg-primary fs-6">

                        <?= count($assignedFaults) ?>

                    </span>

                </div>

            </div>

        </div>

    </div>


    <!-- ============================================================
         MAPPING AREA
    ============================================================= -->

    <div class="card dashboard-card mb-4">

        <div class="card-header bg-dark text-white">

            <i class="bi bi-bug-fill"></i>

            Scenario-Fault Mapping

        </div>


        <div class="card-body">

            <div class="row">


                <!-- ==================================================
                     ADD FAULT
                =================================================== -->

                <div class="col-lg-5">

                    <div class="border rounded p-3">

                        <h5 class="fw-bold mb-3">

                            <i class="bi bi-plus-circle"></i>

                            Assign Fault

                        </h5>


                        <?php if (empty($availableFaults)): ?>

                            <div class="alert alert-info mb-0">

                                <i class="bi bi-info-circle"></i>

                                No additional active faults are
                                available for assignment.

                            </div>

                        <?php else: ?>


                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="action"
                                    value="add_fault"
                                >

                                <input
                                    type="hidden"
                                    name="scenario_id"
                                    value="<?= $scenarioId ?>"
                                >


                                <div class="mb-3">

                                    <label class="form-label fw-semibold">

                                        Fault

                                    </label>

                                    <select
                                        name="fault_id"
                                        class="form-select"
                                        required
                                    >

                                        <option value="">
                                            Select Fault
                                        </option>


                                        <?php foreach (
                                            $availableFaults
                                            as $fault
                                        ): ?>

                                            <option
                                                value="<?= (int) $fault['fault_id'] ?>"
                                            >

                                                <?= htmlspecialchars(
                                                    $fault['fault_code']
                                                ) ?>

                                                -

                                                <?= htmlspecialchars(
                                                    $fault['fault_title']
                                                ) ?>

                                                [<?= htmlspecialchars(
                                                    $fault['affected_device_type']
                                                ) ?>]

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="mb-3">

                                    <label class="form-label fw-semibold">

                                        Mapping Notes

                                    </label>

                                    <textarea
                                        name="notes"
                                        rows="4"
                                        class="form-control"
                                        placeholder="Explain why this fault is relevant to the scenario..."
                                    ></textarea>

                                    <small class="text-muted">

                                        These notes describe the role of the
                                        fault within this particular scenario.

                                    </small>

                                </div>


                                <button
                                    type="submit"
                                    class="btn btn-dark w-100"
                                >

                                    <i class="bi bi-link-45deg"></i>

                                    Assign Fault

                                </button>

                            </form>


                        <?php endif; ?>

                    </div>

                </div>


                <!-- ==================================================
                     ASSIGNED FAULTS
                =================================================== -->

                <div class="col-lg-7 mt-4 mt-lg-0">

                    <h5 class="fw-bold mb-3">

                        Assigned Faults

                        <span class="badge bg-primary">

                            <?= count($assignedFaults) ?>

                        </span>

                    </h5>


                    <?php if (empty($assignedFaults)): ?>

                        <div class="text-center py-5 text-muted">

                            <i
                                class="bi bi-bug"
                                style="font-size:45px;"
                            ></i>

                            <h6 class="mt-3">

                                No faults assigned

                            </h6>

                            <p class="mb-0">

                                Select a troubleshooting fault from
                                the Fault Library to assign it to this
                                scenario.

                            </p>

                        </div>

                    <?php else: ?>


                        <div class="table-responsive">

                            <table class="table table-hover align-middle">

                                <thead>

                                    <tr>

                                        <th>
                                            Fault
                                        </th>

                                        <th>
                                            Category
                                        </th>

                                        <th>
                                            Device
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


                                <?php foreach (
                                    $assignedFaults
                                    as $fault
                                ): ?>

                                    <tr>

                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $fault['fault_title']
                                                ) ?>

                                            </strong>

                                            <small
                                                class="text-muted d-block"
                                            >

                                                <?= htmlspecialchars(
                                                    $fault['fault_code']
                                                ) ?>

                                            </small>

                                        </td>


                                        <td>

                                            <span class="badge bg-info">

                                                <?= htmlspecialchars(
                                                    $fault['category']
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $fault['affected_device_type']
                                            ) ?>

                                        </td>


                                        <td>

                                            <?php if (
                                                (int) $fault['is_active'] === 1
                                            ): ?>

                                                <span
                                                    class="badge bg-success"
                                                >
                                                    Active
                                                </span>

                                            <?php else: ?>

                                                <span
                                                    class="badge bg-secondary"
                                                >
                                                    Inactive
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Remove this fault from the scenario?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="remove_fault"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="scenario_id"
                                                    value="<?= $scenarioId ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="scenario_fault_id"
                                                    value="<?= (int) $fault['scenario_fault_id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Remove Fault"
                                                >

                                                    <i class="bi bi-trash"></i>

                                                </button>

                                            </form>

                                        </td>

                                    </tr>


                                    <?php if (
                                        !empty($fault['notes'])
                                    ): ?>

                                        <tr>

                                            <td
                                                colspan="5"
                                                class="bg-light"
                                            >

                                                <small>

                                                    <strong>
                                                        Mapping Notes:
                                                    </strong>

                                                    <?= htmlspecialchars(
                                                        $fault['notes']
                                                    ) ?>

                                                </small>

                                            </td>

                                        </tr>

                                    <?php endif; ?>


                                <?php endforeach; ?>


                                </tbody>

                            </table>

                        </div>


                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>


    <!-- ============================================================
         FAULT DETAILS
    ============================================================= -->

    <?php if (!empty($assignedFaults)): ?>

        <div class="card dashboard-card mb-4">

            <div class="card-header bg-success text-white">

                <i class="bi bi-bug-fill"></i>

                Assigned Fault Details

            </div>


            <div class="card-body">

                <?php foreach (
                    $assignedFaults
                    as $index => $fault
                ): ?>

                    <div
                        class="border rounded p-3 mb-3"
                    >

                        <div
                            class="d-flex
                                   justify-content-between
                                   align-items-start
                                   mb-3"
                        >

                            <div>

                                <h5 class="fw-bold mb-1">

                                    <?= htmlspecialchars(
                                        $fault['fault_title']
                                    ) ?>

                                </h5>

                                <small class="text-muted">

                                    <?= htmlspecialchars(
                                        $fault['fault_code']
                                    ) ?>

                                    ·

                                    <?= htmlspecialchars(
                                        $fault['category']
                                    ) ?>

                                    ·

                                    <?= htmlspecialchars(
                                        $fault['affected_device_type']
                                    ) ?>

                                </small>

                            </div>


                            <span class="badge bg-primary">

                                Fault <?= $index + 1 ?>

                            </span>

                        </div>


                        <div class="row">


                            <div class="col-md-6">

                                <div class="mb-3">

                                    <strong>
                                        Symptoms
                                    </strong>

                                    <div class="p-2 bg-light rounded mt-1">

                                        <?= !empty(
                                            $fault['symptoms']
                                        )
                                            ? nl2br(
                                                htmlspecialchars(
                                                    $fault['symptoms']
                                                )
                                            )
                                            : '<span class="text-muted">Not specified.</span>'
                                        ?>

                                    </div>

                                </div>


                                <div class="mb-3">

                                    <strong>
                                        Probable Cause
                                    </strong>

                                    <div class="p-2 bg-light rounded mt-1">

                                        <?= !empty(
                                            $fault['probable_cause']
                                        )
                                            ? nl2br(
                                                htmlspecialchars(
                                                    $fault['probable_cause']
                                                )
                                            )
                                            : '<span class="text-muted">Not specified.</span>'
                                        ?>

                                    </div>

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="mb-3">

                                    <strong>
                                        Expected Solution
                                    </strong>

                                    <div class="p-2 bg-light rounded mt-1">

                                        <?= !empty(
                                            $fault['expected_solution']
                                        )
                                            ? nl2br(
                                                htmlspecialchars(
                                                    $fault['expected_solution']
                                                )
                                            )
                                            : '<span class="text-muted">Not specified.</span>'
                                        ?>

                                    </div>

                                </div>


                                <div class="mb-3">

                                    <strong>
                                        Estimated Time
                                    </strong>

                                    <div class="p-2 bg-light rounded mt-1">

                                        <?= (int) $fault['estimated_time'] ?>

                                        minutes

                                    </div>

                                </div>

                            </div>

                        </div>


                        <?php if (!empty($fault['notes'])): ?>

                            <div class="alert alert-info mb-0">

                                <strong>
                                    Scenario Mapping Notes:
                                </strong>

                                <?= htmlspecialchars(
                                    $fault['notes']
                                ) ?>

                            </div>

                        <?php endif; ?>


                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    <?php endif; ?>


</div>


<?php require_once '../includes/layout_end.php'; ?>