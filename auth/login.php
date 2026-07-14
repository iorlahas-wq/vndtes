<?php

/**
 * ==========================================================
 * VNDTES
 * Authentication Module
 * Login Page (Self Processing)
 * ==========================================================
 */

require_once '../includes/init.php';

/*
|--------------------------------------------------------------------------
| Redirect Logged-in Users
|--------------------------------------------------------------------------
*/

if (isLoggedIn()) {

    switch ($_SESSION['role_name']) {

        case 'Administrator':
            redirect(APP_URL . '/admin/dashboard.php');
            break;

        case 'Lecturer':
            redirect(APP_URL . '/lecturer/dashboard.php');
            break;

        case 'Student':
            redirect(APP_URL . '/student/dashboard.php');
            break;
    }
}

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$username = '';
$password = '';
$errors = [];

/*
|--------------------------------------------------------------------------
| Process Login
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    --------------------------------------
    Collect Form Data
    --------------------------------------
    */

    $username = sanitize($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    /*
    --------------------------------------
    Validation
    --------------------------------------
    */

    if ($username === '') {
        $errors[] = "Username is required.";
    }

    if ($password === '') {
        $errors[] = "Password is required.";
    }

    /*
    --------------------------------------
    Authenticate User
    --------------------------------------
    */

    if (empty($errors)) {

        try {

            $sql = "
                SELECT
                    u.user_id,
                    u.role_id,
                    u.full_name,
                    u.username,
                    u.email,
                    u.password_hash,
                    u.profile_photo,
                    u.account_status,
                    r.role_name

                FROM users u

                INNER JOIN roles r
                    ON u.role_id = r.role_id

                WHERE u.username = :username

                LIMIT 1
            ";

            $stmt = db()->prepare($sql);

            $stmt->execute([
                ':username' => $username
            ]);

            $user = $stmt->fetch();

            /*
            --------------------------------------
            User Found?
            --------------------------------------
            */

            if (!$user) {

                $errors[] = "Invalid username or password.";

            } else {

                /*
                --------------------------------------
                Account Status
                --------------------------------------
                */

                if ($user['account_status'] !== 'Active') {

                    $errors[] = "Your account has been disabled.";

                } else {

                    /*
                    --------------------------------------
                    Verify Password
                    --------------------------------------
                    */

                    if (!password_verify($password, $user['password_hash'])) {

                        $errors[] = "Invalid username or password.";

                    } else {

                        /*
                        --------------------------------------
                        Login Successful
                        --------------------------------------
                        */

                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $user['user_id'];

                        $_SESSION['role_id'] = $user['role_id'];

                        $_SESSION['role_name'] = $user['role_name'];

                        $_SESSION['username'] = $user['username'];

                        $_SESSION['full_name'] = $user['full_name'];

                        $_SESSION['profile_photo'] = $user['profile_photo'];

                        $_SESSION['last_login'] = date('Y-m-d H:i:s');

                        /*
                        --------------------------------------
                        Update Database
                        --------------------------------------
                        */

                        $update = db()->prepare("
                            UPDATE users
                            SET last_login = NOW()
                            WHERE user_id = ?
                        ");

                        $update->execute([
                            $user['user_id']
                        ]);

                        /*
                        --------------------------------------
                        Load Student Session
                        --------------------------------------
                        */

                        if ($user['role_name'] === 'Student') {

                            $student = db()->prepare("
                                SELECT *
                                FROM students
                                WHERE user_id = ?
                                LIMIT 1
                            ");

                            $student->execute([
                                $user['user_id']
                            ]);

                            if ($row = $student->fetch()) {

                                $_SESSION['student_id'] = $row['student_id'];

                                $_SESSION['matric_no'] = $row['matric_no'];

                                $_SESSION['department_id'] = $row['department_id'];

                                $_SESSION['programme_type_id'] = $row['programme_type_id'];

                                $_SESSION['option_id'] = $row['option_id'];

                                $_SESSION['level_id'] = $row['level_id'];

                                $_SESSION['current_session_id'] = $row['current_session_id'];

                                $_SESSION['current_semester'] = $row['current_semester'];
                            }
                        }

                        /*
                        --------------------------------------
                        Load Lecturer Session
                        --------------------------------------
                        */

                        if ($user['role_name'] === 'Lecturer') {

                            $lecturer = db()->prepare("
                                SELECT *
                                FROM lecturers
                                WHERE user_id = ?
                                LIMIT 1
                            ");

                            $lecturer->execute([
                                $user['user_id']
                            ]);

                            if ($row = $lecturer->fetch()) {

                                $_SESSION['lecturer_id'] = $row['lecturer_id'];

                                $_SESSION['staff_id'] = $row['staff_id'];

                                $_SESSION['department_id'] = $row['department_id'];
                            }
                        }

                        /*
                        --------------------------------------
                        Redirect User
                        --------------------------------------
                        */

                        switch ($user['role_name']) {

                            case 'Administrator':

                                redirect(APP_URL . '/admin/dashboard.php');

                                break;

                            case 'Lecturer':

                                redirect(APP_URL . '/lecturer/dashboard.php');

                                break;

                            case 'Student':

                                redirect(APP_URL . '/student/dashboard.php');

                                break;

                            default:

                                session_destroy();

                                $errors[] = "Unknown user role.";
                        }
                    }
                }
            }

        } catch (PDOException $e) {

            $errors[] = "Login failed. Please contact the system administrator.";

            // Uncomment during development only
            // $errors[] = $e->getMessage();

        }

    }

}

/*
|--------------------------------------------------------------------------
| Page Settings
|--------------------------------------------------------------------------
*/

$pageTitle = "Login";

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

    <div class="row min-vh-100 align-items-center">

        <!-- LEFT PANEL -->

        <div class="col-lg-6 d-none d-lg-flex">

            <div class="login-left w-100 p-5">

                <img
                    src="<?= APP_URL ?>/assets/images/hpp-logo.png"
                    class="mb-4"
                    style="max-height:90px;">

                <span class="badge bg-primary mb-3">

                    Harry Pass Polytechnic

                </span>

                <h1 class="display-4 fw-bold mb-4">

                    Welcome to

                    <br>

                    <?= APP_NAME ?>

                </h1>

                <p class="lead">

                    Virtual Network Diagnostic & Training Environment
                    designed to help students master networking
                    through intelligent practical simulations,
                    diagnostics and performance analytics.

                </p>

                <hr class="my-5">

                <div class="row">

                    <div class="col-md-6 mb-4">

                        <h5>

                            <i class="bi bi-router-fill text-primary"></i>

                            Virtual Lab

                        </h5>

                        <p>

                            Practice network troubleshooting
                            anytime and anywhere.

                        </p>

                    </div>

                    <div class="col-md-6 mb-4">

                        <h5>

                            <i class="bi bi-lightbulb-fill text-warning"></i>

                            Smart Diagnosis

                        </h5>

                        <p>

                            Receive intelligent hints while
                            solving networking faults.

                        </p>

                    </div>

                    <div class="col-md-6">

                        <h5>

                            <i class="bi bi-bar-chart-fill text-success"></i>

                            Analytics

                        </h5>

                        <p>

                            Track your practical
                            performance over time.

                        </p>

                    </div>

                    <div class="col-md-6">

                        <h5>

                            <i class="bi bi-shield-lock-fill text-danger"></i>

                            Secure Access

                        </h5>

                        <p>

                            Role-based authentication
                            protects every account.

                        </p>

                    </div>

                </div>

            </div>

        </div>

        <!-- RIGHT PANEL -->

        <div class="col-lg-6">

            <div class="login-box">

                <div class="card border-0 shadow-lg">

                    <div class="card-body p-5">

                        <div class="text-center mb-4">

                            <img
                                src="<?= APP_URL ?>/assets/images/hpp-logo.png"
                                style="height:70px;">

                            <h2 class="mt-3">

                                Sign In

                            </h2>

                            <p class="text-muted">

                                Login to continue

                            </p>

                        </div>

                        <?php if(!empty($errors)): ?>

                            <div class="alert alert-danger">

                                <ul class="mb-0">

                                    <?php foreach($errors as $error): ?>

                                        <li>

                                            <?= htmlspecialchars($error) ?>

                                        </li>

                                    <?php endforeach; ?>

                                </ul>

                            </div>

                        <?php endif; ?>

                        <form method="post"
                              autocomplete="off">

                            <div class="mb-4">

                                <label class="form-label">

                                    Username

                                </label>

                                <input
                                    type="text"
                                    name="username"
                                    class="form-control form-control-lg"
                                    value="<?= htmlspecialchars($username) ?>"
                                    autofocus>

                            </div>

                            <div class="mb-4">

                                <label class="form-label">

                                    Password

                                </label>

                                <div class="input-group">

                                    <input
                                        type="password"
                                        name="password"
                                        id="password"
                                        class="form-control form-control-lg">

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary"
                                        id="togglePassword">

                                        <i class="bi bi-eye"></i>

                                    </button>

                                </div>

                            </div>

                            <div class="mb-4">

                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="remember">

                                    <label
                                        class="form-check-label"
                                        for="remember">

                                        Remember Me

                                    </label>

                                </div>

                            </div>

                            <button
                            type="submit"
                            class="btn btn-primary btn-lg w-100"
                            id="loginButton">

                            <span id="loginText">

                                <i class="bi bi-box-arrow-in-right"></i>

                                Login

                            </span>

                            <span
                                id="loginLoading"
                                class="d-none">

                                <span
                                    class="spinner-border spinner-border-sm me-2">
                                </span>

                                Authenticating...

                            </span>

                        </button>

                        </form>

                        <hr class="my-4">

                        <div class="text-center">

                            <a
                                href="<?= APP_URL ?>"
                                class="text-decoration-none">

                                ← Back to Home

                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php

require_once '../includes/layout_end.php';

?>