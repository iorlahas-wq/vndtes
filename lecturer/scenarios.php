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

$pageTitle = "My Scenarios";

$userId = currentUserId();


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$status   = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';


/*
|--------------------------------------------------------------------------
| Build Query
|--------------------------------------------------------------------------
|
| Lecturers can only see scenarios created by themselves.
|
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        s.scenario_id,
        s.scenario_code,
        s.scenario_title,
        s.category,
        s.difficulty,
        s.estimated_time,
        s.status,
        s.created_at,
        s.updated_at,

        COUNT(DISTINCT sd.scenario_device_id) AS device_count

    FROM scenarios s

    LEFT JOIN scenario_devices sd
        ON sd.scenario_id = s.scenario_id

    WHERE s.created_by = ?

";

$params = [$userId];


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($status !== '') {

    $sql .= " AND s.status = ? ";

    $params[] = $status;
}


/*
|--------------------------------------------------------------------------
| Category Filter
|--------------------------------------------------------------------------
*/

if ($category !== '') {

    $sql .= " AND s.category = ? ";

    $params[] = $category;
}


/*
|--------------------------------------------------------------------------
| Grouping
|--------------------------------------------------------------------------
*/

$sql .= "
    GROUP BY
        s.scenario_id,
        s.scenario_code,
        s.scenario_title,
        s.category,
        s.difficulty,
        s.estimated_time,
        s.status,
        s.created_at,
        s.updated_at

    ORDER BY s.updated_at DESC
";


/*
|--------------------------------------------------------------------------
| Execute Query
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare($sql);
$stmt->execute($params);

$scenarios = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

require_once '../includes/layout_start.php';

?>

<style>

/*
|--------------------------------------------------------------------------
| Scenario Action Toolbar
|--------------------------------------------------------------------------
*/

.scenario-actions {

    display: flex;

    justify-content: flex-end;

    align-items: center;

    flex-wrap: wrap;

    gap: 5px;

}


/*
|--------------------------------------------------------------------------
| Scenario Action Buttons
|--------------------------------------------------------------------------
*/

.scenario-action {

    width: 34px;

    height: 34px;

    padding: 0;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border-radius: 8px !important;

    transition:
        transform 0.15s ease,
        box-shadow 0.15s ease;

}


.scenario-action i {

    font-size: 15px;

}


.scenario-action:hover {

    transform: translateY(-1px);

    box-shadow:
        0 3px 8px rgba(0, 0, 0, 0.12);

}


/*
|--------------------------------------------------------------------------
| Workflow Separators
|--------------------------------------------------------------------------
*/

.scenario-action-separator {

    width: 1px;

    height: 24px;

    background: #dee2e6;

    margin: 0 2px;

}


/*
|--------------------------------------------------------------------------
| Action Column
|--------------------------------------------------------------------------
*/

.scenario-actions-cell {

    min-width: 350px;

}


/*
|--------------------------------------------------------------------------
| Workflow Hint
|--------------------------------------------------------------------------
*/

.scenario-workflow {

    font-size: 11px;

    color: #6c757d;

    margin-top: 5px;

    text-align: right;

}


/*
|--------------------------------------------------------------------------
| Mobile Action Column
|--------------------------------------------------------------------------
*/

@media (max-width: 768px) {

    .scenario-actions-cell {

        min-width: 260px;

    }

    .scenario-actions {

        justify-content: flex-start;

    }

    .scenario-workflow {

        text-align: left;

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
                My Scenarios
            </h2>

            <p class="text-muted mb-0">
                Create, configure and manage your network troubleshooting
                scenarios.
            </p>

        </div>


        <a
            href="scenario_add.php"
            class="btn btn-primary"
        >

            <i class="bi bi-plus-circle"></i>

            Create Scenario

        </a>

    </div>



    <!-- ==============================================================
         FILTERS
         ============================================================== -->

    <div class="card dashboard-card mb-4">

        <div class="card-body">

            <form
                method="GET"
                class="row g-3 align-items-end"
            >


                <!-- Status -->

                <div class="col-md-4">

                    <label class="form-label">
                        Status
                    </label>

                    <select
                        name="status"
                        class="form-select"
                    >

                        <option value="">
                            All Statuses
                        </option>

                        <option
                            value="Draft"
                            <?= $status === 'Draft' ? 'selected' : '' ?>
                        >
                            Draft
                        </option>

                        <option
                            value="Published"
                            <?= $status === 'Published' ? 'selected' : '' ?>
                        >
                            Published
                        </option>

                        <option
                            value="Archived"
                            <?= $status === 'Archived' ? 'selected' : '' ?>
                        >
                            Archived
                        </option>

                    </select>

                </div>



                <!-- Category -->

                <div class="col-md-4">

                    <label class="form-label">
                        Category
                    </label>

                    <select
                        name="category"
                        class="form-select"
                    >

                        <option value="">
                            All Categories
                        </option>

                        <option
                            value="Routing"
                            <?= $category === 'Routing' ? 'selected' : '' ?>
                        >
                            Routing
                        </option>

                        <option
                            value="Switching"
                            <?= $category === 'Switching' ? 'selected' : '' ?>
                        >
                            Switching
                        </option>

                        <option
                            value="Subnetting"
                            <?= $category === 'Subnetting' ? 'selected' : '' ?>
                        >
                            Subnetting
                        </option>

                        <option
                            value="Wireless"
                            <?= $category === 'Wireless' ? 'selected' : '' ?>
                        >
                            Wireless
                        </option>

                        <option
                            value="Security"
                            <?= $category === 'Security' ? 'selected' : '' ?>
                        >
                            Security
                        </option>

                        <option
                            value="Network Services"
                            <?= $category === 'Network Services' ? 'selected' : '' ?>
                        >
                            Network Services
                        </option>

                        <option
                            value="Mixed"
                            <?= $category === 'Mixed' ? 'selected' : '' ?>
                        >
                            Mixed
                        </option>

                    </select>

                </div>



                <!-- Filter Buttons -->

                <div class="col-md-4">

                    <div class="d-flex gap-2">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            <i class="bi bi-filter"></i>

                            Filter

                        </button>


                        <a
                            href="scenarios.php"
                            class="btn btn-outline-secondary"
                        >

                            Reset

                        </a>

                    </div>

                </div>

            </form>

        </div>

    </div>



    <!-- ==============================================================
         SCENARIO LIST
         ============================================================== -->

    <div class="card dashboard-card">

        <div class="card-header bg-white">

            <div
                class="d-flex justify-content-between
                       align-items-center"
            >

                <h5 class="mb-0 fw-bold">
                    My Scenarios
                </h5>


                <span class="badge bg-primary">
                    <?= count($scenarios) ?>
                </span>

            </div>

        </div>



        <div class="card-body p-0">


            <?php if (empty($scenarios)): ?>


                <!-- ==================================================
                     EMPTY STATE
                     ================================================== -->

                <div class="text-center py-5">

                    <div class="mb-3">

                        <i
                            class="bi bi-diagram-3"
                            style="
                                font-size: 3rem;
                                color: #6c757d;
                            "
                        ></i>

                    </div>


                    <h5>
                        No scenarios found
                    </h5>


                    <p class="text-muted">

                        You have not created any scenarios yet.

                    </p>


                    <a
                        href="scenario_add.php"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-plus-circle"></i>

                        Create Your First Scenario

                    </a>

                </div>


            <?php else: ?>


                <!-- ==================================================
                     TABLE
                     ================================================== -->

                <div class="table-responsive">

                    <table
                        class="table table-hover align-middle mb-0"
                    >


                        <thead class="table-light">

                            <tr>

                                <th>
                                    Code
                                </th>

                                <th>
                                    Scenario
                                </th>

                                <th>
                                    Category
                                </th>

                                <th>
                                    Difficulty
                                </th>

                                <th class="text-center">
                                    Devices
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Updated
                                </th>

                                <th class="text-end">
                                    Actions
                                </th>

                            </tr>

                        </thead>



                        <tbody>


                        <?php foreach ($scenarios as $scenario): ?>


                            <?php

                            /*
                            |--------------------------------------------------------------------------
                            | Scenario ID
                            |--------------------------------------------------------------------------
                            */

                            $scenarioId =
                                (int) $scenario['scenario_id'];


                            /*
                            |--------------------------------------------------------------------------
                            | Difficulty Badge
                            |--------------------------------------------------------------------------
                            */

                            $difficultyClass = match (
                                $scenario['difficulty']
                            ) {

                                'Beginner' =>
                                    'bg-success',

                                'Intermediate' =>
                                    'bg-warning text-dark',

                                'Advanced' =>
                                    'bg-danger',

                                default =>
                                    'bg-secondary'
                            };


                            /*
                            |--------------------------------------------------------------------------
                            | Status Badge
                            |--------------------------------------------------------------------------
                            */

                            $statusClass = match (
                                $scenario['status']
                            ) {

                                'Published' =>
                                    'bg-success',

                                'Draft' =>
                                    'bg-warning text-dark',

                                'Archived' =>
                                    'bg-secondary',

                                default =>
                                    'bg-secondary'
                            };

                            ?>


                            <tr>


                                <!-- ======================================
                                     SCENARIO CODE
                                     ====================================== -->

                                <td>

                                    <span class="fw-semibold">

                                        <?= htmlspecialchars(
                                            $scenario['scenario_code']
                                        ) ?>

                                    </span>

                                </td>



                                <!-- ======================================
                                     SCENARIO
                                     ====================================== -->

                                <td>

                                    <div class="fw-semibold">

                                        <?= htmlspecialchars(
                                            $scenario['scenario_title']
                                        ) ?>

                                    </div>


                                    <small class="text-muted">

                                        <?= (int)
                                            $scenario['estimated_time']
                                        ?>
                                        mins

                                    </small>

                                </td>



                                <!-- ======================================
                                     CATEGORY
                                     ====================================== -->

                                <td>

                                    <?= htmlspecialchars(
                                        $scenario['category']
                                    ) ?>

                                </td>



                                <!-- ======================================
                                     DIFFICULTY
                                     ====================================== -->

                                <td>

                                    <span
                                        class="badge <?= $difficultyClass ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $scenario['difficulty']
                                        ) ?>

                                    </span>

                                </td>



                                <!-- ======================================
                                     DEVICES
                                     ====================================== -->

                                <td class="text-center">

                                    <span
                                        class="badge bg-light text-dark"
                                        title="Configured scenario device types"
                                    >

                                        <i class="bi bi-cpu"></i>

                                        <?= (int)
                                            $scenario['device_count']
                                        ?>

                                    </span>

                                </td>



                                <!-- ======================================
                                     STATUS
                                     ====================================== -->

                                <td>

                                    <span
                                        class="badge <?= $statusClass ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $scenario['status']
                                        ) ?>

                                    </span>

                                </td>



                                <!-- ======================================
                                     UPDATED
                                     ====================================== -->

                                <td>

                                    <small class="text-muted">

                                        <?= htmlspecialchars(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    $scenario['updated_at']
                                                )
                                            )
                                        ) ?>

                                    </small>

                                </td>



                                <!-- ======================================
                                     ACTIONS
                                     ====================================== -->

                                <td
                                    class="text-end
                                           scenario-actions-cell"
                                >

                                    <div
                                        class="scenario-actions"
                                        role="toolbar"
                                        aria-label="Scenario workflow actions"
                                    >


                                        <!-- =================================
                                             1. VIEW
                                             ================================= -->

                                        <a
                                            href="scenario_view.php?id=<?= $scenarioId ?>"
                                            class="btn btn-sm btn-outline-primary scenario-action"
                                            title="View Scenario"
                                            aria-label="View Scenario"
                                            data-bs-toggle="tooltip"
                                        >

                                            <i class="bi bi-eye"></i>

                                        </a>



                                        <!-- =================================
                                             2. EDIT
                                             ================================= -->

                                        <a
                                            href="scenario_edit.php?id=<?= $scenarioId ?>"
                                            class="btn btn-sm btn-outline-secondary scenario-action"
                                            title="Edit Scenario"
                                            aria-label="Edit Scenario"
                                            data-bs-toggle="tooltip"
                                        >

                                            <i class="bi bi-pencil"></i>

                                        </a>



                                        <!-- WORKFLOW SEPARATOR -->

                                        <span
                                            class="scenario-action-separator"
                                            aria-hidden="true"
                                        ></span>



                                        <!-- =================================
                                             3. SCENARIO DEVICES
                                             ================================= -->

                                        <a
                                            href="scenario_devices.php?id=<?= $scenarioId ?>"
                                            class="btn btn-sm btn-outline-dark scenario-action"
                                            title="Configure Scenario Devices"
                                            aria-label="Configure Scenario Devices"
                                            data-bs-toggle="tooltip"
                                        >

                                            <i class="bi bi-router"></i>

                                        </a>



                                        <!-- =================================
                                             4. DEVICE INSTANCES
                                             ================================= -->

                                        <a
                                            href="scenario_instances.php?id=<?= $scenarioId ?>"
                                            class="btn btn-sm btn-outline-info scenario-action"
                                            title="Create Device Instances"
                                            aria-label="Create Device Instances"
                                            data-bs-toggle="tooltip"
                                        >

                                            <i class="bi bi-hdd-network"></i>

                                        </a>



                                        <!-- =================================
                                             5. INTERFACES
                                             ================================= -->

                                        <a
                                            href="scenario_interfaces.php?id=<?= $scenarioId ?>"
                                            class="btn btn-sm btn-outline-success scenario-action"
                                            title="Configure Interfaces and Ports"
                                            aria-label="Configure Interfaces and Ports"
                                            data-bs-toggle="tooltip"
                                        >

                                            <i class="bi bi-ethernet"></i>

                                        </a>



                                        <!-- =================================
                                             6. CONNECTIONS
                                             ================================= -->

                                        <a
                                            href="scenario_connections.php?id=<?= $scenarioId ?>"
                                            class="btn btn-sm btn-outline-warning scenario-action"
                                            title="Configure Network Connections"
                                            aria-label="Configure Network Connections"
                                            data-bs-toggle="tooltip"
                                        >

                                            <i class="bi bi-link-45deg"></i>

                                        </a>



                                        <!-- =================================
                                             7. TOPOLOGY
                                             ================================= -->

                                        <a
                                            href="scenario_topology.php?id=<?= $scenarioId ?>"
                                            class="btn btn-sm btn-outline-primary scenario-action"
                                            title="View Network Topology"
                                            aria-label="View Network Topology"
                                            data-bs-toggle="tooltip"
                                        >

                                            <i class="bi bi-diagram-3"></i>

                                        </a>



                                        <!-- WORKFLOW SEPARATOR -->

                                        <span
                                            class="scenario-action-separator"
                                            aria-hidden="true"
                                        ></span>



                                        <!-- =================================
                                             8. SCENARIO FAULTS
                                             ================================= -->

                                        <a
                                            href="scenario_faults.php?id=<?= $scenarioId ?>"
                                            class="btn btn-sm btn-outline-danger scenario-action"
                                            title="Configure Scenario Faults"
                                            aria-label="Configure Scenario Faults"
                                            data-bs-toggle="tooltip"
                                        >

                                            <i class="bi bi-bug"></i>

                                        </a>



                                        <!-- =================================
                                             9. DELETE
                                             ================================= -->

                                        <a
                                            href="scenario_delete.php?id=<?= $scenarioId ?>"
                                            class="btn btn-sm btn-outline-danger scenario-action"
                                            title="Delete Scenario"
                                            aria-label="Delete Scenario"
                                            data-bs-toggle="tooltip"
                                            onclick="return confirm(
                                                'Are you sure you want to delete this scenario? This action cannot be undone.'
                                            );"
                                        >

                                            <i class="bi bi-trash"></i>

                                        </a>


                                    </div>


                                    <div
                                        class="scenario-workflow"
                                    >

                                        Configure:

                                        <strong>Devices</strong>
                                        →
                                        <strong>Instances</strong>
                                        →
                                        <strong>Interfaces</strong>
                                        →
                                        <strong>Connections</strong>
                                        →
                                        <strong>Topology</strong>

                                    </div>

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


<?php require_once '../includes/layout_end.php'; ?>