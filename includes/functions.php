<?php

require_once __DIR__ . '/db.php';

/**
 * Return PDO instance
 */
function db()
{
    global $pdo;

    return $pdo;
}

/**
 * Clean user input
 */
function sanitize($value)
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to another page
 */
function redirect($url)
{
    if (!headers_sent()) {
        header("Location: $url");
    } else {
        echo "<script>window.location.href='{$url}';</script>";
    }

    exit;
}

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
   return !empty($_SESSION['user_id']);
}

/**
 * Require authentication
 */
function requireLogin()
{
    if (!isLoggedIn()) {

        redirect(APP_URL . '/auth/login.php');

    }
}

/**
 * Get logged-in user ID
 */
function currentUserId()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get logged-in user's role
 */
function currentUserRole()
{
    return $_SESSION['role_name'] ?? null;
}

/**
 * Check user role
 */
function hasRole($role)
{
    return currentUserRole() === $role;
}

/**
 * Display Bootstrap alert
 *
 * @param string $message
 * @param string $type
 * @return string
 */
function alert($message, $type = 'primary')
{
    return '
    <div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">

        '.$message.'

        <button type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close">
        </button>

    </div>';
}

/**
 * Get logged in user's full name
 */
function currentUserName()
{
    return $_SESSION['full_name'] ?? '';
}

/**
 * Get profile photo
 */
function currentUserPhoto()
{
    return $_SESSION['profile_photo'] ?? 'default.png';
}

/**
 * Require a specific role
 */
function requireRole($role)
{
    requireLogin();

    if (!hasRole($role)) {

        redirect(APP_URL);

    }
}

function getDepartments()
{
    return db()->query("
        SELECT *
        FROM departments
        ORDER BY department_name
    ")->fetchAll();
}

function getProgrammeTypes()
{
    return db()->query("
        SELECT *
        FROM programme_types
        ORDER BY programme_name
    ")->fetchAll();
}

function getProgrammeOptions()
{
    return db()->query("
        SELECT *
        FROM programme_options
        ORDER BY option_name
    ")->fetchAll();
}

function getLevels()
{
    return db()->query("
        SELECT *
        FROM levels
        ORDER BY level_id
    ")->fetchAll();
}

function getAcademicSessions()
{
    return db()->query("
        SELECT *
        FROM academic_sessions
        ORDER BY session_id DESC
    ")->fetchAll();
}

/*
|--------------------------------------------------------------------------
| Find Student
|--------------------------------------------------------------------------
*/

function findStudent($studentID)
{
    $stmt = db()->prepare("

        SELECT

            students.*,

            users.full_name,
            users.username,
            users.email,
            users.profile_photo,
            users.account_status,

            departments.department_name,

            programme_types.programme_name,

            programme_options.option_name,

            levels.level_name,

            academic_sessions.session_name

        FROM students

        INNER JOIN users
            ON users.user_id = students.user_id

        INNER JOIN departments
            ON departments.department_id = students.department_id

        INNER JOIN programme_types
            ON programme_types.programme_type_id = students.programme_type_id

        LEFT JOIN programme_options
            ON programme_options.option_id = students.option_id

        INNER JOIN levels
            ON levels.level_id = students.level_id

        INNER JOIN academic_sessions
            ON academic_sessions.session_id = students.current_session_id

        WHERE students.student_id = ?

        LIMIT 1

    ");

    $stmt->execute([$studentID]);

    return $stmt->fetch();
}

function findLecturer($lecturerID)
{
    $stmt=db()->prepare("

    SELECT

    lecturers.*,

    users.full_name,
    users.username,
    users.email,
    users.profile_photo,
    users.account_status,

    departments.department_name

    FROM lecturers

    INNER JOIN users
    ON users.user_id=lecturers.user_id

    INNER JOIN departments
    ON departments.department_id=lecturers.department_id

    WHERE lecturer_id=?

    LIMIT 1

    ");

    $stmt->execute([$lecturerID]);

    return $stmt->fetch();
}

function findScenario($scenarioID)
{
    $stmt = db()->prepare("

        SELECT

            scenarios.*,

            users.full_name

        FROM scenarios

        INNER JOIN users
            ON users.user_id = scenarios.created_by

        WHERE scenario_id = ?

        LIMIT 1

    ");

    $stmt->execute([$scenarioID]);

    return $stmt->fetch();
}

function findDevice($deviceID)
{
    $stmt=db()->prepare("

        SELECT *

        FROM devices

        WHERE device_id=?

        LIMIT 1

    ");

    $stmt->execute([$deviceID]);

    return $stmt->fetch();
}
/*
|--------------------------------------------------------------------------
| Scenario Device Mapping Functions
|--------------------------------------------------------------------------
*/

/**
 * Get all scenario-device mappings
 */
function getScenarioDeviceMappings()
{
    $stmt = db()->query("
        SELECT

            sd.scenario_device_id,
            sd.scenario_id,
            sd.device_id,
            sd.quantity,
            sd.notes,
            sd.created_at,

            s.scenario_code,
            s.scenario_title,

            d.device_code,
            d.device_name,
            d.device_type,
            d.vendor,
            d.model

        FROM scenario_devices sd

        INNER JOIN scenarios s
            ON sd.scenario_id = s.scenario_id

        INNER JOIN devices d
            ON sd.device_id = d.device_id

        ORDER BY
            s.scenario_title ASC,
            d.device_name ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Get all devices assigned to a particular scenario
 */
function getScenarioDevices($scenarioID)
{
    $stmt = db()->prepare("
        SELECT

            sd.*,

            d.device_code,
            d.device_name,
            d.device_type,
            d.vendor,
            d.model

        FROM scenario_devices sd

        INNER JOIN devices d
            ON sd.device_id=d.device_id

        WHERE sd.scenario_id=?

        ORDER BY d.device_name ASC
    ");

    $stmt->execute([$scenarioID]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Find one mapping by ID
 */
function findScenarioDevice($mappingID)
{
    $stmt = db()->prepare("
        SELECT

            sd.*,

            s.scenario_code,
            s.scenario_title,

            d.device_code,
            d.device_name,
            d.device_type,
            d.vendor,
            d.model

        FROM scenario_devices sd

        INNER JOIN scenarios s
            ON sd.scenario_id=s.scenario_id

        INNER JOIN devices d
            ON sd.device_id=d.device_id

        WHERE sd.scenario_device_id=?

        LIMIT 1
    ");

    $stmt->execute([$mappingID]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}


/**
 * Count mappings
 */
function countScenarioDeviceMappings()
{
    return db()
        ->query("SELECT COUNT(*) FROM scenario_devices")
        ->fetchColumn();
}


/**
 * Check whether a device is already assigned to a scenario
 */
function scenarioDeviceExists($scenarioID,$deviceID,$excludeID=null)
{
    $sql="
        SELECT COUNT(*)
        FROM scenario_devices
        WHERE scenario_id=?
        AND device_id=?
    ";

    $params=[
        $scenarioID,
        $deviceID
    ];

    if($excludeID!==null){

        $sql.=" AND scenario_device_id<>?";

        $params[]=$excludeID;
    }

    $stmt=db()->prepare($sql);

    $stmt->execute($params);

    return $stmt->fetchColumn()>0;
}

function countDevicesForScenario($scenarioID)
{
    $stmt=db()->prepare("
        SELECT SUM(quantity)
        FROM scenario_devices
        WHERE scenario_id=?
    ");

    $stmt->execute([$scenarioID]);

    return (int)($stmt->fetchColumn() ?? 0);
}

function getScenarios()
{
    $stmt = db()->query("
        SELECT *
        FROM scenarios
        ORDER BY scenario_title ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDevices()
{
    $stmt = db()->query("
        SELECT *
        FROM devices
        ORDER BY device_name ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}