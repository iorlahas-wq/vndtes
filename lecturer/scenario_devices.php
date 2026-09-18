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

$scenarioId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

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
|
| IMPORTANT:
| A lecturer can only manage devices belonging to a scenario created
| by that lecturer.
|
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT
        scenario_id,
        scenario_code,
        scenario_title,
        category,
        difficulty,
        estimated_time,
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
| Scenario Not Found / Not Owned
|--------------------------------------------------------------------------
*/

if (!$scenario) {
    redirect('scenarios.php');
}


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$success = '';
$error   = '';


/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Add Device
    |--------------------------------------------------------------------------
    */

    if ($action === 'add_device') {

        $deviceId = isset($_POST['device_id'])
            ? (int) $_POST['device_id']
            : 0;

        $quantity = isset($_POST['quantity'])
            ? (int) $_POST['quantity']
            : 0;

        $notes = trim($_POST['notes'] ?? '');


        /*
        |--------------------------------------------------------------------------
        | Validate
        |--------------------------------------------------------------------------
        */

        if ($deviceId <= 0) {

            $error = "Please select a device.";

        } elseif ($quantity <= 0) {

            $error = "Device quantity must be at least 1.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | Confirm Device Exists
            |--------------------------------------------------------------------------
            */

            $deviceStmt = db()->prepare("
                SELECT
                    device_id,
                    device_name,
                    device_code
                FROM devices
                WHERE device_id = ?
                  AND status = 'Active'
                LIMIT 1
            ");

            $deviceStmt->execute([$deviceId]);

            $device = $deviceStmt->fetch(PDO::FETCH_ASSOC);


            if (!$device) {

                $error = "The selected device is not available.";

            } else {


                /*
                |--------------------------------------------------------------------------
                | Prevent Duplicate Mapping
                |--------------------------------------------------------------------------
                */

                $checkStmt = db()->prepare("
                    SELECT scenario_device_id
                    FROM scenario_devices
                    WHERE scenario_id = ?
                      AND device_id = ?
                    LIMIT 1
                ");

                $checkStmt->execute([
                    $scenarioId,
                    $deviceId
                ]);

                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);


                if ($existing) {

                    $error = "This device is already assigned to this scenario. Edit the existing assignment instead.";

                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | Insert Mapping
                    |--------------------------------------------------------------------------
                    */

                    $insertStmt = db()->prepare("
                        INSERT INTO scenario_devices
                        (
                            scenario_id,
                            device_id,
                            quantity,
                            notes
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?
                        )
                    ");

                    $insertStmt->execute([
                        $scenarioId,
                        $deviceId,
                        $quantity,
                        $notes !== '' ? $notes : null
                    ]);


                    $success = "Device assigned to the scenario successfully.";
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Device
    |--------------------------------------------------------------------------
    */

    if ($action === 'remove_device') {

        $mappingId = isset($_POST['mapping_id'])
            ? (int) $_POST['mapping_id']
            : 0;


        if ($mappingId <= 0) {

            $error = "Invalid device assignment.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | Ownership Check Before Delete
            |--------------------------------------------------------------------------
            */

            $deleteStmt = db()->prepare("
                DELETE sd
                FROM scenario_devices sd
                INNER JOIN scenarios s
                    ON s.scenario_id = sd.scenario_id
                WHERE sd.scenario_device_id = ?
                  AND sd.scenario_id = ?
                  AND s.created_by = ?
            ");

            $deleteStmt->execute([
                $mappingId,
                $scenarioId,
                $userId
            ]);


            if ($deleteStmt->rowCount() > 0) {

                $success = "Device removed from the scenario successfully.";

            } else {

                $error = "The device assignment could not be removed.";
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load Active Device Library
|--------------------------------------------------------------------------
*/

$deviceStmt = db()->query("
    SELECT
        device_id,
        device_code,
        device_name,
        device_type,
        vendor,
        model
    FROM devices
    WHERE status = 'Active'
    ORDER BY device_name ASC
");

$devices = $deviceStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load Configured Devices
|--------------------------------------------------------------------------
*/

$mappingStmt = db()->prepare("
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

    ORDER BY d.device_name ASC
");

$mappingStmt->execute([$scenarioId]);

$assignedDevices = $mappingStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = "Scenario Devices";

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">


    <!-- ==============================================================
         PAGE HEADER
         ============================================================== -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">
                Scenario Devices
            </h2>

            <p class="text-muted mb-0">
                Configure the network equipment required for this scenario.
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



    <!-- ==============================================================
         SCENARIO SUMMARY
         ============================================================== -->

    <div class="card dashboard-card mb-4">

        <div class="card-body">

            <div class="row align-items-center">

                <div class="col-md-8">

                    <div class="d-flex flex-wrap gap-2 mb-2">

                        <span class="badge bg-primary">

                            <?= htmlspecialchars($scenario['category']) ?>

                        </span>


                        <span class="badge bg-secondary">

                            <?= htmlspecialchars($scenario['difficulty']) ?>

                        </span>


                        <span class="badge bg-info text-dark">

                            <?= (int) $scenario['estimated_time'] ?> mins

                        </span>


                        <span class="badge bg-light text-dark">

                            <?= htmlspecialchars($scenario['status']) ?>

                        </span>

                    </div>


                    <h4 class="fw-bold mb-1">

                        <?= htmlspecialchars(
                            $scenario['scenario_title']
                        ) ?>

                    </h4>


                    <div class="text-muted">

                        Scenario Code:

                        <strong>

                            <?= htmlspecialchars(
                                $scenario['scenario_code']
                            ) ?>

                        </strong>

                    </div>

                </div>


                <div class="col-md-4 text-md-end mt-3 mt-md-0">

                    <div class="text-muted">
                        Configured Devices
                    </div>

                    <span class="badge bg-primary fs-6">

                        <?= count($assignedDevices) ?>

                    </span>

                </div>

            </div>

        </div>

    </div>



    <!-- ==============================================================
         ALERTS
         ============================================================== -->

    <?php if ($success !== ''): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-check-circle"></i>

            <?= htmlspecialchars($success) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-exclamation-triangle"></i>

            <?= htmlspecialchars($error) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>



    <!-- ==============================================================
         DEVICE MANAGEMENT
         ============================================================== -->

    <div class="card dashboard-card">

        <div class="card-header bg-dark text-white">

            <h5 class="mb-0">

                <i class="bi bi-router"></i>

                Scenario Devices

            </h5>

        </div>


        <div class="card-body">

            <div class="row g-4">


                <!-- ==================================================
                     ADD DEVICE
                     ================================================== -->

                <div class="col-lg-5">

                    <div class="border rounded p-4 h-100">

                        <h4 class="fw-bold mb-4">

                            <i class="bi bi-plus-circle"></i>

                            Add Device

                        </h4>


                        <form method="POST">

                            <input
                                type="hidden"
                                name="action"
                                value="add_device"
                            >


                            <!-- Device -->

                            <div class="mb-3">

                                <label class="form-label fw-semibold">

                                    Device

                                </label>


                                <select
                                    name="device_id"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        Select Device
                                    </option>


                                    <?php foreach ($devices as $device): ?>

                                        <option
                                            value="<?= (int) $device['device_id'] ?>"
                                        >

                                            <?= htmlspecialchars(
                                                $device['device_code']
                                            ) ?>

                                            -

                                            <?= htmlspecialchars(
                                                $device['device_name']
                                            ) ?>

                                            (<?= htmlspecialchars(
                                                $device['device_type']
                                            ) ?>)

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>



                            <!-- Quantity -->

                            <div class="mb-3">

                                <label class="form-label fw-semibold">

                                    Quantity

                                </label>


                                <input
                                    type="number"
                                    name="quantity"
                                    class="form-control"
                                    value="1"
                                    min="1"
                                    max="100"
                                    required
                                >

                            </div>



                            <!-- Notes -->

                            <div class="mb-3">

                                <label class="form-label fw-semibold">

                                    Notes

                                </label>


                                <textarea
                                    name="notes"
                                    class="form-control"
                                    rows="5"
                                    placeholder="Explain how this device is used in this scenario..."
                                ></textarea>


                                <div class="form-text">

                                    Describe the role of the device in the
                                    practical exercise.

                                </div>

                            </div>



                            <button
                                type="submit"
                                class="btn btn-dark w-100"
                            >

                                <i class="bi bi-plus-circle"></i>

                                Add Device

                            </button>

                        </form>

                    </div>

                </div>



                <!-- ==================================================
                     ASSIGNED DEVICES
                     ================================================== -->

                <div class="col-lg-7">

                    <div class="border rounded p-4 h-100">

                        <div
                            class="d-flex justify-content-between
                                   align-items-center mb-4"
                        >

                            <h4 class="fw-bold mb-0">

                                Configured Devices

                            </h4>


                            <span class="badge bg-primary fs-6">

                                <?= count($assignedDevices) ?>

                            </span>

                        </div>


                        <?php if (empty($assignedDevices)): ?>

                            <div class="text-center py-5">

                                <i
                                    class="bi bi-router"
                                    style="
                                        font-size: 3rem;
                                        color: #6c757d;
                                    "
                                ></i>


                                <h5 class="mt-3">
                                    No devices configured
                                </h5>


                                <p class="text-muted mb-0">

                                    Add the network devices required for this
                                    scenario.

                                </p>

                            </div>

                        <?php else: ?>


                            <div class="table-responsive">

                                <table
                                    class="table table-hover align-middle"
                                >

                                    <thead class="table-light">

                                        <tr>

                                            <th>
                                                Device
                                            </th>

                                            <th>
                                                Type
                                            </th>

                                            <th class="text-center">
                                                Qty
                                            </th>

                                            <th>
                                                Notes
                                            </th>

                                            <th class="text-end">
                                                Action
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>


                                    <?php foreach (
                                        $assignedDevices
                                        as $assigned
                                    ): ?>


                                        <tr>


                                            <td>

                                                <div class="fw-semibold">

                                                    <?= htmlspecialchars(
                                                        $assigned['device_name']
                                                    ) ?>

                                                </div>


                                                <small class="text-muted">

                                                    <?= htmlspecialchars(
                                                        $assigned['device_code']
                                                    ) ?>

                                                </small>

                                            </td>


                                            <td>

                                                <?= htmlspecialchars(
                                                    $assigned['device_type']
                                                ) ?>

                                            </td>


                                            <td class="text-center">

                                                <span class="badge bg-primary">

                                                    <?= (int) $assigned['quantity'] ?>

                                                </span>

                                            </td>


                                            <td>

                                                <?php if (
                                                    trim(
                                                        (string)
                                                        $assigned['notes']
                                                    ) !== ''
                                                ): ?>

                                                    <?= nl2br(
                                                        htmlspecialchars(
                                                            $assigned['notes']
                                                        )
                                                    ) ?>

                                                <?php else: ?>

                                                    <span class="text-muted">
                                                        No notes
                                                    </span>

                                                <?php endif; ?>

                                            </td>


                                            <td class="text-end">

                                                <form
                                                    method="POST"
                                                    onsubmit="return confirm(
                                                        'Remove this device from the scenario?'
                                                    );"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="remove_device"
                                                    >


                                                    <input
                                                        type="hidden"
                                                        name="mapping_id"
                                                        value="<?= (int) $assigned['scenario_device_id'] ?>"
                                                    >


                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Remove Device"
                                                    >

                                                        <i
                                                            class="bi bi-trash"
                                                        ></i>

                                                    </button>

                                                </form>

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

        </div>

    </div>



    <!-- ==============================================================
         NEXT WORKFLOW
         ============================================================== -->

    <div class="card dashboard-card mt-4">

        <div class="card-body">

            <div class="row align-items-center">

                <div class="col-md-8">

                    <h5 class="fw-bold mb-1">

                        <i class="bi bi-bug"></i>

                        Configure Troubleshooting Faults

                    </h5>


                    <p class="text-muted mb-0">

                        After configuring the required equipment, assign the
                        troubleshooting faults that students will investigate
                        during this scenario.

                    </p>

                </div>


                <div class="col-md-4 text-md-end mt-3 mt-md-0">

                    <a
                        href="scenario_faults.php?id=<?= $scenarioId ?>"
                        class="btn btn-outline-danger"
                    >

                        <i class="bi bi-bug"></i>

                        Manage Scenario Faults

                    </a>

                </div>

            </div>

        </div>

    </div>

</div>


<?php require_once '../includes/layout_end.php'; ?>