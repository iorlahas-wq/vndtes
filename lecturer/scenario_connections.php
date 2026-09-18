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
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Add Connection
    |--------------------------------------------------------------------------
    */

    if ($action === 'add_connection') {

        $interfaceAId = isset($_POST['interface_a_id'])
            ? (int) $_POST['interface_a_id']
            : 0;

        $interfaceBId = isset($_POST['interface_b_id'])
            ? (int) $_POST['interface_b_id']
            : 0;

        $connectionType =
            $_POST['connection_type'] ?? 'Ethernet';

        $notes = trim(
            $_POST['notes'] ?? ''
        );


        /*
        |--------------------------------------------------------------------------
        | Validate Basic Input
        |--------------------------------------------------------------------------
        */

        $allowedConnectionTypes = [
            'Ethernet',
            'Serial',
            'Wireless',
            'Other'
        ];


        if ($interfaceAId <= 0) {

            $error =
                "Please select the first interface.";

        } elseif ($interfaceBId <= 0) {

            $error =
                "Please select the second interface.";

        } elseif ($interfaceAId === $interfaceBId) {

            $error =
                "An interface cannot be connected to itself.";

        } elseif (!in_array(
            $connectionType,
            $allowedConnectionTypes,
            true
        )) {

            $error =
                "Invalid connection type.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Load Both Interfaces
            |--------------------------------------------------------------------------
            |
            | The ownership check is performed through the complete
            | scenario relationship.
            |
            |--------------------------------------------------------------------------
            */

            $interfaceStmt = db()->prepare("
                SELECT

                    sdif.interface_id,
                    sdif.interface_name,
                    sdif.interface_type,

                    sdi.instance_id,
                    sdi.instance_name,
                    sdi.display_name,

                    sd.scenario_id,

                    d.device_name,
                    d.device_type

                FROM scenario_device_interfaces sdif

                INNER JOIN scenario_device_instances sdi
                    ON sdi.instance_id =
                       sdif.instance_id

                INNER JOIN scenario_devices sd
                    ON sd.scenario_device_id =
                       sdi.scenario_device_id

                INNER JOIN scenarios s
                    ON s.scenario_id =
                       sd.scenario_id

                INNER JOIN devices d
                    ON d.device_id =
                       sd.device_id

                WHERE sdif.interface_id IN (?, ?)
                  AND sd.scenario_id = ?
                  AND s.created_by = ?

                ORDER BY sdif.interface_id ASC
            ");

            $interfaceStmt->execute([
                $interfaceAId,
                $interfaceBId,
                $scenarioId,
                $userId
            ]);

            $selectedInterfaces =
                $interfaceStmt->fetchAll(PDO::FETCH_ASSOC);


            /*
            |--------------------------------------------------------------------------
            | Both Interfaces Must Belong To Scenario
            |--------------------------------------------------------------------------
            */

            if (count($selectedInterfaces) !== 2) {

                $error =
                    "One or both selected interfaces do not belong to this scenario.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Existing Connection Check
                |--------------------------------------------------------------------------
                |
                | Check BOTH directions:
                |
                | A → B
                | B → A
                |
                | This is important because the database unique key only
                | protects one ordering of the pair.
                |
                |--------------------------------------------------------------------------
                */

                $connectionCheckStmt = db()->prepare("
                    SELECT connection_id

                    FROM scenario_connections

                    WHERE
                        (
                            interface_a_id = ?
                            AND interface_b_id = ?
                        )

                        OR

                        (
                            interface_a_id = ?
                            AND interface_b_id = ?
                        )

                    LIMIT 1
                ");

                $connectionCheckStmt->execute([
                    $interfaceAId,
                    $interfaceBId,
                    $interfaceBId,
                    $interfaceAId
                ]);

                $existingConnection =
                    $connectionCheckStmt->fetchColumn();


                if ($existingConnection) {

                    $error =
                        "These two interfaces are already connected.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Check Whether Either Interface Is Already Connected
                    |--------------------------------------------------------------------------
                    |
                    | A normal network interface should have one physical
                    | connection at this stage.
                    |
                    |--------------------------------------------------------------------------
                    */

                    $usedInterfaceStmt = db()->prepare("
                        SELECT connection_id

                        FROM scenario_connections

                        WHERE interface_a_id IN (?, ?)
                           OR interface_b_id IN (?, ?)

                        LIMIT 1
                    ");

                    $usedInterfaceStmt->execute([
                        $interfaceAId,
                        $interfaceBId,
                        $interfaceAId,
                        $interfaceBId
                    ]);

                    $usedInterface =
                        $usedInterfaceStmt->fetchColumn();


                    if ($usedInterface) {

                        $error =
                            "One of the selected interfaces is already connected.";

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Insert Connection
                        |--------------------------------------------------------------------------
                        */

                        $insertStmt = db()->prepare("
                            INSERT INTO scenario_connections
                            (
                                interface_a_id,
                                interface_b_id,
                                connection_type,
                                status,
                                notes
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                'Active',
                                ?
                            )
                        ");

                        $insertStmt->execute([
                            $interfaceAId,
                            $interfaceBId,
                            $connectionType,
                            $notes !== ''
                                ? $notes
                                : null
                        ]);


                        $success =
                            "Network connection created successfully.";
                    }
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Connection
    |--------------------------------------------------------------------------
    */

    if ($action === 'remove_connection') {

        $connectionId = isset(
            $_POST['connection_id']
        )
            ? (int) $_POST['connection_id']
            : 0;


        if ($connectionId <= 0) {

            $error =
                "Invalid network connection.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Ownership-Safe Delete
            |--------------------------------------------------------------------------
            */

            $deleteStmt = db()->prepare("
                DELETE sc
                FROM scenario_connections sc

                INNER JOIN scenario_device_interfaces ia
                    ON ia.interface_id =
                       sc.interface_a_id

                INNER JOIN scenario_device_instances i1
                    ON i1.instance_id =
                       ia.instance_id

                INNER JOIN scenario_devices sd1
                    ON sd1.scenario_device_id =
                       i1.scenario_device_id

                INNER JOIN scenarios s
                    ON s.scenario_id =
                       sd1.scenario_id

                WHERE sc.connection_id = ?
                  AND sd1.scenario_id = ?
                  AND s.created_by = ?
            ");

            $deleteStmt->execute([
                $connectionId,
                $scenarioId,
                $userId
            ]);


            if ($deleteStmt->rowCount() > 0) {

                $success =
                    "Network connection removed successfully.";

            } else {

                $error =
                    "The network connection could not be removed.";
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load Available Interfaces
|--------------------------------------------------------------------------
*/

$interfaceStmt = db()->prepare("
    SELECT

        sdif.interface_id,
        sdif.interface_name,
        sdif.interface_type,
        sdif.interface_status,

        sdi.instance_id,
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
| Load Existing Connections
|--------------------------------------------------------------------------
*/

$connectionStmt = db()->prepare("
    SELECT

        sc.connection_id,
        sc.connection_type,
        sc.status,
        sc.notes,
        sc.created_at,

        ia.interface_id AS interface_a_id,
        ia.interface_name AS interface_a_name,

        i1.instance_id AS instance_a_id,
        i1.instance_name AS instance_a_name,
        i1.display_name AS display_a_name,

        da.device_name AS device_a_name,
        da.device_type AS device_a_type,

        ib.interface_id AS interface_b_id,
        ib.interface_name AS interface_b_name,

        i2.instance_id AS instance_b_id,
        i2.instance_name AS instance_b_name,
        i2.display_name AS display_b_name,

        db.device_name AS device_b_name,
        db.device_type AS device_b_type

    FROM scenario_connections sc

    INNER JOIN scenario_device_interfaces ia
        ON ia.interface_id =
           sc.interface_a_id

    INNER JOIN scenario_device_instances i1
        ON i1.instance_id =
           ia.instance_id

    INNER JOIN scenario_devices sd1
        ON sd1.scenario_device_id =
           i1.scenario_device_id

    INNER JOIN devices da
        ON da.device_id =
           sd1.device_id

    INNER JOIN scenario_device_interfaces ib
        ON ib.interface_id =
           sc.interface_b_id

    INNER JOIN scenario_device_instances i2
        ON i2.instance_id =
           ib.instance_id

    INNER JOIN scenario_devices sd2
        ON sd2.scenario_device_id =
           i2.scenario_device_id

    INNER JOIN devices db
        ON db.device_id =
           sd2.device_id

    WHERE sd1.scenario_id = ?
      AND sd2.scenario_id = ?

    ORDER BY sc.connection_id DESC
");

$connectionStmt->execute([
    $scenarioId,
    $scenarioId
]);

$connections =
    $connectionStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Connection Statistics
|--------------------------------------------------------------------------
*/

$totalInterfaces =
    count($interfaces);

$totalConnections =
    count($connections);


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = "Scenario Connections";

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
                Network Connections
            </h2>

            <p class="text-muted mb-0">

                Connect device interfaces to build the network topology
                for this scenario.

            </p>

        </div>


        <div class="d-flex gap-2">

            <a
                href="scenario_interfaces.php?id=<?= $scenarioId ?>"
                class="btn btn-outline-primary"
            >

                <i class="bi bi-ethernet"></i>

                Interfaces

            </a>


            <a
                href="scenario_instances.php?id=<?= $scenarioId ?>"
                class="btn btn-outline-secondary"
            >

                <i class="bi bi-hdd-network"></i>

                Device Instances

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

                    <div class="text-muted">

                        Interfaces

                    </div>

                    <span class="badge bg-secondary fs-6">

                        <?= $totalInterfaces ?>

                    </span>


                    <div class="text-muted mt-2">

                        Connections

                    </div>

                    <span class="badge bg-primary fs-6">

                        <?= $totalConnections ?>

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
         ADD CONNECTION
         ============================================================== -->

    <div class="card dashboard-card mb-4">

        <div class="card-header bg-dark text-white">

            <h5 class="mb-0">

                <i class="bi bi-link-45deg"></i>

                Create Network Connection

            </h5>

        </div>


        <div class="card-body">

            <?php if (count($interfaces) < 2): ?>

                <div class="text-center py-5">

                    <i
                        class="bi bi-ethernet"
                        style="
                            font-size: 3rem;
                            color: #6c757d;
                        "
                    ></i>


                    <h5 class="mt-3">

                        Not enough interfaces available

                    </h5>


                    <p class="text-muted mb-3">

                        At least two interfaces are required
                        before a network connection can be created.

                    </p>


                    <a
                        href="scenario_interfaces.php?id=<?= $scenarioId ?>"
                        class="btn btn-dark"
                    >

                        <i class="bi bi-ethernet"></i>

                        Manage Interfaces

                    </a>

                </div>

            <?php else: ?>


                <form method="POST">

                    <input
                        type="hidden"
                        name="action"
                        value="add_connection"
                    >


                    <div class="row g-3">


                        <!-- ==========================================
                             INTERFACE A
                             ========================================== -->

                        <div class="col-lg-5">

                            <label
                                class="form-label fw-semibold"
                            >

                                First Interface

                            </label>


                            <select
                                name="interface_a_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select first interface
                                </option>


                                <?php foreach (
                                    $interfaces
                                    as $interface
                                ): ?>

                                    <option
                                        value="<?= (int)
                                            $interface[
                                                'interface_id'
                                            ] ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $interface[
                                                'instance_name'
                                            ]
                                        ) ?>

                                        -

                                        <?= htmlspecialchars(
                                            $interface[
                                                'interface_name'
                                            ]
                                        ) ?>

                                        |

                                        <?= htmlspecialchars(
                                            $interface[
                                                'device_name'
                                            ]
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>



                        <!-- ==========================================
                             CONNECTION ICON
                             ========================================== -->

                        <div
                            class="col-lg-2 d-flex
                                   align-items-end
                                   justify-content-center"
                        >

                            <div
                                class="text-center
                                       pb-2"
                            >

                                <i
                                    class="bi bi-arrow-left-right"
                                    style="
                                        font-size: 1.8rem;
                                    "
                                ></i>


                                <div
                                    class="small text-muted"
                                >

                                    CONNECT

                                </div>

                            </div>

                        </div>



                        <!-- ==========================================
                             INTERFACE B
                             ========================================== -->

                        <div class="col-lg-5">

                            <label
                                class="form-label fw-semibold"
                            >

                                Second Interface

                            </label>


                            <select
                                name="interface_b_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select second interface
                                </option>


                                <?php foreach (
                                    $interfaces
                                    as $interface
                                ): ?>

                                    <option
                                        value="<?= (int)
                                            $interface[
                                                'interface_id'
                                            ] ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $interface[
                                                'instance_name'
                                            ]
                                        ) ?>

                                        -

                                        <?= htmlspecialchars(
                                            $interface[
                                                'interface_name'
                                            ]
                                        ) ?>

                                        |

                                        <?= htmlspecialchars(
                                            $interface[
                                                'device_name'
                                            ]
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>



                        <!-- ==========================================
                             CONNECTION TYPE
                             ========================================== -->

                        <div class="col-md-4">

                            <label
                                class="form-label fw-semibold"
                            >

                                Connection Type

                            </label>


                            <select
                                name="connection_type"
                                class="form-select"
                            >

                                <option value="Ethernet">
                                    Ethernet
                                </option>

                                <option value="Serial">
                                    Serial
                                </option>

                                <option value="Wireless">
                                    Wireless
                                </option>

                                <option value="Other">
                                    Other
                                </option>

                            </select>

                        </div>



                        <!-- ==========================================
                             NOTES
                             ========================================== -->

                        <div class="col-md-5">

                            <label
                                class="form-label fw-semibold"
                            >

                                Notes

                            </label>


                            <input
                                type="text"
                                name="notes"
                                class="form-control"
                                maxlength="255"
                                placeholder="Optional connection description"
                            >

                        </div>



                        <!-- ==========================================
                             SUBMIT
                             ========================================== -->

                        <div
                            class="col-md-3
                                   d-flex align-items-end"
                        >

                            <button
                                type="submit"
                                class="btn btn-dark w-100"
                            >

                                <i class="bi bi-link-45deg"></i>

                                Create Connection

                            </button>

                        </div>

                    </div>

                </form>


            <?php endif; ?>

        </div>

    </div>



    <!-- ==============================================================
         CONNECTION LIST
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

                    <i class="bi bi-diagram-3"></i>

                    Scenario Connections

                </h5>


                <span class="badge bg-light text-dark">

                    <?= $totalConnections ?>

                </span>

            </div>

        </div>


        <div class="card-body">

            <?php if (empty($connections)): ?>

                <div class="text-center py-5">

                    <i
                        class="bi bi-link-45deg"
                        style="
                            font-size: 3rem;
                            color: #6c757d;
                        "
                    ></i>


                    <h5 class="mt-3">

                        No connections configured

                    </h5>


                    <p class="text-muted mb-0">

                        Connect the available device interfaces
                        above to begin building the network topology.

                    </p>

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
                                    Device A
                                </th>

                                <th class="text-center">
                                    Connection
                                </th>

                                <th>
                                    Device B
                                </th>

                                <th>
                                    Type
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
                            $connections
                            as $connection
                        ): ?>

                            <tr>

                                <!-- ==================================
                                     DEVICE A
                                     ================================== -->

                                <td>

                                    <div class="fw-bold">

                                        <?= htmlspecialchars(
                                            $connection[
                                                'instance_a_name'
                                            ]
                                        ) ?>

                                    </div>


                                    <div class="text-muted small">

                                        <?= htmlspecialchars(
                                            $connection[
                                                'device_a_name'
                                            ]
                                        ) ?>

                                    </div>


                                    <span
                                        class="badge bg-light text-dark mt-1"
                                    >

                                        <?= htmlspecialchars(
                                            $connection[
                                                'interface_a_name'
                                            ]
                                        ) ?>

                                    </span>

                                </td>



                                <!-- ==================================
                                     CONNECTION
                                     ================================== -->

                                <td class="text-center">

                                    <i
                                        class="bi bi-arrow-left-right"
                                        style="
                                            font-size: 1.4rem;
                                        "
                                    ></i>


                                    <div
                                        class="small text-muted"
                                    >

                                        <?= htmlspecialchars(
                                            $connection[
                                                'connection_type'
                                            ]
                                        ) ?>

                                    </div>

                                </td>



                                <!-- ==================================
                                     DEVICE B
                                     ================================== -->

                                <td>

                                    <div class="fw-bold">

                                        <?= htmlspecialchars(
                                            $connection[
                                                'instance_b_name'
                                            ]
                                        ) ?>

                                    </div>


                                    <div class="text-muted small">

                                        <?= htmlspecialchars(
                                            $connection[
                                                'device_b_name'
                                            ]
                                        ) ?>

                                    </div>


                                    <span
                                        class="badge bg-light text-dark mt-1"
                                    >

                                        <?= htmlspecialchars(
                                            $connection[
                                                'interface_b_name'
                                            ]
                                        ) ?>

                                    </span>

                                </td>



                                <!-- ==================================
                                     TYPE
                                     ================================== -->

                                <td>

                                    <?= htmlspecialchars(
                                        $connection[
                                            'connection_type'
                                        ]
                                    ) ?>

                                </td>



                                <!-- ==================================
                                     STATUS
                                     ================================== -->

                                <td>

                                    <?php if (
                                        $connection['status']
                                        === 'Active'
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



                                <!-- ==================================
                                     ACTION
                                     ================================== -->

                                <td class="text-end">

                                    <form
                                        method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm(
                                            'Remove this network connection?'
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="remove_connection"
                                        >


                                        <input
                                            type="hidden"
                                            name="connection_id"
                                            value="<?= (int)
                                                $connection[
                                                    'connection_id'
                                                ] ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Remove Connection"
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
         TOPOLOGY PREPARATION
         ============================================================== -->

    <div class="card dashboard-card mt-4">

        <div class="card-body">

            <div class="row align-items-center">

                <div class="col-md-8">

                    <h5 class="fw-bold mb-1">

                        <i class="bi bi-diagram-3"></i>

                        Network Topology

                    </h5>


                    <p class="text-muted mb-0">

                        The configured devices, interfaces and
                        connections now provide the data required
                        for the scenario topology view.

                    </p>

                </div>


                <div
                    class="col-md-4 text-md-end
                           mt-3 mt-md-0"
                >

                    <span
                        class="badge bg-secondary fs-6"
                    >

                        Phase 3.5

                    </span>

                </div>

            </div>

        </div>

    </div>

</div>


<?php require_once '../includes/layout_end.php'; ?>