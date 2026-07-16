<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if (currentUserRole() !== "Administrator") {
    redirect(APP_URL);
}

$pageTitle = "Add Lecturer";

$message = "";

$departments = getDepartments();

if ($_SERVER['REQUEST_METHOD'] === "POST") {

    /*---------------------------------------------------------
    Collect Form Data
    ---------------------------------------------------------*/

    $fullName          = sanitize($_POST['full_name']);
    $username          = sanitize($_POST['username']);
    $email             = sanitize($_POST['email']);

    $staffID           = sanitize($_POST['staff_id']);
    $departmentID      = (int)$_POST['department_id'];
    $designation       = sanitize($_POST['designation']);
    $specialization    = sanitize($_POST['specialization']);
    $employmentStatus  = $_POST['employment_status'];

    $passwordHash = password_hash("password", PASSWORD_DEFAULT);

    $errors = [];

    /*---------------------------------------------------------
    Validation
    ---------------------------------------------------------*/

    if ($fullName == "")
        $errors[] = "Full Name is required.";

    if ($username == "")
        $errors[] = "Username is required.";

    if ($staffID == "")
        $errors[] = "Staff ID is required.";

    /* Username */

    $stmt = db()->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE username=?
    ");

    $stmt->execute([$username]);

    if ($stmt->fetchColumn() > 0)
        $errors[] = "Username already exists.";

    /* Email */

    if ($email != "") {

        $stmt = db()->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE email=?
        ");

        $stmt->execute([$email]);

        if ($stmt->fetchColumn() > 0)
            $errors[] = "Email already exists.";

    }

    /* Staff ID */

    $stmt = db()->prepare("
        SELECT COUNT(*)
        FROM lecturers
        WHERE staff_id=?
    ");

    $stmt->execute([$staffID]);

    if ($stmt->fetchColumn() > 0)
        $errors[] = "Staff ID already exists.";

    /*---------------------------------------------------------
    Save
    ---------------------------------------------------------*/

    if (empty($errors)) {

        try {

            db()->beginTransaction();

            /* Users */

            $stmt = db()->prepare("
                INSERT INTO users
                (
                    role_id,
                    full_name,
                    username,
                    email,
                    password_hash,
                    created_by
                )
                VALUES
                (
                    ?,?,?,?,?,?
                )
            ");

            $stmt->execute([

                2,
                $fullName,
                $username,
                $email,
                $passwordHash,
                currentUserId()

            ]);

            $userID = db()->lastInsertId();

            /* Lecturers */

            $stmt = db()->prepare("
                INSERT INTO lecturers
                (
                    user_id,
                    staff_id,
                    department_id,
                    designation,
                    specialization,
                    employment_status
                )
                VALUES
                (
                    ?,?,?,?,?,?
                )
            ");

            $stmt->execute([

                $userID,
                $staffID,
                $departmentID,
                $designation,
                $specialization,
                $employmentStatus

            ]);

           db()->commit();

$message = alert(
    "Lecturer created successfully.",
    "success"
);

// redirect("lecturers.php");

        }

        catch (Throwable $e) {

            db()->rollBack();

            $message = alert($e->getMessage(), "danger");

        }

    } else {

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

            <h2 class="fw-bold">

                Add Lecturer

            </h2>

            <p class="text-muted">

                Register New Lecturer

            </p>

        </div>

        <a href="lecturers.php"
           class="btn btn-secondary">

            <i class="bi bi-arrow-left"></i>

            Back

        </a>

    </div>

    <?= $message ?>

    <form method="POST">

        <div class="row">

            <div class="col-lg-6">

                <div class="card dashboard-card mb-4">

                    <div class="card-header bg-primary text-white">

                        Account Information

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
                                required>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">

                                Email

                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control">

                        </div>

                        <div class="alert alert-info">

                            Default Password:
                            <strong>password</strong>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col-lg-6">

                <div class="card dashboard-card mb-4">

                    <div class="card-header bg-success text-white">

                        Employment Information

                    </div>

                    <div class="card-body">

                        <div class="mb-3">

                            <label class="form-label">

                                Staff ID

                            </label>

                            <input
                                type="text"
                                name="staff_id"
                                class="form-control"
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

                                <?php foreach ($departments as $department): ?>

                                    <option value="<?= $department['department_id'] ?>">

                                        <?= htmlspecialchars($department['department_name']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">

                                Designation

                            </label>

                            <input
                                type="text"
                                name="designation"
                                class="form-control">

                        </div>

                        <div class="mb-3">

                            <label class="form-label">

                                Specialization

                            </label>

                            <input
                                type="text"
                                name="specialization"
                                class="form-control">

                        </div>

                        <div class="mb-3">

                            <label class="form-label">

                                Employment Status

                            </label>

                            <select
                                name="employment_status"
                                class="form-select">

                                <option value="Active">Active</option>
                                <option value="Sabbatical">Sabbatical</option>
                                <option value="Retired">Retired</option>
                                <option value="Resigned">Resigned</option>

                            </select>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="text-end">

            <button
                type="submit"
                class="btn btn-primary">

                <i class="bi bi-save-fill"></i>

                Save Lecturer

            </button>

        </div>

    </form>

</div>

<?php require_once '../includes/layout_end.php'; ?>