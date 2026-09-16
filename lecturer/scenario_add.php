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
| Page State
|--------------------------------------------------------------------------
*/

$pageTitle = "Scenario";

$message = "";

$lecturerId = currentUserId();

$scenarioId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

$isEdit = $scenarioId > 0;

$scenario = null;


/*
|--------------------------------------------------------------------------
| Load Existing Scenario
|--------------------------------------------------------------------------
|
| IMPORTANT:
| A lecturer can ONLY access scenarios created by that lecturer.
|
*/

if ($isEdit) {

    $stmt = db()->prepare("
        SELECT
            s.*,
            l.staff_id,
            l.designation,
            l.specialization
        FROM scenarios s
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

    if (!$scenario) {

        $message = alert(
            "Scenario not found or you do not have permission to access it.",
            "danger"
        );

        $isEdit = false;
        $scenarioId = 0;
    }
}


/*
|--------------------------------------------------------------------------
| Default Form Values
|--------------------------------------------------------------------------
*/

$scenarioCode = $scenario['scenario_code'] ?? '';

$scenarioTitle = $scenario['scenario_title'] ?? '';

$category = $scenario['category'] ?? 'Routing';

$difficulty = $scenario['difficulty'] ?? 'Beginner';

$estimatedTime = $scenario['estimated_time'] ?? 30;

$instructions = $scenario['instructions'] ?? '';

$expectedOutcome = $scenario['expected_outcome'] ?? '';

$availableFrom = $scenario['available_from'] ?? '';
$availableUntil = $scenario['available_until'] ?? '';


/*
|--------------------------------------------------------------------------
| Handle Scenario Save
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? 'save_scenario';


    /*
    |--------------------------------------------------------------------------
    | Save Scenario
    |--------------------------------------------------------------------------
    */

    if ($action === 'save_scenario') {

        $postedScenarioId = (int) ($_POST['scenario_id'] ?? 0);

        $scenarioCode = sanitize($_POST['scenario_code'] ?? '');

        $scenarioTitle = sanitize($_POST['scenario_title'] ?? '');

        $category = $_POST['category'] ?? '';

        $difficulty = $_POST['difficulty'] ?? '';

        $estimatedTime = (int) ($_POST['estimated_time'] ?? 30);

        $instructions = trim($_POST['instructions'] ?? '');

        $expectedOutcome = trim($_POST['expected_outcome'] ?? '');

        $availableFrom = trim($_POST['available_from'] ?? '');
        $availableUntil = trim($_POST['available_until'] ?? '');

        $errors = [];


        /*
        |--------------------------------------------------------------------------
        | Validate Category
        |--------------------------------------------------------------------------
        */

        $allowedCategories = [
            'Routing',
            'Switching',
            'Subnetting',
            'Wireless',
            'Security',
            'Network Services',
            'Mixed'
        ];

        if (!in_array($category, $allowedCategories, true)) {
            $errors[] = "Invalid scenario category.";
        }


        /*
        |--------------------------------------------------------------------------
        | Validate Difficulty
        |--------------------------------------------------------------------------
        */

        $allowedDifficulties = [
            'Beginner',
            'Intermediate',
            'Advanced'
        ];

        if (!in_array($difficulty, $allowedDifficulties, true)) {
            $errors[] = "Invalid difficulty level.";
        }


        /*
        |--------------------------------------------------------------------------
        | Required Fields
        |--------------------------------------------------------------------------
        */

        if ($scenarioCode === '') {
            $errors[] = "Scenario Code is required.";
        }

        if ($scenarioTitle === '') {
            $errors[] = "Scenario Title is required.";
        }

        if ($instructions === '') {
            $errors[] = "Instructions are required.";
        }


        /*
        |--------------------------------------------------------------------------
        | Estimated Time
        |--------------------------------------------------------------------------
        */

        if ($estimatedTime <= 0) {
            $errors[] = "Estimated time must be greater than zero.";
        }


        /*
        |--------------------------------------------------------------------------
        | Student Availability Window
        |--------------------------------------------------------------------------
        |
        | Availability is independent of publication status.
        | A lecturer cannot publish a scenario, but can prepare its
        | intended student availability window. The administrator
        | controls publication.
        |
        | Empty dates mean:
        | - available_from: immediately after publication
        | - available_until: no expiry
        |
        */

        $availableFromDb = null;
        $availableUntilDb = null;

        if ($availableFrom !== '') {

            $fromTimestamp = strtotime($availableFrom);

            if ($fromTimestamp === false) {

                $errors[] = "Invalid student availability start date.";

            } else {

                $availableFromDb = date('Y-m-d H:i:s', $fromTimestamp);
            }
        }

        if ($availableUntil !== '') {

            $untilTimestamp = strtotime($availableUntil);

            if ($untilTimestamp === false) {

                $errors[] = "Invalid student availability end date.";

            } else {

                $availableUntilDb = date('Y-m-d H:i:s', $untilTimestamp);
            }
        }

        if (
            $availableFromDb !== null &&
            $availableUntilDb !== null &&
            strtotime($availableUntilDb) < strtotime($availableFromDb)
        ) {

            $errors[] = "Availability end must be on or after availability start.";
        }


        /*
        |--------------------------------------------------------------------------
        | Duplicate Scenario Code
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            if ($postedScenarioId > 0) {

                $stmt = db()->prepare("
                    SELECT COUNT(*)
                    FROM scenarios
                    WHERE scenario_code = ?
                      AND scenario_id != ?
                ");

                $stmt->execute([
                    $scenarioCode,
                    $postedScenarioId
                ]);

            } else {

                $stmt = db()->prepare("
                    SELECT COUNT(*)
                    FROM scenarios
                    WHERE scenario_code = ?
                ");

                $stmt->execute([
                    $scenarioCode
                ]);
            }

            if ((int) $stmt->fetchColumn() > 0) {
                $errors[] = "Scenario Code already exists.";
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Save / Update
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            /*
            |--------------------------------------------------------------------------
            | EDIT EXISTING SCENARIO
            |--------------------------------------------------------------------------
            */

            if ($postedScenarioId > 0) {

                /*
                | Verify ownership again.
                */

                $stmt = db()->prepare("
                    SELECT scenario_id
                    FROM scenarios
                    WHERE scenario_id = ?
                      AND created_by = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $postedScenarioId,
                    $lecturerId
                ]);

                $ownedScenario = $stmt->fetchColumn();

                if (!$ownedScenario) {

                    $message = alert(
                        "You cannot modify this scenario.",
                        "danger"
                    );

                } else {

                    /*
                    | Lecturer cannot publish/archive.
                    | Existing published status is also preserved.
                    */

                    $stmt = db()->prepare("
                        UPDATE scenarios
                        SET
                            scenario_code = ?,
                            scenario_title = ?,
                            category = ?,
                            difficulty = ?,
                            estimated_time = ?,
                            instructions = ?,
                            expected_outcome = ?,
                            available_from = ?,
                            available_until = ?
                        WHERE scenario_id = ?
                          AND created_by = ?
                    ");

                    $stmt->execute([

                        $scenarioCode,

                        $scenarioTitle,

                        $category,

                        $difficulty,

                        $estimatedTime,

                        $instructions,

                        $expectedOutcome,

                        $availableFromDb,

                        $availableUntilDb,

                        $postedScenarioId,

                        $lecturerId
                    ]);


                    redirect(
                        "scenario_add.php?id=" .
                        $postedScenarioId .
                        "&saved=1"
                    );
                }

            }


            /*
            |--------------------------------------------------------------------------
            | CREATE NEW SCENARIO
            |--------------------------------------------------------------------------
            */

            else {

                /*
                | Lecturer-created scenarios ALWAYS begin as Draft.
                */

                $stmt = db()->prepare("
                    INSERT INTO scenarios (
                        scenario_code,
                        scenario_title,
                        category,
                        difficulty,
                        estimated_time,
                        instructions,
                        expected_outcome,
                        available_from,
                        available_until,
                        status,
                        created_by
                    )
                    VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?
                    )
                ");

                $stmt->execute([

                    $scenarioCode,

                    $scenarioTitle,

                    $category,

                    $difficulty,

                    $estimatedTime,

                    $instructions,

                    $expectedOutcome,

                    $availableFromDb,

                    $availableUntilDb,

                    $lecturerId
                ]);

                $newScenarioId = (int) db()->lastInsertId();


                redirect(
                    "scenario_add.php?id=" .
                    $newScenarioId .
                    "&created=1"
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Display Validation Errors
        |--------------------------------------------------------------------------
        */

        if (!empty($errors)) {

            $message = alert(
                implode("<br>", $errors),
                "danger"
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Add / Update Scenario Device
    |--------------------------------------------------------------------------
    */

    if ($action === 'save_device') {

        $postedScenarioId = (int) ($_POST['scenario_id'] ?? 0);

        $deviceId = (int) ($_POST['device_id'] ?? 0);

        $quantity = (int) ($_POST['quantity'] ?? 1);

        $notes = trim($_POST['notes'] ?? '');


        /*
        | Verify scenario ownership.
        */

        $stmt = db()->prepare("
            SELECT scenario_id
            FROM scenarios
            WHERE scenario_id = ?
              AND created_by = ?
            LIMIT 1
        ");

        $stmt->execute([
            $postedScenarioId,
            $lecturerId
        ]);

        if (!$stmt->fetchColumn()) {

            $message = alert(
                "You do not have permission to modify this scenario.",
                "danger"
            );

        } else {

            /*
            | Verify device exists and is active.
            */

            $stmt = db()->prepare("
                SELECT device_id
                FROM devices
                WHERE device_id = ?
                  AND status = 'Active'
                LIMIT 1
            ");

            $stmt->execute([
                $deviceId
            ]);

            if (!$stmt->fetchColumn()) {

                $message = alert(
                    "Selected device is not available.",
                    "danger"
                );

            } else {

                if ($quantity < 1) {
                    $quantity = 1;
                }


                /*
                |--------------------------------------------------------------------------
                | Check whether device is already attached
                |--------------------------------------------------------------------------
                */

                $stmt = db()->prepare("
                    SELECT scenario_device_id
                    FROM scenario_devices
                    WHERE scenario_id = ?
                      AND device_id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $postedScenarioId,
                    $deviceId
                ]);

                $existingDevice = $stmt->fetchColumn();


                if ($existingDevice) {

                    $stmt = db()->prepare("
                        UPDATE scenario_devices
                        SET
                            quantity = ?,
                            notes = ?
                        WHERE scenario_device_id = ?
                    ");

                    $stmt->execute([
                        $quantity,
                        $notes,
                        $existingDevice
                    ]);

                } else {

                    $stmt = db()->prepare("
                        INSERT INTO scenario_devices (
                            scenario_id,
                            device_id,
                            quantity,
                            notes
                        )
                        VALUES (?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $postedScenarioId,
                        $deviceId,
                        $quantity,
                        $notes
                    ]);
                }


                redirect(
                    "scenario_add.php?id=" .
                    $postedScenarioId .
                    "&device_saved=1"
                );
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Scenario Device
    |--------------------------------------------------------------------------
    */

    if ($action === 'remove_device') {

        $postedScenarioId = (int) ($_POST['scenario_id'] ?? 0);

        $scenarioDeviceId = (int) ($_POST['scenario_device_id'] ?? 0);


        /*
        | Verify ownership before deleting.
        */

        $stmt = db()->prepare("
            SELECT sd.scenario_device_id
            FROM scenario_devices sd
            INNER JOIN scenarios s
                ON s.scenario_id = sd.scenario_id
            WHERE sd.scenario_device_id = ?
              AND sd.scenario_id = ?
              AND s.created_by = ?
            LIMIT 1
        ");

        $stmt->execute([
            $scenarioDeviceId,
            $postedScenarioId,
            $lecturerId
        ]);

        if ($stmt->fetchColumn()) {

            $stmt = db()->prepare("
                DELETE FROM scenario_devices
                WHERE scenario_device_id = ?
            ");

            $stmt->execute([
                $scenarioDeviceId
            ]);


            redirect(
                "scenario_add.php?id=" .
                $postedScenarioId .
                "&device_removed=1"
            );

        } else {

            $message = alert(
                "Device assignment could not be removed.",
                "danger"
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| Success Messages
|--------------------------------------------------------------------------
*/

if (isset($_GET['created'])) {

    $message = alert(
        "Scenario created successfully. It is currently saved as Draft.",
        "success"
    );
}

if (isset($_GET['saved'])) {

    $message = alert(
        "Scenario updated successfully.",
        "success"
    );
}

if (isset($_GET['device_saved'])) {

    $message = alert(
        "Scenario device saved successfully.",
        "success"
    );
}

if (isset($_GET['device_removed'])) {

    $message = alert(
        "Scenario device removed successfully.",
        "success"
    );
}


/*
|--------------------------------------------------------------------------
| Load Scenario Devices
|--------------------------------------------------------------------------
*/

$scenarioDevices = [];

if ($scenarioId > 0 && $scenario) {

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
        ORDER BY d.device_name ASC
    ");

    $stmt->execute([
        $scenarioId
    ]);

    $scenarioDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| Load Available Devices
|--------------------------------------------------------------------------
*/

$devices = [];

$stmt = db()->query("
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

$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);


require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

    <!-- ============================================================
         PAGE HEADER
    ============================================================= -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">

                <?= $isEdit ? 'Scenario Details' : 'Create Scenario' ?>

            </h2>

            <p class="text-muted mb-0">

                <?= $isEdit
                    ? 'View, edit and configure your network troubleshooting scenario.'
                    : 'Create a network troubleshooting scenario for student training.'
                ?>

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

        </div>

    </div>


    <?= $message ?>


    <!-- ============================================================
         EXISTING SCENARIO SUMMARY
    ============================================================= -->

    <?php if ($isEdit && $scenario): ?>

        <div class="card dashboard-card mb-4">

            <div class="card-body">

                <div class="row align-items-center">

                    <div class="col-lg-8">

                        <div class="d-flex align-items-center gap-3">

                            <div>

                                <small class="text-muted">
                                    Scenario Code
                                </small>

                                <h4 class="fw-bold mb-0">
                                    <?= htmlspecialchars($scenario['scenario_code']) ?>
                                </h4>

                            </div>


                            <div>

                                <small class="text-muted">
                                    Status
                                </small>

                                <div>

                                    <?php

                                    $status = $scenario['status'];

                                    $statusClass = match ($status) {

                                        'Published' => 'success',

                                        'Archived' => 'secondary',

                                        default => 'warning'

                                    };

                                    ?>

                                    <span class="badge bg-<?= $statusClass ?>">

                                        <?= htmlspecialchars($status) ?>

                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>


                    <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">

                        <small class="text-muted d-block">
                            Created
                        </small>

                        <strong>

                            <?= htmlspecialchars($scenario['created_at']) ?>

                        </strong>

                    </div>

                </div>

            </div>

        </div>

    <?php endif; ?>


    <!-- ============================================================
         SCENARIO INFORMATION
    ============================================================= -->

    <form method="POST">

        <input
            type="hidden"
            name="action"
            value="save_scenario"
        >

        <input
            type="hidden"
            name="scenario_id"
            value="<?= $scenarioId ?>"
        >


        <div class="row">


            <!-- ====================================================
                 LEFT COLUMN
            ===================================================== -->

            <div class="col-lg-6">

                <div class="card dashboard-card mb-4">

                    <div class="card-header bg-primary text-white">

                        <i class="bi bi-diagram-3-fill"></i>

                        Scenario Information

                    </div>


                    <div class="card-body">


                        <!-- Scenario Code -->

                        <div class="mb-3">

                            <label class="form-label fw-semibold">

                                Scenario Code

                            </label>

                            <input
                                type="text"
                                name="scenario_code"
                                class="form-control"
                                placeholder="SCN-001"
                                value="<?= htmlspecialchars($scenarioCode) ?>"
                                required
                            >

                        </div>


                        <!-- Scenario Title -->

                        <div class="mb-3">

                            <label class="form-label fw-semibold">

                                Scenario Title

                            </label>

                            <input
                                type="text"
                                name="scenario_title"
                                class="form-control"
                                value="<?= htmlspecialchars($scenarioTitle) ?>"
                                required
                            >

                        </div>


                        <!-- Category -->

                        <div class="mb-3">

                            <label class="form-label fw-semibold">

                                Category

                            </label>

                            <select
                                name="category"
                                class="form-select"
                                required
                            >

                                <?php

                                $categories = [

                                    'Routing',
                                    'Switching',
                                    'Subnetting',
                                    'Wireless',
                                    'Security',
                                    'Network Services',
                                    'Mixed'

                                ];

                                foreach ($categories as $item):

                                ?>

                                    <option
                                        value="<?= htmlspecialchars($item) ?>"
                                        <?= $category === $item ? 'selected' : '' ?>
                                    >

                                        <?= htmlspecialchars($item) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="row">


                            <!-- Difficulty -->

                            <div class="col-md-6">

                                <div class="mb-3">

                                    <label class="form-label fw-semibold">

                                        Difficulty

                                    </label>

                                    <select
                                        name="difficulty"
                                        class="form-select"
                                    >

                                        <?php

                                        $difficulties = [

                                            'Beginner',
                                            'Intermediate',
                                            'Advanced'

                                        ];

                                        foreach ($difficulties as $item):

                                        ?>

                                            <option
                                                value="<?= htmlspecialchars($item) ?>"
                                                <?= $difficulty === $item ? 'selected' : '' ?>
                                            >

                                                <?= htmlspecialchars($item) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            </div>


                            <!-- Estimated Time -->

                            <div class="col-md-6">

                                <div class="mb-3">

                                    <label class="form-label fw-semibold">

                                        Estimated Time (mins)

                                    </label>

                                    <input
                                        type="number"
                                        name="estimated_time"
                                        class="form-control"
                                        min="1"
                                        value="<?= (int) $estimatedTime ?>"
                                    >

                                </div>

                            </div>

                        </div>


                        <!-- ==================================================
                             STUDENT AVAILABILITY
                        =================================================== -->

                        <div class="border rounded p-3 bg-light mb-3">

                            <h6 class="fw-bold mb-1">

                                <i class="bi bi-calendar-range"></i>

                                Student Availability

                            </h6>

                            <p class="small text-muted mb-3">

                                These dates control when students may access the
                                scenario after an administrator publishes it.
                                Leaving a field blank keeps that boundary open.

                            </p>


                            <div class="row">

                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">

                                        Available From

                                    </label>

                                    <input
                                        type="datetime-local"
                                        name="available_from"
                                        class="form-control"
                                        value="<?= !empty($availableFrom)
                                            ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($availableFrom)))
                                            : '' ?>"
                                    >

                                    <small class="text-muted">

                                        Blank = immediately after publication.

                                    </small>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label fw-semibold">

                                        Available Until

                                    </label>

                                    <input
                                        type="datetime-local"
                                        name="available_until"
                                        class="form-control"
                                        value="<?= !empty($availableUntil)
                                            ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($availableUntil)))
                                            : '' ?>"
                                    >

                                    <small class="text-muted">

                                        Blank = no expiry.

                                    </small>

                                </div>

                            </div>

                        </div>


                        <?php if ($isEdit): ?>

                            <div class="alert alert-light border">

                                <i class="bi bi-info-circle"></i>

                                Lecturer-created scenarios remain in

                                <strong>Draft</strong>

                                until an administrator publishes them.

                            </div>

                        <?php endif; ?>


                    </div>

                </div>

            </div>


            <!-- ====================================================
                 RIGHT COLUMN
            ===================================================== -->

            <div class="col-lg-6">

                <div class="card dashboard-card mb-4">

                    <div class="card-header bg-success text-white">

                        <i class="bi bi-file-text-fill"></i>

                        Scenario Content

                    </div>


                    <div class="card-body">


                        <!-- Instructions -->

                        <div class="mb-3">

                            <label class="form-label fw-semibold">

                                Instructions

                            </label>

                            <textarea
                                name="instructions"
                                rows="9"
                                class="form-control"
                                required
                            ><?= htmlspecialchars($instructions) ?></textarea>

                            <small class="text-muted">

                                Describe the network situation and what the
                                student is expected to troubleshoot.

                            </small>

                        </div>


                        <!-- Expected Outcome -->

                        <div class="mb-3">

                            <label class="form-label fw-semibold">

                                Expected Outcome

                            </label>

                            <textarea
                                name="expected_outcome"
                                rows="6"
                                class="form-control"
                            ><?= htmlspecialchars($expectedOutcome) ?></textarea>

                            <small class="text-muted">

                                Describe the network state or result the
                                student should achieve.

                            </small>

                        </div>


                    </div>

                </div>

            </div>

        </div>


        <!-- ============================================================
             SAVE BUTTON
        ============================================================= -->

        <div class="d-flex justify-content-end gap-2 mb-4">

            <a
                href="scenarios.php"
                class="btn btn-outline-secondary"
            >

                Cancel

            </a>

            <button
                type="submit"
                class="btn btn-primary"
            >

                <i class="bi bi-save-fill"></i>

                <?= $isEdit ? 'Update Scenario' : 'Save Scenario' ?>

            </button>

        </div>

    </form>


    <?php if ($isEdit && $scenario): ?>


        <!-- ============================================================
             SCENARIO DEVICES
        ============================================================= -->

        <div class="card dashboard-card mb-4">

            <div class="card-header bg-dark text-white">

                <i class="bi bi-hdd-network-fill"></i>

                Scenario Devices

            </div>


            <div class="card-body">


                <div class="row">


                    <!-- ==================================================
                         ADD DEVICE
                    =================================================== -->

                    <div class="col-lg-5">

                        <div class="border rounded p-3">

                            <h5 class="fw-bold mb-3">

                                Add Device

                            </h5>


                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="action"
                                    value="save_device"
                                >

                                <input
                                    type="hidden"
                                    name="scenario_id"
                                    value="<?= $scenarioId ?>"
                                >


                                <div class="mb-3">

                                    <label class="form-label">

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


                                <div class="mb-3">

                                    <label class="form-label">

                                        Quantity

                                    </label>

                                    <input
                                        type="number"
                                        name="quantity"
                                        class="form-control"
                                        value="1"
                                        min="1"
                                    >

                                </div>


                                <div class="mb-3">

                                    <label class="form-label">

                                        Notes

                                    </label>

                                    <textarea
                                        name="notes"
                                        rows="3"
                                        class="form-control"
                                        placeholder="Optional device notes..."
                                    ></textarea>

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
                         CURRENT DEVICES
                    =================================================== -->

                    <div class="col-lg-7 mt-4 mt-lg-0">

                        <h5 class="fw-bold mb-3">

                            Configured Devices

                            <span class="badge bg-primary">

                                <?= count($scenarioDevices) ?>

                            </span>

                        </h5>


                        <?php if (empty($scenarioDevices)): ?>

                            <div class="text-center py-5 text-muted">

                                <i
                                    class="bi bi-hdd-network"
                                    style="font-size: 40px;"
                                ></i>

                                <h6 class="mt-3">

                                    No devices configured

                                </h6>

                                <p class="mb-0">

                                    Add the network devices required for
                                    this scenario.

                                </p>

                            </div>

                        <?php else: ?>


                            <div class="table-responsive">

                                <table class="table table-hover align-middle">

                                    <thead>

                                        <tr>

                                            <th>Device</th>

                                            <th>Type</th>

                                            <th>Qty</th>

                                            <th>Notes</th>

                                            <th></th>

                                        </tr>

                                    </thead>


                                    <tbody>

                                        <?php foreach ($scenarioDevices as $device): ?>

                                            <tr>

                                                <td>

                                                    <strong>

                                                        <?= htmlspecialchars(
                                                            $device['device_name']
                                                        ) ?>

                                                    </strong>

                                                    <small class="text-muted d-block">

                                                        <?= htmlspecialchars(
                                                            $device['device_code']
                                                        ) ?>

                                                    </small>

                                                </td>


                                                <td>

                                                    <?= htmlspecialchars(
                                                        $device['device_type']
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <span class="badge bg-primary">

                                                        <?= (int) $device['quantity'] ?>

                                                    </span>

                                                </td>


                                                <td>

                                                    <?= $device['notes']
                                                        ? htmlspecialchars($device['notes'])
                                                        : '<span class="text-muted">—</span>'
                                                    ?>

                                                </td>


                                                <td class="text-end">

                                                    <form
                                                        method="POST"
                                                        onsubmit="return confirm('Remove this device from the scenario?');"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="remove_device"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="scenario_id"
                                                            value="<?= $scenarioId ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="scenario_device_id"
                                                            value="<?= (int) $device['scenario_device_id'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                        >

                                                            <i class="bi bi-trash"></i>

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


        <!-- ============================================================
             SCENARIO PREVIEW
        ============================================================= -->

        <div class="card dashboard-card mb-4">

            <div class="card-header">

                <h5 class="mb-0 fw-bold">

                    <i class="bi bi-eye"></i>

                    Student Scenario Preview

                </h5>

            </div>


            <div class="card-body">

                <div class="mb-4">

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

                    <?= htmlspecialchars($scenario['scenario_title']) ?>

                </h3>


                <hr>


                <h5 class="fw-bold">

                    Troubleshooting Instructions

                </h5>

                <div class="p-3 bg-light rounded">

                    <?= nl2br(
                        htmlspecialchars($scenario['instructions'])
                    ) ?>

                </div>


                <?php if (!empty($scenario['expected_outcome'])): ?>

                    <h5 class="fw-bold mt-4">

                        Expected Outcome

                    </h5>

                    <div class="p-3 bg-light rounded">

                        <?= nl2br(
                            htmlspecialchars($scenario['expected_outcome'])
                        ) ?>

                    </div>

                <?php endif; ?>


                <h5 class="fw-bold mt-4">

                    Student Availability

                </h5>

                <div class="p-3 bg-light rounded">

                    <?php if (!empty($scenario['available_from'])): ?>

                        <div>
                            <strong>From:</strong>
                            <?= htmlspecialchars($scenario['available_from']) ?>
                        </div>

                    <?php else: ?>

                        <div>
                            <strong>From:</strong>
                            Immediately after publication
                        </div>

                    <?php endif; ?>


                    <?php if (!empty($scenario['available_until'])): ?>

                        <div class="mt-1">
                            <strong>Until:</strong>
                            <?= htmlspecialchars($scenario['available_until']) ?>
                        </div>

                    <?php else: ?>

                        <div class="mt-1">
                            <strong>Until:</strong>
                            No expiry
                        </div>

                    <?php endif; ?>

                    <div class="small text-muted mt-2">

                        Students will only see this scenario when it is
                        published and within this availability window.

                    </div>

                </div>


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

                                            <?= htmlspecialchars(
                                                $device['notes']
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


    <?php endif; ?>

</div>


<?php require_once '../includes/layout_end.php'; ?>