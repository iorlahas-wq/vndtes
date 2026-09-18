<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

/*
|--------------------------------------------------------------------------
| Lecturer Access
|--------------------------------------------------------------------------
*/

if (currentUserRole() !== "Lecturer") {
    redirect(APP_URL);
}


/*
|--------------------------------------------------------------------------
| Scenario ID
|--------------------------------------------------------------------------
*/

$scenarioId = isset($_GET['id'])
    ? (int) $_GET['id']
    : (int) ($_POST['scenario_id'] ?? 0);

if ($scenarioId <= 0) {
    redirect('scenarios.php');
}


/*
|--------------------------------------------------------------------------
| Current Lecturer
|--------------------------------------------------------------------------
*/

$userId = currentUserId();


/*
|--------------------------------------------------------------------------
| Load Lecturer-Owned Scenario
|--------------------------------------------------------------------------
*/

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
    $userId
]);

$scenario = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Security Check
|--------------------------------------------------------------------------
|
| A lecturer cannot delete another lecturer's scenario simply by
| changing the ID in the URL.
|
|--------------------------------------------------------------------------
*/

if (!$scenario) {
    redirect('scenarios.php');
}


/*
|--------------------------------------------------------------------------
| Delete Confirmation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $confirm = $_POST['confirm_delete'] ?? '';


    if ($confirm !== 'YES') {

        redirect(
            'scenario_delete.php?id=' . $scenarioId
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Delete Scenario
    |--------------------------------------------------------------------------
    |
    | scenario_devices and scenario-fault mappings should normally be
    | removed automatically through their foreign-key relationships.
    |
    |--------------------------------------------------------------------------
    */

    try {

        db()->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Delete Scenario
        |--------------------------------------------------------------------------
        */

        $deleteStmt = db()->prepare("
            DELETE FROM scenarios
            WHERE scenario_id = ?
              AND created_by = ?
            LIMIT 1
        ");

        $deleteStmt->execute([
            $scenarioId,
            $userId
        ]);


        if ($deleteStmt->rowCount() !== 1) {

            throw new Exception(
                "The scenario could not be deleted."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */

        db()->commit();


        /*
        |--------------------------------------------------------------------------
        | Return to Scenario Library
        |--------------------------------------------------------------------------
        */

        redirect(
            'scenarios.php?deleted=1'
        );

        exit;


    } catch (Throwable $e) {


        /*
        |--------------------------------------------------------------------------
        | Rollback
        |--------------------------------------------------------------------------
        */

        if (db()->inTransaction()) {
            db()->rollBack();
        }


        /*
        |--------------------------------------------------------------------------
        | Display Error
        |--------------------------------------------------------------------------
        */

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = "Delete Scenario";

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">


    <!-- ==============================================================
         PAGE HEADER
         ============================================================== -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">
                Delete Scenario
            </h2>

            <p class="text-muted mb-0">
                Confirm removal of this networking scenario.
            </p>

        </div>


        <a
            href="scenarios.php"
            class="btn btn-outline-secondary"
        >

            <i class="bi bi-arrow-left"></i>

            Back to My Scenarios

        </a>

    </div>



    <!-- ==============================================================
         ERROR
         ============================================================== -->

    <?php if (!empty($error)): ?>

        <div
            class="alert alert-danger"
            role="alert"
        >

            <i class="bi bi-exclamation-triangle"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>



    <!-- ==============================================================
         DELETE CONFIRMATION
         ============================================================== -->

    <div class="row justify-content-center">

        <div class="col-lg-8">

            <div class="card dashboard-card">

                <div class="card-header bg-danger text-white">

                    <h5 class="mb-0">

                        <i class="bi bi-trash"></i>

                        Confirm Scenario Deletion

                    </h5>

                </div>


                <div class="card-body p-4">


                    <div class="alert alert-warning">

                        <i class="bi bi-exclamation-triangle"></i>

                        <strong>Warning:</strong>

                        Deleting this scenario may also remove its configured
                        devices and scenario-specific fault mappings.

                        This action cannot be undone.

                    </div>



                    <!-- Scenario Details -->

                    <div class="border rounded p-4 mb-4">

                        <div class="row g-3">


                            <div class="col-md-4">

                                <small class="text-muted d-block">

                                    Scenario Code

                                </small>


                                <strong>

                                    <?= htmlspecialchars(
                                        $scenario['scenario_code']
                                    ) ?>

                                </strong>

                            </div>



                            <div class="col-md-8">

                                <small class="text-muted d-block">

                                    Scenario Title

                                </small>


                                <strong>

                                    <?= htmlspecialchars(
                                        $scenario['scenario_title']
                                    ) ?>

                                </strong>

                            </div>



                            <div class="col-md-4">

                                <small class="text-muted d-block">

                                    Category

                                </small>


                                <?= htmlspecialchars(
                                    $scenario['category']
                                ) ?>

                            </div>



                            <div class="col-md-4">

                                <small class="text-muted d-block">

                                    Difficulty

                                </small>


                                <?= htmlspecialchars(
                                    $scenario['difficulty']
                                ) ?>

                            </div>



                            <div class="col-md-4">

                                <small class="text-muted d-block">

                                    Status

                                </small>


                                <span class="badge bg-secondary">

                                    <?= htmlspecialchars(
                                        $scenario['status']
                                    ) ?>

                                </span>

                            </div>

                        </div>

                    </div>



                    <!-- Confirmation Form -->

                    <form method="POST">

                        <input
                            type="hidden"
                            name="scenario_id"
                            value="<?= $scenarioId ?>"
                        >


                        <input
                            type="hidden"
                            name="confirm_delete"
                            value="YES"
                        >


                        <div class="d-flex justify-content-end gap-2">

                            <a
                                href="scenarios.php"
                                class="btn btn-outline-secondary"
                            >

                                <i class="bi bi-x-circle"></i>

                                Cancel

                            </a>


                            <button
                                type="submit"
                                class="btn btn-danger"
                            >

                                <i class="bi bi-trash"></i>

                                Yes, Delete Scenario

                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>


<?php require_once '../includes/layout_end.php'; ?>