<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!=="Administrator"){
    redirect(APP_URL);
}

if(empty($_GET['id'])){
    redirect("lecturers.php");
}

$lecturerID=(int)$_GET['id'];

$lecturer=findLecturer($lecturerID);

if(!$lecturer){
    redirect("lecturers.php");
}

$pageTitle="Edit Lecturer";

$message="";

$departments=getDepartments();

if($_SERVER['REQUEST_METHOD']=="POST"){

    $fullName=sanitize($_POST['full_name']);
    $username=sanitize($_POST['username']);
    $email=sanitize($_POST['email']);

    $staffID=sanitize($_POST['staff_id']);

    $departmentID=(int)$_POST['department_id'];

    $designation=sanitize($_POST['designation']);

    $specialization=sanitize($_POST['specialization']);

    $employmentStatus=$_POST['employment_status'];

    $errors=[];

    if($fullName=="")
        $errors[]="Full Name is required.";

    if($username=="")
        $errors[]="Username is required.";

    /*
    |--------------------------------------------------------------------------
    | Duplicate Username
    |--------------------------------------------------------------------------
    */

    $stmt=db()->prepare("

        SELECT COUNT(*)

        FROM users

        WHERE username=?

        AND user_id<>?

    ");

    $stmt->execute([
        $username,
        $lecturer['user_id']
    ]);

    if($stmt->fetchColumn()){

        $errors[]="Username already exists.";

    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate Email
    |--------------------------------------------------------------------------
    */

    if($email!=""){

        $stmt=db()->prepare("

            SELECT COUNT(*)

            FROM users

            WHERE email=?

            AND user_id<>?

        ");

        $stmt->execute([
            $email,
            $lecturer['user_id']
        ]);

        if($stmt->fetchColumn()){

            $errors[]="Email already exists.";

        }

    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate Staff ID
    |--------------------------------------------------------------------------
    */

    $stmt=db()->prepare("

        SELECT COUNT(*)

        FROM lecturers

        WHERE staff_id=?

        AND lecturer_id<>?

    ");

    $stmt->execute([
        $staffID,
        $lecturerID
    ]);

    if($stmt->fetchColumn()){

        $errors[]="Staff ID already exists.";

    }

    if(empty($errors)){

        try{

            db()->beginTransaction();

            $stmt=db()->prepare("

                UPDATE users

                SET

                    full_name=?,

                    username=?,

                    email=?,

                    updated_by=?

                WHERE user_id=?

            ");

            $stmt->execute([

                $fullName,

                $username,

                $email,

                currentUserId(),

                $lecturer['user_id']

            ]);

            $stmt=db()->prepare("

                UPDATE lecturers

                SET

                    staff_id=?,

                    department_id=?,

                    designation=?,

                    specialization=?,

                    employment_status=?

                WHERE lecturer_id=?

            ");

            $stmt->execute([

                $staffID,

                $departmentID,

                $designation,

                $specialization,

                $employmentStatus,

                $lecturerID

            ]);

            db()->commit();

            redirect("lecturers.php");

        }

        catch(Throwable $e){

            db()->rollBack();

            $message=alert($e->getMessage(),"danger");

        }

    }

    else{

        $message=alert(

            implode("<br>",$errors),

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
Edit Lecturer
</h2>

<p class="text-muted">
Update Lecturer Information
</p>

</div>

<a href="lecturers.php" class="btn btn-secondary">
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

<label class="form-label">Full Name</label>

<input
type="text"
name="full_name"
class="form-control"
value="<?= htmlspecialchars($lecturer['full_name']) ?>"
required>

</div>

<div class="mb-3">

<label class="form-label">Username</label>

<input
type="text"
name="username"
class="form-control"
value="<?= htmlspecialchars($lecturer['username']) ?>"
required>

</div>

<div class="mb-3">

<label class="form-label">Email</label>

<input
type="email"
name="email"
class="form-control"
value="<?= htmlspecialchars($lecturer['email']) ?>">

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

<label class="form-label">Staff ID</label>

<input
type="text"
name="staff_id"
class="form-control"
value="<?= htmlspecialchars($lecturer['staff_id']) ?>"
required>

</div>

<div class="mb-3">

<label class="form-label">Department</label>

<select
name="department_id"
class="form-select"
required>

<?php foreach($departments as $department): ?>

<option
value="<?= $department['department_id'] ?>"
<?= $department['department_id']==$lecturer['department_id'] ? 'selected' : '' ?>>

<?= htmlspecialchars($department['department_name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="mb-3">

<label class="form-label">Designation</label>

<input
type="text"
name="designation"
class="form-control"
value="<?= htmlspecialchars($lecturer['designation']) ?>">

</div>

<div class="mb-3">

<label class="form-label">Specialization</label>

<input
type="text"
name="specialization"
class="form-control"
value="<?= htmlspecialchars($lecturer['specialization']) ?>">

</div>

<div class="mb-3">

<label class="form-label">Employment Status</label>

<select
name="employment_status"
class="form-select">

<?php

$statusList=[
"Active",
"Sabbatical",
"Retired",
"Resigned"
];

foreach($statusList as $status){

?>

<option
value="<?= $status ?>"
<?= $status==$lecturer['employment_status'] ? 'selected' : '' ?>>

<?= $status ?>

</option>

<?php } ?>

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

<i class="bi bi-check-circle-fill"></i>

Update Lecturer

</button>

</div>

</form>

</div>

<?php require_once '../includes/layout_end.php'; ?>