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

$pageTitle = 'Student Management';
$message = "";

if(isset($_GET['success'])){

    $message = alert(

        "Student registered successfully.",

        "success"

    );

}

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalStudents = db()->query("
    SELECT COUNT(*)
    FROM students
")->fetchColumn();

$activeStudents = db()->query("
    SELECT COUNT(*)
    FROM students
    WHERE academic_status='Active'
")->fetchColumn();

$siwesStudents = db()->query("
    SELECT COUNT(*)
    FROM students
    WHERE academic_status='SIWES'
")->fetchColumn();

$graduatedStudents = db()->query("
    SELECT COUNT(*)
    FROM students
    WHERE academic_status='Graduated'
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| Student List
|--------------------------------------------------------------------------
*/

$sql = "

SELECT

students.student_id,
students.matric_no,
students.current_session_id,
students.academic_status,

users.full_name,
users.profile_photo,

programme_types.programme_name,

programme_options.option_name,

levels.level_name

FROM students

INNER JOIN users
ON students.user_id = users.user_id

INNER JOIN programme_types
ON students.programme_type_id = programme_types.programme_type_id

LEFT JOIN programme_options
ON students.option_id = programme_options.option_id

INNER JOIN levels
ON students.level_id = levels.level_id

ORDER BY users.full_name ASC

";

$students = db()->query($sql)->fetchAll();

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

    <div class="page-toolbar">

        <div>

            <h2 class="fw-bold">

                Student Management

            </h2>

            <p class="text-muted">

                Manage registered students

            </p>

        </div>

        <a href="student_add.php"
           class="btn btn-primary">

            <i class="bi bi-person-plus-fill"></i>

            Add Student

        </a>

    </div>

    <div class="row g-4 mb-4">

        <div class="col-lg-3">

            <div class="card dashboard-card">

                <div class="card-body">

                    <h5>

                        <i class="bi bi-people-fill text-primary me-2"></i>

                        Total Students

                    </h5>

                    <h2 class="fw-bold">

                        <?= number_format($totalStudents) ?>

                    </h2>

                </div>

            </div>

        </div>

        <div class="col-lg-3">

            <div class="card dashboard-card">

                <div class="card-body">

                    <h5>

                        <i class="bi bi-person-check-fill text-success me-2"></i>

                        Active

                    </h5>

                    <h2 class="fw-bold">

                        <?= number_format($activeStudents) ?>

                    </h2>

                </div>

            </div>

        </div>

        <div class="col-lg-3">

            <div class="card dashboard-card">

                <div class="card-body">

                    <h5>

                        <i class="bi bi-briefcase-fill text-warning me-2"></i>

                        SIWES

                    </h5>

                    <h2 class="fw-bold">

                        <?= number_format($siwesStudents) ?>

                    </h2>

                </div>

            </div>

        </div>

        <div class="col-lg-3">

            <div class="card dashboard-card">

                <div class="card-body">

                    <h5>

                        <i class="bi bi-award-fill text-danger me-2"></i>

                        Graduated

                    </h5>

                    <h2 class="fw-bold">

                        <?= number_format($graduatedStudents) ?>

                    </h2>

                </div>

            </div>

        </div>

    </div>

    <div class="card table-card">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                    <tr>

                        <th>Photo</th>

                        <th>Matric No</th>

                        <th>Name</th>

                        <th>Programme</th>

                        <th>Level</th>

                        <th>Status</th>

                        <th width="140">Action</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php if(count($students)): ?>

                        <?php foreach($students as $student): ?>

                        <tr>

                            <td>

                                <img
                                src="<?= APP_URL ?>/uploads/profile/<?= $student['profile_photo'] ?>"
                                class="rounded-circle"
                                width="45">

                            </td>

                            <td>

                                <?= htmlspecialchars($student['matric_no']) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars($student['full_name']) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars($student['programme_name']) ?>

                                <?php

                                if($student['option_name']){

                                    echo " ({$student['option_name']})";

                                }

                                ?>

                            </td>

                            <td>

                                <?= htmlspecialchars($student['level_name']) ?>

                            </td>

                            <td>

                                <span class="badge bg-success">

                                    <?= $student['academic_status'] ?>

                                </span>

                            </td>

                            <td>

                                <a href="student_view.php?id=<?= $student['student_id'] ?>"
                                   class="btn btn-sm btn-info">

                                    <i class="bi bi-eye-fill"></i>

                                </a>

                                <a href="student_edit.php?id=<?= $student['student_id'] ?>"
                                   class="btn btn-sm btn-warning">

                                    <i class="bi bi-pencil-fill"></i>

                                </a>

                            </td>

                        </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="7"
                                class="text-center text-muted">

                                No students found.

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<?php require_once '../includes/layout_end.php'; ?>