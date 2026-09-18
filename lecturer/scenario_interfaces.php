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
| Interface Name Generator
|--------------------------------------------------------------------------
|
| Generates practical interface names based on the device type.
|
|--------------------------------------------------------------------------
*/

function getInterfaceName(
    string $deviceType,
    int $number
): string {

    switch ($deviceType) {

        case 'Router':

            return 'G0/' . ($number - 1);


        case 'Switch':

            return 'Fa0/' . $number;


        case 'Layer3 Switch':

            return 'Gi0/' . $number;


        case 'PC':

            return 'Fa0';


        case 'Server':

            return 'Gi0/' . ($number - 1);


        case 'Wireless Router':

            if ($number === 1) {
                return 'WAN';
            }

            return 'LAN' . ($number - 1);


        case 'Access Point':

            if ($number === 1) {
                return 'Ethernet0';
            }

            return 'Wireless' . ($number - 1);


        case 'Firewall':

            return 'Gi0/' . ($number - 1);


        case 'Cloud':

            return 'Cloud' . $number;


        default:

            return 'Port' . $number;
    }
}


/*
|--------------------------------------------------------------------------
| Interface Type Generator
|--------------------------------------------------------------------------
*/

function getInterfaceType(string $deviceType): string
{
    switch ($deviceType) {

        case 'Router':
            return 'GigabitEthernet';

        case 'Switch':
            return 'FastEthernet';

        case 'Layer3 Switch':
            return 'GigabitEthernet';

        case 'PC':
            return 'FastEthernet';

        case 'Server':
            return 'GigabitEthernet';

        case 'Wireless Router':
            return 'Ethernet';

        case 'Access Point':
            return 'Ethernet/Wireless';

        case 'Firewall':
            return 'GigabitEthernet';

        case 'Cloud':
            return 'Cloud Interface';

        default:
            return 'Network Interface';
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
    | Generate Interfaces
    |--------------------------------------------------------------------------
    */

    if ($action === 'generate_interfaces') {

        $instanceId = isset($_POST['instance_id'])
            ? (int) $_POST['instance_id']
            : 0;


        if ($instanceId <= 0) {

            $error = "Invalid device instance.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Load Instance With Ownership Check
            |--------------------------------------------------------------------------
            */

            $instanceStmt = db()->prepare("
                SELECT

                    sdi.instance_id,
                    sdi.instance_name,
                    sdi.display_name,

                    d.device_id,
                    d.device_name,
                    d.device_type,
                    d.interface_count

                FROM scenario_device_instances sdi

                INNER JOIN scenario_devices sd
                    ON sd.scenario_device_id =
                       sdi.scenario_device_id

                INNER JOIN scenarios s
                    ON s.scenario_id =
                       sd.scenario_id

                INNER JOIN devices d
                    ON d.device_id =
                       sd.device_id

                WHERE sdi.instance_id = ?
                  AND sd.scenario_id = ?
                  AND s.created_by = ?

                LIMIT 1
            ");

            $instanceStmt->execute([
                $instanceId,
                $scenarioId,
                $userId
            ]);

            $instance =
                $instanceStmt->fetch(PDO::FETCH_ASSOC);


            if (!$instance) {

                $error =
                    "The selected device instance was not found.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Determine Interface Count
                |--------------------------------------------------------------------------
                */

                $interfaceCount =
                    (int) $instance['interface_count'];


                if ($interfaceCount <= 0) {

                    $error =
                        "This device does not have a valid interface count.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Count Existing Interfaces
                    |--------------------------------------------------------------------------
                    */

                    $countStmt = db()->prepare("
                        SELECT COUNT(*)
                        FROM scenario_device_interfaces
                        WHERE instance_id = ?
                    ");

                    $countStmt->execute([
                        $instanceId
                    ]);

                    $existingCount =
                        (int) $countStmt->fetchColumn();


                    /*
                    |--------------------------------------------------------------------------
                    | Already Complete
                    |--------------------------------------------------------------------------
                    */

                    if ($existingCount >= $interfaceCount) {

                        $error =
                            "All available interfaces for this device have already been created.";

                    } else {

                        try {

                            db()->beginTransaction();


                            /*
                            |--------------------------------------------------------------------------
                            | Generate Missing Interfaces
                            |--------------------------------------------------------------------------
                            */

                            for (
                                $number = 1;
                                $number <= $interfaceCount;
                                $number++
                            ) {

                                $interfaceName =
                                    getInterfaceName(
                                        $instance['device_type'],
                                        $number
                                    );


                                /*
                                |--------------------------------------------------------------------------
                                | Check Existing Interface
                                |--------------------------------------------------------------------------
                                */

                                $checkStmt = db()->prepare("
                                    SELECT interface_id
                                    FROM scenario_device_interfaces
                                    WHERE instance_id = ?
                                      AND interface_name = ?
                                    LIMIT 1
                                ");

                                $checkStmt->execute([
                                    $instanceId,
                                    $interfaceName
                                ]);


                                if ($checkStmt->fetchColumn()) {
                                    continue;
                                }


                                /*
                                |--------------------------------------------------------------------------
                                | Interface Type
                                |--------------------------------------------------------------------------
                                */

                                $interfaceType =
                                    getInterfaceType(
                                        $instance['device_type']
                                    );


                                /*
                                |--------------------------------------------------------------------------
                                | Insert Interface
                                |--------------------------------------------------------------------------
                                */

                                $insertStmt = db()->prepare("
                                    INSERT INTO scenario_device_interfaces
                                    (
                                        instance_id,
                                        interface_name,
                                        interface_type,
                                        interface_status
                                    )
                                    VALUES
                                    (
                                        ?,
                                        ?,
                                        ?,
                                        'Down'
                                    )
                                ");

                                $insertStmt->execute([
                                    $instanceId,
                                    $interfaceName,
                                    $interfaceType
                                ]);
                            }


                            db()->commit();


                            $success =
                                "Interfaces generated successfully.";

                        } catch (Throwable $e) {

                            if (db()->inTransaction()) {
                                db()->rollBack();
                            }

                            $error =
                                "The device interfaces could not be created.";
                        }
                    }
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Interface
    |--------------------------------------------------------------------------
    */

    if ($action === 'remove_interface') {

        $interfaceId = isset($_POST['interface_id'])
            ? (int) $_POST['interface_id']
            : 0;


        if ($interfaceId <= 0) {

            $error = "Invalid interface.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Ownership-Safe Delete
            |--------------------------------------------------------------------------
            */

            $deleteStmt = db()->prepare("
                DELETE sdi
                FROM scenario_device_interfaces sdi

                INNER JOIN scenario_device_instances sdi2
                    ON sdi2.instance_id =
                       sdi.instance_id

                INNER JOIN scenario_devices sd
                    ON sd.scenario_device_id =
                       sdi2.scenario_device_id

                INNER JOIN scenarios s
                    ON s.scenario_id =
                       sd.scenario_id

                WHERE sdi.interface_id = ?
                  AND sd.scenario_id = ?
                  AND s.created_by = ?
            ");

            $deleteStmt->execute([
                $interfaceId,
                $scenarioId,
                $userId
            ]);


            if ($deleteStmt->rowCount() > 0) {

                $success =
                    "Interface removed successfully.";

            } else {

                $error =
                    "The interface could not be removed.";
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Update Interface
    |--------------------------------------------------------------------------
    */

    if ($action === 'update_interface') {

        $interfaceId = isset($_POST['interface_id'])
            ? (int) $_POST['interface_id']
            : 0;

        $ipAddress = trim(
            $_POST['ip_address'] ?? ''
        );

        $subnetMask = trim(
            $_POST['subnet_mask'] ?? ''
        );

        $macAddress = trim(
            $_POST['mac_address'] ?? ''
        );

        $interfaceStatus =
            $_POST['interface_status'] ?? 'Down';

        $notes = trim(
            $_POST['notes'] ?? ''
        );


        /*
        |--------------------------------------------------------------------------
        | Validate Status
        |--------------------------------------------------------------------------
        */

        $allowedStatuses = [
            'Up',
            'Down',
            'Administratively Down'
        ];


        if (!in_array(
            $interfaceStatus,
            $allowedStatuses,
            true
        )) {

            $error =
                "Invalid interface status.";

        } elseif ($interfaceId <= 0) {

            $error =
                "Invalid interface.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Ownership-Safe Update
            |--------------------------------------------------------------------------
            */

            $updateStmt = db()->prepare("
                UPDATE scenario_device_interfaces sdi

                INNER JOIN scenario_device_instances sdi2
                    ON sdi2.instance_id =
                       sdi.instance_id

                INNER JOIN scenario_devices sd
                    ON sd.scenario_device_id =
                       sdi2.scenario_device_id

                INNER JOIN scenarios s
                    ON s.scenario_id =
                       sd.scenario_id

                SET

                    sdi.ip_address = ?,
                    sdi.subnet_mask = ?,
                    sdi.mac_address = ?,
                    sdi.interface_status = ?,
                    sdi.notes = ?

                WHERE sdi.interface_id = ?
                  AND sd.scenario_id = ?
                  AND s.created_by = ?
            ");

            $updateStmt->execute([

                $ipAddress !== ''
                    ? $ipAddress
                    : null,

                $subnetMask !== ''
                    ? $subnetMask
                    : null,

                $macAddress !== ''
                    ? $macAddress
                    : null,

                $interfaceStatus,

                $notes !== ''
                    ? $notes
                    : null,

                $interfaceId,
                $scenarioId,
                $userId
            ]);


            if ($updateStmt->rowCount() >= 0) {

                $success =
                    "Interface configuration saved successfully.";

            } else {

                $error =
                    "The interface configuration could not be saved.";
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load Device Instances
|--------------------------------------------------------------------------
*/

$instanceStmt = db()->prepare("
    SELECT

        sdi.instance_id,
        sdi.instance_name,
        sdi.display_name,
        sdi.instance_status,

        d.device_code,
        d.device_name,
        d.device_type,
        d.vendor,
        d.model,
        d.interface_count,

        COUNT(sdif.interface_id) AS interface_created

    FROM scenario_device_instances sdi

    INNER JOIN scenario_devices sd
        ON sd.scenario_device_id =
           sdi.scenario_device_id

    INNER JOIN devices d
        ON d.device_id =
           sd.device_id

    LEFT JOIN scenario_device_interfaces sdif
        ON sdif.instance_id =
           sdi.instance_id

    WHERE sd.scenario_id = ?

    GROUP BY

        sdi.instance_id,
        sdi.instance_name,
        sdi.display_name,
        sdi.instance_status,

        d.device_code,
        d.device_name,
        d.device_type,
        d.vendor,
        d.model,
        d.interface_count

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
| Load All Interfaces
|--------------------------------------------------------------------------
*/

$interfaceStmt = db()->prepare("
    SELECT

        sdif.interface_id,
        sdif.instance_id,
        sdif.interface_name,
        sdif.interface_type,
        sdif.ip_address,
        sdif.subnet_mask,
        sdif.mac_address,
        sdif.interface_status,
        sdif.notes,

        sdi.instance_name,
        sdi.display_name,

        d.device_name,
        d.device_type,
        d.device_code

    FROM scenario_device_interfaces sdif

    INNER JOIN scenario_device_instances sdi
        ON sdi.instance_id =
           sdif.instance_id

    INNER JOIN scenario_devices sd
        ON sd.scenario_device_id =
           sdi.scenario_device_id

    INNER JOIN devices d
        ON d.device_id =
           sd.device_id

    WHERE sd.scenario_id = ?

    ORDER BY
        d.device_type ASC,
        sdi.instance_name ASC,
        sdif.interface_id ASC
");

$interfaceStmt->execute([
    $scenarioId
]);

$interfaces =
    $interfaceStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Group Interfaces By Instance
|--------------------------------------------------------------------------
*/

$interfacesByInstance = [];

foreach ($interfaces as $interface) {

    $instanceId =
        (int) $interface['instance_id'];

    if (!isset(
        $interfacesByInstance[$instanceId]
    )) {

        $interfacesByInstance[$instanceId] = [];
    }

    $interfacesByInstance[$instanceId][] =
        $interface;
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalInstances =
    count($instances);

$totalInterfaces =
    count($interfaces);

$totalExpectedInterfaces = 0;

foreach ($instances as $instance) {

    $totalExpectedInterfaces +=
        (int) $instance['interface_count'];
}


$interfacePercentage = 0;

if ($totalExpectedInterfaces > 0) {

    $interfacePercentage =
        min(
            100,
            round(
                (
                    $totalInterfaces /
                    $totalExpectedInterfaces
                ) * 100
            )
        );
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = "Scenario Interfaces";

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">


    <!-- ==============================================================
         PAGE HEADER
         ============================================================== -->

    <div
        class="d-flex justify-content-between
               align-items-center mb-4"
    >

        <div>

            <h2 class="fw-bold mb-1">
                Device Interfaces
            </h2>

            <p class="text-muted mb-0">

                Define and configure the interfaces and ports
                used by the scenario devices.

            </p>

        </div>


        <div class="d-flex gap-2">

            <a
                href="scenario_instances.php?id=<?= $scenarioId ?>"
                class="btn btn-outline-primary"
            >

                <i class="bi bi-hdd-network"></i>

                Device Instances

            </a>


            <a
                href="scenario_devices.php?id=<?= $scenarioId ?>"
                class="btn btn-outline-secondary"
            >

                <i class="bi bi-router"></i>

                Scenario Devices

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


                <div
                    class="col-md-4 text-md-end
                           mt-3 mt-md-0"
                >

                    <div class="text-muted mb-1">

                        Interface Progress

                    </div>


                    <div class="fw-bold fs-4">

                        <?= $totalInterfaces ?>

                        /

                        <?= $totalExpectedInterfaces ?>

                    </div>


                    <div class="progress mt-2">

                        <div
                            class="progress-bar"
                            role="progressbar"
                            style="
                                width:
                                <?= $interfacePercentage ?>%;
                            "
                            aria-valuenow="<?= $interfacePercentage ?>"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >

                            <?= $interfacePercentage ?>%

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
            class="alert alert-success
                   alert-dismissible fade show"
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
            class="alert alert-danger
                   alert-dismissible fade show"
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
         DEVICE INTERFACE GENERATION
         ============================================================== -->

    <div class="card dashboard-card mb-4">

        <div class="card-header bg-dark text-white">

            <h5 class="mb-0">

                <i class="bi bi-ethernet"></i>

                Device Interface Definitions

            </h5>

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

                        No device instances found

                    </h5>


                    <p class="text-muted mb-3">

                        Create the actual device instances before
                        defining their interfaces.

                    </p>


                    <a
                        href="scenario_instances.php?id=<?= $scenarioId ?>"
                        class="btn btn-dark"
                    >

                        <i class="bi bi-hdd-network"></i>

                        Manage Device Instances

                    </a>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table
                        class="table table-hover
                               align-middle"
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

                                <th class="text-center">
                                    Interfaces
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
                            $instances
                            as $instance
                        ): ?>

                            <?php

                            $expected =
                                (int)
                                $instance[
                                    'interface_count'
                                ];

                            $created =
                                (int)
                                $instance[
                                    'interface_created'
                                ];

                            $complete =
                                $created >= $expected;

                            ?>

                            <tr>

                                <td>

                                    <div class="fw-bold">

                                        <?= htmlspecialchars(
                                            $instance[
                                                'instance_name'
                                            ]
                                        ) ?>

                                    </div>


                                    <small class="text-muted">

                                        <?= htmlspecialchars(
                                            $instance[
                                                'display_name'
                                            ]
                                        ) ?>

                                    </small>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $instance[
                                            'device_name'
                                        ]
                                    ) ?>


                                    <br>

                                    <small class="text-muted">

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


                                <td class="text-center">

                                    <span
                                        class="badge
                                        <?= $complete
                                            ? 'bg-success'
                                            : 'bg-warning text-dark'
                                        ?>"
                                    >

                                        <?= $created ?>

                                        /

                                        <?= $expected ?>

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
                                            class="badge
                                                   bg-warning text-dark"
                                        >

                                            <?= $expected - $created ?>

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
                                                value="generate_interfaces"
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
                                                class="btn btn-sm btn-dark"
                                            >

                                                <i
                                                    class="bi bi-plus-circle"
                                                ></i>

                                                Generate

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
         INTERFACE CONFIGURATION
         ============================================================== -->

    <div class="card dashboard-card">

        <div
            class="card-header bg-primary text-white"
        >

            <div
                class="d-flex justify-content-between
                       align-items-center"
            >

                <h5 class="mb-0">

                    <i class="bi bi-ethernet"></i>

                    Configured Interfaces

                </h5>


                <span class="badge bg-light text-dark">

                    <?= $totalInterfaces ?>

                </span>

            </div>

        </div>


        <div class="card-body">

            <?php if (empty($interfaces)): ?>

                <div class="text-center py-5">

                    <i
                        class="bi bi-ethernet"
                        style="
                            font-size: 3rem;
                            color: #6c757d;
                        "
                    ></i>


                    <h5 class="mt-3">

                        No interfaces configured

                    </h5>


                    <p class="text-muted mb-0">

                        Generate interfaces for the device instances
                        above.

                    </p>

                </div>

            <?php else: ?>


                <?php foreach (
                    $instances
                    as $instance
                ): ?>

                    <?php

                    $instanceId =
                        (int)
                        $instance['instance_id'];

                    $instanceInterfaces =
                        $interfacesByInstance[
                            $instanceId
                        ] ?? [];

                    ?>

                    <?php if (
                        empty($instanceInterfaces)
                    ): ?>

                        <?php continue; ?>

                    <?php endif; ?>


                    <div class="border rounded mb-4">

                        <div
                            class="bg-light border-bottom
                                   p-3"
                        >

                            <div
                                class="d-flex
                                       justify-content-between
                                       align-items-center"
                            >

                                <div>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $instance[
                                                'instance_name'
                                            ]
                                        ) ?>

                                    </strong>


                                    <span class="text-muted">

                                        —

                                        <?= htmlspecialchars(
                                            $instance[
                                                'device_name'
                                            ]
                                        ) ?>

                                    </span>

                                </div>


                                <span
                                    class="badge bg-secondary"
                                >

                                    <?= count(
                                        $instanceInterfaces
                                    ) ?>

                                    interfaces

                                </span>

                            </div>

                        </div>


                        <div class="table-responsive">

                            <table
                                class="table table-hover
                                       align-middle mb-0"
                            >

                                <thead>

                                    <tr>

                                        <th>
                                            Interface
                                        </th>

                                        <th>
                                            Type
                                        </th>

                                        <th>
                                            IP Address
                                        </th>

                                        <th>
                                            Subnet Mask
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                        <th class="text-end">
                                            Actions
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                <?php foreach (
                                    $instanceInterfaces
                                    as $interface
                                ): ?>

                                    <tr>

                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $interface[
                                                        'interface_name'
                                                    ]
                                                ) ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <small>

                                                <?= htmlspecialchars(
                                                    $interface[
                                                        'interface_type'
                                                    ]
                                                ) ?>

                                            </small>

                                        </td>


                                        <td>

                                            <?= !empty(
                                                $interface[
                                                    'ip_address'
                                                ]
                                            )
                                                ? htmlspecialchars(
                                                    $interface[
                                                        'ip_address'
                                                    ]
                                                )
                                                : '<span class="text-muted">Not configured</span>'
                                            ?>

                                        </td>


                                        <td>

                                            <?= !empty(
                                                $interface[
                                                    'subnet_mask'
                                                ]
                                            )
                                                ? htmlspecialchars(
                                                    $interface[
                                                        'subnet_mask'
                                                    ]
                                                )
                                                : '<span class="text-muted">Not configured</span>'
                                            ?>

                                        </td>


                                        <td>

                                            <?php

                                            $status =
                                                $interface[
                                                    'interface_status'
                                                ];

                                            $statusClass =
                                                match ($status) {

                                                    'Up' =>
                                                        'bg-success',

                                                    'Administratively Down' =>
                                                        'bg-danger',

                                                    default =>
                                                        'bg-secondary'
                                                };

                                            ?>


                                            <span
                                                class="badge
                                                       <?= $statusClass ?>"
                                            >

                                                <?= htmlspecialchars(
                                                    $status
                                                ) ?>

                                            </span>

                                        </td>


                                        <td class="text-end">

                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editInterface<?= (int)
                                                    $interface[
                                                        'interface_id'
                                                    ] ?>"
                                                title="Configure Interface"
                                            >

                                                <i
                                                    class="bi bi-pencil"
                                                ></i>

                                            </button>


                                            <form
                                                method="POST"
                                                class="d-inline"
                                                onsubmit="return confirm(
                                                    'Remove this interface? Any topology connection using this interface will also be removed.'
                                                );"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="remove_interface"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="interface_id"
                                                    value="<?= (int)
                                                        $interface[
                                                            'interface_id'
                                                        ] ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Remove Interface"
                                                >

                                                    <i
                                                        class="bi bi-trash"
                                                    ></i>

                                                </button>

                                            </form>

                                        </td>

                                    </tr>


                                    <!-- ==================================
                                         EDIT INTERFACE MODAL
                                         ================================== -->

                                    <div
                                        class="modal fade"
                                        id="editInterface<?= (int)
                                            $interface[
                                                'interface_id'
                                            ] ?>"
                                        tabindex="-1"
                                        aria-hidden="true"
                                    >

                                        <div
                                            class="modal-dialog
                                                   modal-lg"
                                        >

                                            <div class="modal-content">

                                                <div
                                                    class="modal-header"
                                                >

                                                    <h5
                                                        class="modal-title"
                                                    >

                                                        Configure
                                                        Interface

                                                    </h5>


                                                    <button
                                                        type="button"
                                                        class="btn-close"
                                                        data-bs-dismiss="modal"
                                                    ></button>

                                                </div>


                                                <form
                                                    method="POST"
                                                >

                                                    <div
                                                        class="modal-body"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="update_interface"
                                                        >


                                                        <input
                                                            type="hidden"
                                                            name="interface_id"
                                                            value="<?= (int)
                                                                $interface[
                                                                    'interface_id'
                                                                ] ?>"
                                                        >


                                                        <div class="row">

                                                            <div class="col-md-6 mb-3">

                                                                <label
                                                                    class="form-label fw-semibold"
                                                                >

                                                                    Device

                                                                </label>


                                                                <input
                                                                    type="text"
                                                                    class="form-control"
                                                                    value="<?= htmlspecialchars(
                                                                        $interface[
                                                                            'instance_name'
                                                                        ] .
                                                                        ' - ' .
                                                                        $interface[
                                                                            'device_name'
                                                                        ]
                                                                    ) ?>"
                                                                    readonly
                                                                >

                                                            </div>


                                                            <div class="col-md-6 mb-3">

                                                                <label
                                                                    class="form-label fw-semibold"
                                                                >

                                                                    Interface

                                                                </label>


                                                                <input
                                                                    type="text"
                                                                    class="form-control"
                                                                    value="<?= htmlspecialchars(
                                                                        $interface[
                                                                            'interface_name'
                                                                        ]
                                                                    ) ?>"
                                                                    readonly
                                                                >

                                                            </div>

                                                        </div>


                                                        <div class="row">

                                                            <div class="col-md-6 mb-3">

                                                                <label
                                                                    class="form-label fw-semibold"
                                                                >

                                                                    IP Address

                                                                </label>


                                                                <input
                                                                    type="text"
                                                                    name="ip_address"
                                                                    class="form-control"
                                                                    value="<?= htmlspecialchars(
                                                                        $interface[
                                                                            'ip_address'
                                                                        ] ?? ''
                                                                    ) ?>"
                                                                    placeholder="e.g. 192.168.1.1"
                                                                >

                                                            </div>


                                                            <div class="col-md-6 mb-3">

                                                                <label
                                                                    class="form-label fw-semibold"
                                                                >

                                                                    Subnet Mask

                                                                </label>


                                                                <input
                                                                    type="text"
                                                                    name="subnet_mask"
                                                                    class="form-control"
                                                                    value="<?= htmlspecialchars(
                                                                        $interface[
                                                                            'subnet_mask'
                                                                        ] ?? ''
                                                                    ) ?>"
                                                                    placeholder="e.g. 255.255.255.0"
                                                                >

                                                            </div>

                                                        </div>


                                                        <div class="row">

                                                            <div class="col-md-6 mb-3">

                                                                <label
                                                                    class="form-label fw-semibold"
                                                                >

                                                                    MAC Address

                                                                </label>


                                                                <input
                                                                    type="text"
                                                                    name="mac_address"
                                                                    class="form-control"
                                                                    value="<?= htmlspecialchars(
                                                                        $interface[
                                                                            'mac_address'
                                                                        ] ?? ''
                                                                    ) ?>"
                                                                    placeholder="e.g. 00:1A:2B:3C:4D:5E"
                                                                >

                                                            </div>


                                                            <div class="col-md-6 mb-3">

                                                                <label
                                                                    class="form-label fw-semibold"
                                                                >

                                                                    Interface Status

                                                                </label>


                                                                <select
                                                                    name="interface_status"
                                                                    class="form-select"
                                                                >

                                                                    <option
                                                                        value="Up"
                                                                        <?= $interface[
                                                                            'interface_status'
                                                                        ] === 'Up'
                                                                            ? 'selected'
                                                                            : ''
                                                                        ?>
                                                                    >
                                                                        Up
                                                                    </option>


                                                                    <option
                                                                        value="Down"
                                                                        <?= $interface[
                                                                            'interface_status'
                                                                        ] === 'Down'
                                                                            ? 'selected'
                                                                            : ''
                                                                        ?>
                                                                    >
                                                                        Down
                                                                    </option>


                                                                    <option
                                                                        value="Administratively Down"
                                                                        <?= $interface[
                                                                            'interface_status'
                                                                        ] === 'Administratively Down'
                                                                            ? 'selected'
                                                                            : ''
                                                                        ?>
                                                                    >
                                                                        Administratively Down
                                                                    </option>

                                                                </select>

                                                            </div>

                                                        </div>


                                                        <div class="mb-3">

                                                            <label
                                                                class="form-label fw-semibold"
                                                            >

                                                                Notes

                                                            </label>


                                                            <textarea
                                                                name="notes"
                                                                class="form-control"
                                                                rows="3"
                                                                placeholder="Describe the role or configuration of this interface..."
                                                            ><?= htmlspecialchars(
                                                                $interface[
                                                                    'notes'
                                                                ] ?? ''
                                                            ) ?></textarea>

                                                        </div>

                                                    </div>


                                                    <div
                                                        class="modal-footer"
                                                    >

                                                        <button
                                                            type="button"
                                                            class="btn btn-secondary"
                                                            data-bs-dismiss="modal"
                                                        >

                                                            Cancel

                                                        </button>


                                                        <button
                                                            type="submit"
                                                            class="btn btn-primary"
                                                        >

                                                            <i
                                                                class="bi bi-save"
                                                            ></i>

                                                            Save Configuration

                                                        </button>

                                                    </div>

                                                </form>

                                            </div>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                <?php endforeach; ?>

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

                        <i class="bi bi-diagram-3"></i>

                        Next: Connect Network Interfaces

                    </h5>


                    <p class="text-muted mb-0">

                        After the interfaces have been defined,
                        connect the ports to create the physical
                        or logical topology of the scenario.

                    </p>

                </div>


                <div
                    class="col-md-4 text-md-end
                           mt-3 mt-md-0"
                >

                    <span
                        class="badge bg-secondary fs-6"
                    >

                        Phase 3.4

                    </span>

                </div>

            </div>

        </div>

    </div>

</div>


<?php require_once '../includes/layout_end.php'; ?>