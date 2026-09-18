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
    : 0;

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


if (!$scenario) {
    redirect('scenarios.php');
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

        d.device_id,
        d.device_code,
        d.device_name,
        d.device_type,
        d.vendor,
        d.model

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

    WHERE sd.scenario_id = ?
      AND s.created_by = ?

    ORDER BY
        d.device_type ASC,
        sdi.instance_name ASC
");

$instanceStmt->execute([
    $scenarioId,
    $userId
]);

$instances =
    $instanceStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load Interfaces
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
        sdif.interface_status

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

    WHERE sd.scenario_id = ?
      AND s.created_by = ?

    ORDER BY
        sdif.interface_id ASC
");

$interfaceStmt->execute([
    $scenarioId,
    $userId
]);

$interfaces =
    $interfaceStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load Connections
|--------------------------------------------------------------------------
*/

$connectionStmt = db()->prepare("
    SELECT

        sc.connection_id,
        sc.interface_a_id,
        sc.interface_b_id,
        sc.connection_type,
        sc.status,
        sc.notes

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

    INNER JOIN scenario_device_interfaces ib
        ON ib.interface_id =
           sc.interface_b_id

    INNER JOIN scenario_device_instances i2
        ON i2.instance_id =
           ib.instance_id

    INNER JOIN scenario_devices sd2
        ON sd2.scenario_device_id =
           i2.scenario_device_id

    WHERE sd1.scenario_id = ?
      AND sd2.scenario_id = ?
      AND s.created_by = ?

    ORDER BY
        sc.connection_id ASC
");

$connectionStmt->execute([
    $scenarioId,
    $scenarioId,
    $userId
]);

$connections =
    $connectionStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Interface Lookup
|--------------------------------------------------------------------------
*/

$interfaceLookup = [];

foreach ($interfaces as $interface) {

    $interfaceLookup[
        (int) $interface['interface_id']
    ] = $interface;
}


/*
|--------------------------------------------------------------------------
| Device Statistics
|--------------------------------------------------------------------------
*/

$totalInstances =
    count($instances);

$totalInterfaces =
    count($interfaces);

$totalConnections =
    count($connections);


/*
|--------------------------------------------------------------------------
| Prepare Topology Data
|--------------------------------------------------------------------------
*/

$topologyInstances = [];

foreach ($instances as $instance) {

    $instanceId =
        (int) $instance['instance_id'];

    $topologyInstances[] = [

        'id' =>
            $instanceId,

        'name' =>
            $instance['instance_name'],

        'display_name' =>
            $instance['display_name'],

        'device_name' =>
            $instance['device_name'],

        'device_type' =>
            $instance['device_type'],

        'vendor' =>
            $instance['vendor'],

        'model' =>
            $instance['model'],

        'status' =>
            $instance['instance_status']
    ];
}


/*
|--------------------------------------------------------------------------
| Prepare Connection Data
|--------------------------------------------------------------------------
*/

$topologyConnections = [];

foreach ($connections as $connection) {

    $a =
        $interfaceLookup[
            (int) $connection['interface_a_id']
        ] ?? null;

    $b =
        $interfaceLookup[
            (int) $connection['interface_b_id']
        ] ?? null;


    if (!$a || !$b) {
        continue;
    }


    $topologyConnections[] = [

        'id' =>
            (int) $connection['connection_id'],

        'interface_a_id' =>
            (int) $connection['interface_a_id'],

        'interface_b_id' =>
            (int) $connection['interface_b_id'],

        'interface_a_name' =>
            $a['interface_name'],

        'interface_b_name' =>
            $b['interface_name'],

        'instance_a_id' =>
            (int) $a['instance_id'],

        'instance_b_id' =>
            (int) $b['instance_id'],

        'connection_type' =>
            $connection['connection_type'],

        'status' =>
            $connection['status'],

        'notes' =>
            $connection['notes']
    ];
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = "Network Topology";

require_once '../includes/layout_start.php';

?>

<style>

/*
|--------------------------------------------------------------------------
| Topology Workspace
|--------------------------------------------------------------------------
*/

.topology-workspace {

    position: relative;

    min-height: 620px;

    background:
        linear-gradient(
            rgba(0, 0, 0, 0.035) 1px,
            transparent 1px
        ),
        linear-gradient(
            90deg,
            rgba(0, 0, 0, 0.035) 1px,
            transparent 1px
        );

    background-size: 25px 25px;

    border: 1px solid #dee2e6;

    border-radius: 12px;

    overflow: hidden;
}


/*
|--------------------------------------------------------------------------
| SVG Connection Layer
|--------------------------------------------------------------------------
*/

.topology-connections {

    position: absolute;

    inset: 0;

    width: 100%;

    height: 100%;

    pointer-events: none;

    z-index: 1;
}


.topology-line {

    stroke: #6c757d;

    stroke-width: 3;

    fill: none;
}


.topology-line.active {

    stroke: #198754;
}


.topology-line.inactive {

    stroke: #adb5bd;

    stroke-dasharray: 8 6;
}


/*
|--------------------------------------------------------------------------
| Device Node
|--------------------------------------------------------------------------
*/

.topology-node {

    position: absolute;

    width: 190px;

    min-height: 110px;

    background: #ffffff;

    border: 2px solid #0d6efd;

    border-radius: 12px;

    box-shadow:
        0 5px 18px rgba(0, 0, 0, 0.10);

    z-index: 2;

    cursor: grab;

    user-select: none;
}


.topology-node:active {

    cursor: grabbing;
}


.topology-node-header {

    padding: 10px 12px;

    color: #ffffff;

    background: #0d6efd;

    border-radius:
        9px 9px 0 0;

    font-weight: 700;
}


.topology-node-body {

    padding: 10px 12px;

}


.topology-node-type {

    font-size: 12px;

    color: #6c757d;

}


.topology-node-status {

    margin-top: 6px;

    font-size: 12px;

}


/*
|--------------------------------------------------------------------------
| Device Type Icons
|--------------------------------------------------------------------------
*/

.topology-icon {

    font-size: 20px;

    margin-right: 7px;
}


/*
|--------------------------------------------------------------------------
| Legend
|--------------------------------------------------------------------------
*/

.topology-legend {

    display: flex;

    flex-wrap: wrap;

    gap: 18px;

    font-size: 13px;

}


.legend-item {

    display: flex;

    align-items: center;

    gap: 7px;
}


.legend-line {

    width: 30px;

    height: 3px;

    background: #198754;
}


.legend-line.inactive {

    background:
        repeating-linear-gradient(
            90deg,
            #adb5bd 0,
            #adb5bd 7px,
            transparent 7px,
            transparent 13px
        );
}


/*
|--------------------------------------------------------------------------
| Empty Topology
|--------------------------------------------------------------------------
*/

.topology-empty {

    min-height: 600px;

    display: flex;

    align-items: center;

    justify-content: center;

    text-align: center;

    color: #6c757d;
}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 768px) {

    .topology-workspace {

        min-height: 700px;

    }

    .topology-node {

        width: 160px;

    }

}

</style>


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

                Network Topology

            </h2>


            <p class="text-muted mb-0">

                Visual representation of the devices and
                connections configured for this scenario.

            </p>

        </div>


        <div class="d-flex gap-2">


            <a
                href="scenario_connections.php?id=<?= $scenarioId ?>"
                class="btn btn-outline-primary"
            >

                <i class="bi bi-link-45deg"></i>

                Connections

            </a>


            <a
                href="scenario_interfaces.php?id=<?= $scenarioId ?>"
                class="btn btn-outline-secondary"
            >

                <i class="bi bi-ethernet"></i>

                Interfaces

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

                    <div class="d-flex justify-content-md-end
                                gap-3">


                        <div>

                            <small class="text-muted">
                                Devices
                            </small>

                            <div
                                class="badge bg-primary fs-6"
                            >

                                <?= $totalInstances ?>

                            </div>

                        </div>


                        <div>

                            <small class="text-muted">
                                Interfaces
                            </small>

                            <div
                                class="badge bg-secondary fs-6"
                            >

                                <?= $totalInterfaces ?>

                            </div>

                        </div>


                        <div>

                            <small class="text-muted">
                                Connections
                            </small>

                            <div
                                class="badge bg-success fs-6"
                            >

                                <?= $totalConnections ?>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>



    <!-- ==============================================================
         TOPOLOGY CARD
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

                    Scenario Topology

                </h5>


                <span class="badge bg-light text-dark">

                    <?= $totalConnections ?>

                    connections

                </span>

            </div>

        </div>


        <div class="card-body">


            <?php if (empty($instances)): ?>


                <div class="topology-empty">

                    <div>

                        <i
                            class="bi bi-diagram-3"
                            style="
                                font-size: 4rem;
                            "
                        ></i>


                        <h5 class="mt-3">

                            No device instances available

                        </h5>


                        <p class="mb-3">

                            Create device instances before
                            viewing the scenario topology.

                        </p>


                        <a
                            href="scenario_instances.php?id=<?= $scenarioId ?>"
                            class="btn btn-primary"
                        >

                            <i
                                class="bi bi-hdd-network"
                            ></i>

                            Manage Device Instances

                        </a>

                    </div>

                </div>


            <?php else: ?>


                <!-- ==================================================
                     TOPOLOGY WORKSPACE
                     ================================================== -->

                <div
                    id="topologyWorkspace"
                    class="topology-workspace"
                >


                    <!-- SVG CONNECTION LAYER -->

                    <svg
                        id="topologyConnections"
                        class="topology-connections"
                    ></svg>


                    <!-- DEVICE NODES -->

                    <?php

                    /*
                    |--------------------------------------------------------------------------
                    | Initial Node Placement
                    |--------------------------------------------------------------------------
                    |
                    | Nodes are arranged automatically in rows.
                    | JavaScript can later allow full drag/drop positioning.
                    |
                    */

                    $nodeIndex = 0;

                    foreach ($instances as $instance):

                        $nodeIndex++;

                        $column =
                            ($nodeIndex - 1) % 4;

                        $row =
                            floor(
                                ($nodeIndex - 1) / 4
                            );

                        $left =
                            5 + ($column * 24);

                        $top =
                            8 + ($row * 28);


                        $deviceType =
                            $instance['device_type'];


                        $icon = match ($deviceType) {

                            'Router' =>
                                'bi-router',

                            'Switch',
                            'Layer3 Switch' =>
                                'bi-diagram-3',

                            'PC' =>
                                'bi-pc-display',

                            'Server' =>
                                'bi-server',

                            'Firewall' =>
                                'bi-shield-lock',

                            'Access Point',
                            'Wireless Router' =>
                                'bi-wifi',

                            default =>
                                'bi-hdd-network'
                        };

                    ?>

                        <div
                            class="topology-node"
                            id="node-<?= (int)
                                $instance['instance_id'] ?>"
                            data-instance-id="<?= (int)
                                $instance['instance_id'] ?>"
                            style="
                                left: <?= $left ?>%;
                                top: <?= $top ?>%;
                            "
                        >

                            <div
                                class="topology-node-header"
                            >

                                <i
                                    class="bi <?= $icon ?>
                                           topology-icon"
                                ></i>

                                <?= htmlspecialchars(
                                    $instance[
                                        'instance_name'
                                    ]
                                ) ?>

                            </div>


                            <div
                                class="topology-node-body"
                            >

                                <div class="fw-semibold">

                                    <?= htmlspecialchars(
                                        $instance[
                                            'device_name'
                                        ]
                                    ) ?>

                                </div>


                                <div
                                    class="topology-node-type"
                                >

                                    <?= htmlspecialchars(
                                        $deviceType
                                    ) ?>

                                    <?php if (
                                        !empty(
                                            $instance['model']
                                        )
                                    ): ?>

                                        —

                                        <?= htmlspecialchars(
                                            $instance['model']
                                        ) ?>

                                    <?php endif; ?>

                                </div>


                                <div
                                    class="topology-node-status"
                                >

                                    <span
                                        class="badge
                                        <?= $instance[
                                            'instance_status'
                                        ] === 'Active'
                                            ? 'bg-success'
                                            : 'bg-secondary'
                                        ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $instance[
                                                'instance_status'
                                            ]
                                        ) ?>

                                    </span>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>


                </div>


                <!-- ==================================================
                     LEGEND
                     ================================================== -->

                <div
                    class="topology-legend mt-3"
                >

                    <div class="legend-item">

                        <span
                            class="legend-line"
                        ></span>

                        Active Connection

                    </div>


                    <div class="legend-item">

                        <span
                            class="legend-line inactive"
                        ></span>

                        Inactive Connection

                    </div>

                </div>


            <?php endif; ?>


        </div>

    </div>



    <!-- ==============================================================
         CONNECTION DETAILS
         ============================================================== -->

    <?php if (!empty($connections)): ?>

        <div class="card dashboard-card mt-4">

            <div class="card-header bg-dark text-white">

                <h5 class="mb-0">

                    <i class="bi bi-link-45deg"></i>

                    Connection Details

                </h5>

            </div>


            <div class="card-body">

                <div class="table-responsive">

                    <table
                        class="table table-hover
                               align-middle mb-0"
                    >

                        <thead class="table-light">

                            <tr>

                                <th>
                                    From
                                </th>

                                <th class="text-center">
                                    Type
                                </th>

                                <th>
                                    To
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach (
                            $topologyConnections
                            as $connection
                        ): ?>

                            <tr>

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $interfaceLookup[
                                                $connection[
                                                    'interface_a_id'
                                                ]
                                            ]['interface_name']
                                        ) ?>

                                    </strong>

                                </td>


                                <td class="text-center">

                                    <span
                                        class="badge bg-light text-dark"
                                    >

                                        <?= htmlspecialchars(
                                            $connection[
                                                'connection_type'
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $interfaceLookup[
                                                $connection[
                                                    'interface_b_id'
                                                ]
                                            ]['interface_name']
                                        ) ?>

                                    </strong>

                                </td>


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

                                            <?= htmlspecialchars(
                                                $connection[
                                                    'status'
                                                ]
                                            ) ?>

                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    <?php endif; ?>



    <!-- ==============================================================
         NEXT PHASE
         ============================================================== -->

    <div class="card dashboard-card mt-4">

        <div class="card-body">

            <div class="row align-items-center">

                <div class="col-md-8">

                    <h5 class="fw-bold mb-1">

                        <i class="bi bi-bug"></i>

                        Next: Troubleshooting Exercise Engine

                    </h5>


                    <p class="text-muted mb-0">

                        The completed topology provides the network
                        structure that will be used for fault injection
                        and student troubleshooting exercises.

                    </p>

                </div>


                <div
                    class="col-md-4 text-md-end
                           mt-3 mt-md-0"
                >

                    <span
                        class="badge bg-secondary fs-6"
                    >

                        Phase 4

                    </span>

                </div>

            </div>

        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| VNDTES Topology Visualization
|--------------------------------------------------------------------------
|
| Draws SVG lines between the centres of connected device nodes.
|
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const workspace =
            document.getElementById(
                'topologyWorkspace'
            );

        const svg =
            document.getElementById(
                'topologyConnections'
            );


        if (!workspace || !svg) {
            return;
        }


        const connections =
            <?= json_encode(
                $topologyConnections,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            ) ?>;


        const nodes =
            document.querySelectorAll(
                '.topology-node'
            );


        function drawConnections() {

            svg.innerHTML = '';


            const workspaceRect =
                workspace.getBoundingClientRect();


            connections.forEach(
                function (connection) {

                    const nodeA =
                        document.getElementById(
                            'node-' +
                            connection.instance_a_id
                        );


                    const nodeB =
                        document.getElementById(
                            'node-' +
                            connection.instance_b_id
                        );


                    if (!nodeA || !nodeB) {
                        return;
                    }


                    const rectA =
                        nodeA.getBoundingClientRect();


                    const rectB =
                        nodeB.getBoundingClientRect();


                    const x1 =
                        rectA.left -
                        workspaceRect.left +
                        (rectA.width / 2);


                    const y1 =
                        rectA.top -
                        workspaceRect.top +
                        (rectA.height / 2);


                    const x2 =
                        rectB.left -
                        workspaceRect.left +
                        (rectB.width / 2);


                    const y2 =
                        rectB.top -
                        workspaceRect.top +
                        (rectB.height / 2);


                    const line =
                        document.createElementNS(
                            'http://www.w3.org/2000/svg',
                            'line'
                        );


                    line.setAttribute(
                        'x1',
                        x1
                    );

                    line.setAttribute(
                        'y1',
                        y1
                    );

                    line.setAttribute(
                        'x2',
                        x2
                    );

                    line.setAttribute(
                        'y2',
                        y2
                    );


                    line.classList.add(
                        'topology-line'
                    );


                    if (
                        connection.status ===
                        'Active'
                    ) {

                        line.classList.add(
                            'active'
                        );

                    } else {

                        line.classList.add(
                            'inactive'
                        );
                    }


                    svg.appendChild(line);

                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Initial Drawing
        |--------------------------------------------------------------------------
        */

        drawConnections();


        /*
        |--------------------------------------------------------------------------
        | Redraw When Window Changes
        |--------------------------------------------------------------------------
        */

        window.addEventListener(
            'resize',
            drawConnections
        );


        /*
        |--------------------------------------------------------------------------
        | Simple Dragging
        |--------------------------------------------------------------------------
        |
        | Allows lecturer to arrange the devices visually.
        |
        |--------------------------------------------------------------------------
        */

        nodes.forEach(
            function (node) {

                let dragging = false;

                let offsetX = 0;

                let offsetY = 0;


                node.addEventListener(
                    'mousedown',
                    function (event) {

                        dragging = true;

                        const rect =
                            node.getBoundingClientRect();


                        offsetX =
                            event.clientX -
                            rect.left;


                        offsetY =
                            event.clientY -
                            rect.top;

                    }
                );


                document.addEventListener(
                    'mousemove',
                    function (event) {

                        if (!dragging) {
                            return;
                        }


                        const workspaceRect =
                            workspace.getBoundingClientRect();


                        let left =
                            event.clientX -
                            workspaceRect.left -
                            offsetX;


                        let top =
                            event.clientY -
                            workspaceRect.top -
                            offsetY;


                        const maxLeft =
                            workspaceRect.width -
                            node.offsetWidth;


                        const maxTop =
                            workspaceRect.height -
                            node.offsetHeight;


                        left =
                            Math.max(
                                0,
                                Math.min(
                                    left,
                                    maxLeft
                                )
                            );


                        top =
                            Math.max(
                                0,
                                Math.min(
                                    top,
                                    maxTop
                                )
                            );


                        node.style.left =
                            left + 'px';

                        node.style.top =
                            top + 'px';


                        drawConnections();

                    }
                );


                document.addEventListener(
                    'mouseup',
                    function () {

                        dragging = false;

                    }
                );

            }
        );

    }
);

</script>


<?php require_once '../includes/layout_end.php'; ?>