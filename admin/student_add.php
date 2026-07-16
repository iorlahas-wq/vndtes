<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

if (currentUserRole() !== 'Administrator') {

    redirect(APP_URL);

}

$pageTitle = "Register Student";

$message = "";

/*
|--------------------------------------------------------------------------
| Load Dropdown Data
|--------------------------------------------------------------------------
*/

$departments = db()->query("
SELECT *
FROM departments
ORDER BY department_name
")->fetchAll();

$programmeTypes = db()->query("
SELECT *
FROM programme_types
ORDER BY programme_name
")->fetchAll();

$programmeOptions = db()->query("
SELECT *
FROM programme_options
ORDER BY option_name
")->fetchAll();

$levels = db()->query("
SELECT *
FROM levels
ORDER BY level_id
")->fetchAll();

$sessions = db()->query("
SELECT *
FROM academic_sessions
ORDER BY session_id DESC
")->fetchAll();

/*
|--------------------------------------------------------------------------
| Save Student
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Collect Data
    |--------------------------------------------------------------------------
    */

    $fullName = sanitize($_POST['full_name']);
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    $matricNo = sanitize($_POST['matric_no']);

    $departmentID = (int)$_POST['department_id'];
    $programmeTypeID = (int)$_POST['programme_type_id'];
    $optionID = !empty($_POST['option_id']) ? (int)$_POST['option_id'] : null;
    $levelID = (int)$_POST['level_id'];

    $currentSessionID = (int)$_POST['current_session_id'];

    $admissionYear = $_POST['admission_year'];

    $graduationYear = !empty($_POST['graduation_year'])
        ? $_POST['graduation_year']
        : null;

    $currentSemester = $_POST['current_semester'];

    $academicStatus = $_POST['academic_status'];

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    $errors = [];

    if ($fullName == "")
        $errors[] = "Full name is required.";

    if ($username == "")
        $errors[] = "Username is required.";

    if (strlen($password) < 6)
        $errors[] = "Password must be at least 6 characters.";

    if ($matricNo == "")
        $errors[] = "Matric number is required.";

    /*
    |--------------------------------------------------------------------------
    | Duplicate Username
    |--------------------------------------------------------------------------
    */

    $stmt = db()->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE username=?
    ");

    $stmt->execute([$username]);

    if ($stmt->fetchColumn()) {

        $errors[] = "Username already exists.";

    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate Email
    |--------------------------------------------------------------------------
    */

    if (!empty($email)) {

        $stmt = db()->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE email=?
        ");

        $stmt->execute([$email]);

        if ($stmt->fetchColumn()) {

            $errors[] = "Email already exists.";

        }

    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate Matric Number
    |--------------------------------------------------------------------------
    */

    $stmt = db()->prepare("
        SELECT COUNT(*)
        FROM students
        WHERE matric_no=?
    ");

    $stmt->execute([$matricNo]);

    if ($stmt->fetchColumn()) {

        $errors[] = "Matric Number already exists.";

    }

    /*
    |--------------------------------------------------------------------------
    | Save Record
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            db()->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Insert User
            |--------------------------------------------------------------------------
            */

            $stmt = db()->prepare("

                INSERT INTO users (

                    role_id,
                    full_name,
                    username,
                    email,
                    password_hash,
                    profile_photo,
                    account_status,
                    created_by

                )

                VALUES (

                    ?,?,?,?,?,?,?,?

                )

            ");

            $stmt->execute([

                3, // Student

                $fullName,

                $username,

                $email,

                password_hash($password, PASSWORD_DEFAULT),

                'default.png',

                'Active',

                currentUserId()

            ]);

            $userID = db()->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | Insert Student
            |--------------------------------------------------------------------------
            */

            $stmt = db()->prepare("

                INSERT INTO students (

                    user_id,
                    matric_no,
                    department_id,
                    programme_type_id,
                    option_id,
                    level_id,
                    current_session_id,
                    admission_year,
                    graduation_year,
                    current_semester,
                    academic_status

                )

                VALUES (

                    ?,?,?,?,?,?,?,?,?,?,?

                )

            ");

            $stmt->execute([

                $userID,

                $matricNo,

                $departmentID,

                $programmeTypeID,

                $optionID,

                $levelID,

                $currentSessionID,

                $admissionYear,

                $graduationYear,

                $currentSemester,

                $academicStatus

            ]);

            db()->commit();

            redirect(APP_URL . "/admin/students.php?success=1");

        }

        catch (Throwable $e) {

            db()->rollBack();

            $message = alert(
    "Unable to save the student record. Please try again.",
    "danger"
);

        }

    }

    else {

        $message = alert(

            implode("<br>", $errors),

            "danger"

        );

    }

}

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">

                <i class="bi bi-person-vcard-fill"></i>

                Register New Student

            </h2>

            <p class="text-muted">

                Create a new student account and academic record.

            </p>

        </div>

        <a href="students.php" class="btn btn-outline-secondary">

            <i class="bi bi-arrow-left"></i>

            Back

        </a>

    </div>

    <?= $message ?>

    <form method="POST" autocomplete="off">

        <div class="row">

            <!-- LEFT COLUMN -->

            <div class="col-lg-6">

                <div class="card shadow-sm border-0 mb-4">

                    <div class="card-header bg-primary text-white">

                        <strong>

                            Personal Information

                        </strong>

                    </div>

                    <div class="card-body">

                        <div class="mb-3">

                            <label class="form-label">

                                Full Name

                            </label>

                            <input
                                type="text"
                                name="full_name"
                                class="form-control"
                                value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                                required>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">

                                Username

                            </label>

                            <input
                                type="text"
                                name="username"
                                class="form-control"
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                required>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">

                                Email Address

                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

                        </div>

                        <div class="mb-3">

                            <label class="form-label">

                                Password

                            </label>

                            <input
                                type="password"
                                name="password"
                                class="form-control" 
                                 value="<?= htmlspecialchars($_POST['password'] ?? '') ?>"
                                required>

                        </div>

                    </div>

                </div>

            </div>

            <!-- RIGHT COLUMN -->

            <div class="col-lg-6">

                <div class="card shadow-sm border-0 mb-4">

                    <div class="card-header bg-success text-white">

                        <strong>

                            Academic Information

                        </strong>

                    </div>

                    <div class="card-body">

                        <div class="mb-3">

                            <label class="form-label">

                                Matric Number

                            </label>

                            <input
                                type="text"
                                name="matric_no"
                                class="form-control"
                                 value="<?= htmlspecialchars($_POST['matric_no'] ?? '') ?>"
                                required>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">

                                Department

                            </label>

                            <select
                                name="department_id"
                                class="form-select"
                                required>

                                <option value="">

                                    Select Department

                                </option>

                                <?php foreach($departments as $department): ?>

                                <option
                                    value="<?= $department['department_id'] ?>"
                                    <?= (($_POST['department_id'] ?? '') == $department['department_id']) ? 'selected' : '' ?>>

                                    <?= htmlspecialchars($department['department_name']) ?>

                                </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">

                                Programme Type

                            </label>

                            <select
                                name="programme_type_id"
                                class="form-select"
                                required>

                                <option value="">

                                    Select Programme

                                </option>

                                <?php foreach($programmeTypes as $programme): ?>

                                <option value="<?= $programme['programme_type_id'] ?>">

                                    <?= htmlspecialchars($programme['programme_name']) ?>

                                </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">

                                Programme Option

                            </label>

                            <select
                                name="option_id"
                                class="form-select">

                                <option value="">

                                    Select Option

                                </option>

                                <?php foreach($programmeOptions as $option): ?>

                                <option value="<?= $option['option_id'] ?>">

                                    <?= htmlspecialchars($option['option_name']) ?>

                                </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label class="form-label">

                                    Level

                                </label>

                                <select
                                    name="level_id"
                                    class="form-select"
                                    required>

                                    <option value="">

                                        Select Level

                                    </option>

                                    <?php foreach($levels as $level): ?>

                                    <option value="<?= $level['level_id'] ?>">

                                        <?= htmlspecialchars($level['level_name']) ?>

                                    </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label">

                                    Current Session

                                </label>

                                <select
                                    name="current_session_id"
                                    class="form-select"
                                    required>

                                   <option value="">Select Session</option>

                                    <?php foreach($sessions as $session): ?>

                                    <option
                                        value="<?= $session['session_id'] ?>"
                                        <?= $session['is_current'] ? 'selected' : '' ?>>

                                        <?= htmlspecialchars($session['session_name']) ?>

                                    </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label class="form-label">

                                    Admission Year

                                </label>

                                <input
                                    type="number"
                                    name="admission_year"
                                    min="2020"
                                    max="2100"
                                    class="form-control"
                                    value="<?= htmlspecialchars($_POST['admission_year'] ?? '') ?>"
                                    required>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label">

                                    Graduation Year

                                </label>

                                <input
                                    type="number"
                                    name="graduation_year"
                                    min="2020"
                                    max="2100"
                                    class="form-control"
                                    value="<?= htmlspecialchars($_POST['graduation_year'] ?? '') ?>">

                            </div>

                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label class="form-label">

                                    Current Semester

                                </label>

                                <select
                                    name="current_semester"
                                    class="form-select">

                                    <option value="First">

                                        First Semester

                                    </option>

                                    <option value="Second">

                                        Second Semester

                                    </option>

                                </select>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label">

                                    Academic Status

                                </label>

                                <select
                                    name="academic_status"
                                    class="form-select"
                                    required>


                                    <option value="Active" selected>

                                        Active

                                    </option>

                                    <option value="SIWES">

                                        SIWES

                                    </option>

                                    <option value="Deferred">

                                        Deferred

                                    </option>

                                    <option value="Graduated">

                                        Graduated

                                    </option>

                                </select>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="text-end mb-5">

            <button
                type="reset"
                class="btn btn-outline-secondary">

                <i class="bi bi-arrow-counterclockwise"></i>

                Reset

            </button>

            <button
                type="submit"
                class="btn btn-primary">

                <i class="bi bi-check-circle-fill"></i>

                Save Student

            </button>

        </div>

    </form>

</div>

<?php

require_once '../includes/layout_end.php';

?>
