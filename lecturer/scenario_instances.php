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
| Device Instance Naming Helper
|--------------------------------------------------------------------------
|
| Converts the device type into a practical topology name prefix.
|
|--------------------------------------------------------------------------
*/

function getInstancePrefix(string $deviceType): string
{
    switch ($deviceType) {

        case 'Router':
            return 'R';

        case 'Switch':
            return 'SW';

        case 'Layer3 Switch':
            return 'L3SW';

        case 'PC':
            return 'PC';

        case 'Server':
            return 'SRV';

        case 'Wireless Router':
            return 'WR';

        case 'Access Point':
            return 'AP';

        case 'Firewall':
            return 'FW';

        case 'Cloud':
            return 'CLOUD';

        default:
            return 'DEV';
    }
}


/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Generate Required Instances
    |--------------------------------------------------------------------------
    */

    if ($action === 'generate_instances') {

        $scenarioDeviceId = isset($_POST['scenario_device_id'])
            ? (int) $_POST['scenario_device_id']
            : 0;


        if ($scenarioDeviceId <= 0) {

            $error = "Invalid scenario device assignment.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Confirm Scenario Device Belongs to Lecturer-Owned Scenario
            |--------------------------------------------------------------------------
            */

            $deviceStmt = db()->prepare("
                SELECT
                    sd.scenario_device_id,
                    sd.scenario_id,
                    sd.quantity,

                    d.device_id,
                    d.device_code,
                    d.device_name,
                    d.device_type,
                    d.vendor,
                    d.model

                FROM scenario_devices sd

                INNER JOIN scenarios s
                    ON s.scenario_id = sd.scenario_id

                INNER JOIN devices d
                    ON d.device_id = sd.device_id

                WHERE sd.scenario_device_id = ?
                  AND sd.scenario_id = ?
                  AND s.created_by = ?

                LIMIT 1
            ");

            $deviceStmt->execute([
                $scenarioDeviceId,
                $scenarioId,
                $userId
            ]);

            $scenarioDevice = $deviceStmt->fetch(PDO::FETCH_ASSOC);


            if (!$scenarioDevice) {

                $error = "The selected device assignment was not found.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Count Existing Instances
                |--------------------------------------------------------------------------
                */

                $countStmt = db()->prepare("
                    SELECT COUNT(*) 
                    FROM scenario_device_instances
                    WHERE scenario_device_id = ?
                ");

                $countStmt->execute([
                    $scenarioDeviceId
                ]);

                $existingCount = (int) $countStmt->fetchColumn();


                $requiredQuantity = (int) $scenarioDevice['quantity'];


                /*
                |--------------------------------------------------------------------------
                | Already Complete
                |--------------------------------------------------------------------------
                */

                if ($existingCount >= $requiredQuantity) {

                    $error = "All required instances for this device have already been created.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Determine How Many Are Missing
                    |--------------------------------------------------------------------------
                    */

                    $missingQuantity =
                        $requiredQuantity - $existingCount;


                    $prefix = getInstancePrefix(
                        $scenarioDevice['device_type']
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | Generate Instances
                    |--------------------------------------------------------------------------
                    */

                    try {

                        db()->beginTransaction();


                        for (
                            $i = 1;
                            $i <= $missingQuantity;
                            $i++
                        ) {

                            /*
                            |--------------------------------------------------------------------------
                            | Find Next Available Instance Number
                            |--------------------------------------------------------------------------
                            */

                            $number = 1;


                            do {

                                $instanceName =
                                    $prefix . $number;


                                $checkNameStmt = db()->prepare("
                                    SELECT instance_id
                                    FROM scenario_device_instances
                                    WHERE scenario_device_id = ?
                                      AND instance_name = ?
                                    LIMIT 1
                                ");

                                $checkNameStmt->execute([
                                    $scenarioDeviceId,
                                    $instanceName
                                ]);


                                $nameExists =
                                    $checkNameStmt->fetchColumn();


                                if ($nameExists) {
                                    $number++;
                                }

                            } while ($nameExists);


                            /*
                            |--------------------------------------------------------------------------
                            | Display Name
                            |--------------------------------------------------------------------------
                            */

                            $displayName =
                                $instanceName .
                                ' - ' .
                                $scenarioDevice['device_name'];


                            /*
                            |--------------------------------------------------------------------------
                            | Insert Instance
                            |--------------------------------------------------------------------------
                            */

                            $insertStmt = db()->prepare("
                                INSERT INTO scenario_device_instances
                                (
                                    scenario_device_id,
                                    instance_name,
                                    display_name,
                                    instance_status
                                )
                                VALUES
                                (
                                    ?,
                                    ?,
                                    ?,
                                    'Active'
                                )
                            ");

                            $insertStmt->execute([
                                $scenarioDeviceId,
                                $instanceName,
                                $displayName
                            ]);
                        }


                        db()->commit();


                        $success =
                            $missingQuantity .
                            " device instance" .
                            ($missingQuantity > 1 ? "s" : "") .
                            " created successfully.";

                    } catch (Throwable $e) {

                        if (db()->inTransaction()) {
                            db()->rollBack();
                        }

                        $error =
                            "The device instances could not be created.";
                    }
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Instance
    |--------------------------------------------------------------------------
    */

    if ($action === 'remove_instance') {

        $instanceId = isset($_POST['instance_id'])
            ? (int) $_POST['instance_id']
            : 0;


        if ($instanceId <= 0) {

            $error = "Invalid device instance.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Ownership-Safe Delete
            |--------------------------------------------------------------------------
            */

            $deleteStmt = db()->prepare("
                DELETE sdi
                FROM scenario_device_instances sdi

                INNER JOIN scenario_devices sd
                    ON sd.scenario_device_id =
                       sdi.scenario_device_id

                INNER JOIN scenarios s
                    ON s.scenario_id =
                       sd.scenario_id

                WHERE sdi.instance_id = ?
                  AND sd.scenario_id = ?
                  AND s.created_by = ?
            ");

            $deleteStmt->execute([
                $instanceId,
                $scenarioId,
                $userId
            ]);


            if ($deleteStmt->rowCount() > 0) {

                $success =
                    "Device instance removed successfully.";

            } else {

                $error =
                    "The device instance could not be removed.";
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load Scenario Device Requirements
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
        d.model,

        COUNT(sdi.instance_id) AS instance_count

    FROM scenario_devices sd

    INNER JOIN devices d
        ON d.device_id = sd.device_id

    LEFT JOIN scenario_device_instances sdi
        ON sdi.scenario_device_id =
           sd.scenario_device_id

    WHERE sd.scenario_id = ?

    GROUP BY
        sd.scenario_device_id,
        sd.quantity,
        sd.notes,
        d.device_id,
        d.device_code,
        d.device_name,
        d.device_type,
        d.vendor,
        d.model

    ORDER BY d.device_name ASC
");

$mappingStmt->execute([
    $scenarioId
]);

$deviceRequirements =
    $mappingStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load All Device Instances
|--------------------------------------------------------------------------
*/

$instanceStmt = db()->prepare("
    SELECT

        sdi.instance_id,
        sdi.scenario_device_id,
        sdi.instance_name,
        sdi.display_name,
        sdi.instance_status,
        sdi.notes,
        sdi.created_at,

        d.device_code,
        d.device_name,
        d.device_type,
        d.vendor,
        d.model

    FROM scenario_device_instances sdi

    INNER JOIN scenario_devices sd
        ON sd.scenario_device_id =
           sdi.scenario_device_id

    INNER JOIN devices d
        ON d.device_id =
           sd.device_id

    WHERE sd.scenario_id = ?

    ORDER BY
        d.device_type ASC,
        sdi.instance_name ASC
");

$instanceStmt->execute([
    $scenarioId
]);

$instances =
    $instanceStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalRequired = 0;
$totalInstances = count($instances);

foreach ($deviceRequirements as $requirement) {

    $totalRequired +=
        (int) $requirement['quantity'];
}


$completionPercentage = 0;

if ($totalRequired > 0) {

    $completionPercentage =
        min(
            100,
            round(
                ($totalInstances / $totalRequired) * 100
            )
        );
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = "Scenario Device Instances";

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">


    <!-- ==============================================================
         PAGE HEADER
         ============================================================== -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">
                Device Instances
            </h2>

            <p class="text-muted mb-0">
                Create the actual network devices used in this scenario.
            </p>

        </div>


        <div class="d-flex gap-2">

            <a
                href="scenario_devices.php?id=<?= $scenarioId ?>"
                class="btn btn-outline-primary"
            >

                <i class="bi bi-router"></i>

                Scenario Devices

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


                        <span class="badge bg-light text-dark">
                            <?= htmlspecialchars(
                                $scenario['status']
                            ) ?>
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

                    <div class="text-muted mb-1">
                        Topology Instance Progress
                    </div>


                    <div class="fw-bold fs-4">

                        <?= $totalInstances ?>
                        /
                        <?= $totalRequired ?>

                    </div>


                    <div class="progress mt-2">

                        <div
                            class="progress-bar"
                            role="progressbar"
                            style="width: <?= $completionPercentage ?>%;"
                            aria-valuenow="<?= $completionPercentage ?>"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <?= $completionPercentage ?>%
                        </div>

                    </div>

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
         REQUIRED DEVICES
         ============================================================== -->

    <div class="card dashboard-card mb-4">

        <div class="card-header bg-dark text-white">

            <h5 class="mb-0">

                <i class="bi bi-diagram-3"></i>

                Required Device Instances

            </h5>

        </div>


        <div class="card-body">

            <?php if (empty($deviceRequirements)): ?>

                <div class="text-center py-5">

                    <i
                        class="bi bi-router"
                        style="
                            font-size: 3rem;
                            color: #6c757d;
                        "
                    ></i>


                    <h5 class="mt-3">
                        No scenario devices configured
                    </h5>


                    <p class="text-muted mb-3">

                        Configure the devices required by this scenario
                        before creating device instances.

                    </p>


                    <a
                        href="scenario_devices.php?id=<?= $scenarioId ?>"
                        class="btn btn-dark"
                    >

                        <i class="bi bi-router"></i>

                        Configure Scenario Devices

                    </a>

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
                                    Required
                                </th>

                                <th class="text-center">
                                    Created
                                </th>

                                <th class="text-center">
                                    Status
                                </th>

                                <th class="text-end">
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach (
                            $deviceRequirements
                            as $requirement
                        ): ?>

                            <?php

                            $required =
                                (int)
                                $requirement['quantity'];

                            $created =
                                (int)
                                $requirement['instance_count'];

                            $complete =
                                $created >= $required;

                            ?>

                            <tr>

                                <td>

                                    <div class="fw-semibold">

                                        <?= htmlspecialchars(
                                            $requirement['device_name']
                                        ) ?>

                                    </div>


                                    <small class="text-muted">

                                        <?= htmlspecialchars(
                                            $requirement['device_code']
                                        ) ?>

                                        <?php if (
                                            !empty(
                                                $requirement['model']
                                            )
                                        ): ?>

                                            —
                                            <?= htmlspecialchars(
                                                $requirement['model']
                                            ) ?>

                                        <?php endif; ?>

                                    </small>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $requirement['device_type']
                                    ) ?>

                                </td>


                                <td class="text-center">

                                    <span class="badge bg-secondary">

                                        <?= $required ?>

                                    </span>

                                </td>


                                <td class="text-center">

                                    <span
                                        class="badge
                                        <?= $complete
                                            ? 'bg-success'
                                            : 'bg-warning text-dark'
                                        ?>"
                                    >

                                        <?= $created ?>

                                    </span>

                                </td>


                                <td class="text-center">

                                    <?php if ($complete): ?>

                                        <span
                                            class="badge bg-success"
                                        >

                                            <i
                                                class="bi bi-check-circle"
                                            ></i>

                                            Complete

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="badge bg-warning text-dark"
                                        >

                                            <?= $required - $created ?>

                                            remaining

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td class="text-end">

                                    <?php if (!$complete): ?>

                                        <form
                                            method="POST"
                                            class="d-inline"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="generate_instances"
                                            >


                                            <input
                                                type="hidden"
                                                name="scenario_device_id"
                                                value="<?= (int)
                                                    $requirement[
                                                        'scenario_device_id'
                                                    ] ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-dark"
                                            >

                                                <i
                                                    class="bi bi-plus-circle"
                                                ></i>

                                                Generate
                                                <?= $required - $created ?>

                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span class="text-success">

                                            <i
                                                class="bi bi-check-circle"
                                            ></i>

                                            Ready

                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>



    <!-- ==============================================================
         ACTUAL DEVICE INSTANCES
         ============================================================== -->

    <div class="card dashboard-card">

        <div class="card-header bg-primary text-white">

            <div
                class="d-flex justify-content-between
                       align-items-center"
            >

                <h5 class="mb-0">

                    <i class="bi bi-hdd-network"></i>

                    Created Device Instances

                </h5>


                <span class="badge bg-light text-dark">

                    <?= $totalInstances ?>

                </span>

            </div>

        </div>


        <div class="card-body">

            <?php if (empty($instances)): ?>

                <div class="text-center py-5">

                    <i
                        class="bi bi-hdd-network"
                        style="
                            font-size: 3rem;
                            color: #6c757d;
                        "
                    ></i>


                    <h5 class="mt-3">
                        No device instances created
                    </h5>


                    <p class="text-muted mb-0">

                        Generate the required device instances above
                        to begin building the network topology.

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
                                    Instance
                                </th>

                                <th>
                                    Device
                                </th>

                                <th>
                                    Type
                                </th>

                                <th>
                                    Vendor / Model
                                </th>

                                <th>
                                    Status
                                </th>

                                <th class="text-end">
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach (
                            $instances
                            as $instance
                        ): ?>

                            <tr>

                                <td>

                                    <div class="fw-bold">

                                        <?= htmlspecialchars(
                                            $instance[
                                                'instance_name'
                                            ]
                                        ) ?>

                                    </div>


                                    <?php if (
                                        !empty(
                                            $instance[
                                                'display_name'
                                            ]
                                        )
                                    ): ?>

                                        <small
                                            class="text-muted"
                                        >

                                            <?= htmlspecialchars(
                                                $instance[
                                                    'display_name'
                                                ]
                                            ) ?>

                                        </small>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $instance[
                                            'device_name'
                                        ]
                                    ) ?>


                                    <br>

                                    <small
                                        class="text-muted"
                                    >

                                        <?= htmlspecialchars(
                                            $instance[
                                                'device_code'
                                            ]
                                        ) ?>

                                    </small>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $instance[
                                            'device_type'
                                        ]
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $instance[
                                            'vendor'
                                        ]
                                    ) ?>


                                    <?php if (
                                        !empty(
                                            $instance['model']
                                        )
                                    ): ?>

                                        <br>

                                        <small
                                            class="text-muted"
                                        >

                                            <?= htmlspecialchars(
                                                $instance[
                                                    'model'
                                                ]
                                            ) ?>

                                        </small>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php if (
                                        $instance[
                                            'instance_status'
                                        ] === 'Active'
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


                                <td class="text-end">

                                    <form
                                        method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm(
                                            'Remove this device instance? Any interfaces and topology connections belonging to this instance will also be removed.'
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="remove_instance"
                                        >


                                        <input
                                            type="hidden"
                                            name="instance_id"
                                            value="<?= (int)
                                                $instance[
                                                    'instance_id'
                                                ] ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Remove Instance"
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



    <!-- ==============================================================
         NEXT WORKFLOW
         ============================================================== -->

    <div class="card dashboard-card mt-4">

        <div class="card-body">

            <div class="row align-items-center">

                <div class="col-md-8">

                    <h5 class="fw-bold mb-1">

                        <i class="bi bi-ethernet"></i>

                        Next: Configure Device Interfaces

                    </h5>


                    <p class="text-muted mb-0">

                        After creating the actual device instances,
                        define the interfaces and ports that will be
                        used to build the scenario topology.

                    </p>

                </div>


                <div class="col-md-4 text-md-end mt-3 mt-md-0">

                    <span
                        class="badge bg-secondary fs-6"
                    >

                        Phase 3.3

                    </span>

                </div>

            </div>

        </div>

    </div>

</div>


<?php require_once '../includes/layout_end.php'; ?>