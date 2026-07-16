<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!=="Administrator"){

    redirect(APP_URL);

}

$pageTitle="Edit Student";

if(empty($_GET['id'])){

    redirect("students.php");

}

$studentID=(int)$_GET['id'];

$student=findStudent($studentID);

if(!$student){

    redirect("students.php");

}

$message="";

/*
|--------------------------------------------------------------------------
| Load Dropdowns
|--------------------------------------------------------------------------
*/

$departments=getDepartments();

$programmeTypes=getProgrammeTypes();

$programmeOptions=getProgrammeOptions();

$levels=getLevels();

$sessions=getAcademicSessions();

/*
|--------------------------------------------------------------------------
| Update Student
|--------------------------------------------------------------------------
*/

if($_SERVER['REQUEST_METHOD']=="POST"){

    $fullName=sanitize($_POST['full_name']);
    $username=sanitize($_POST['username']);
    $email=sanitize($_POST['email']);

    $matricNo=sanitize($_POST['matric_no']);

    $departmentID=(int)$_POST['department_id'];
    $programmeTypeID=(int)$_POST['programme_type_id'];
    $optionID=!empty($_POST['option_id'])?(int)$_POST['option_id']:NULL;
    $levelID=(int)$_POST['level_id'];

    $sessionID=(int)$_POST['current_session_id'];

    $admissionYear=$_POST['admission_year'];

    $graduationYear=!empty($_POST['graduation_year'])
        ?$_POST['graduation_year']
        :NULL;

    $semester=$_POST['current_semester'];

    $academicStatus=$_POST['academic_status'];

    $errors=[];

    if($fullName=="")
        $errors[]="Full Name is required.";

    if($username=="")
        $errors[]="Username is required.";

    if($matricNo=="")
        $errors[]="Matric Number is required.";

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

        $student['user_id']

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

            $student['user_id']

        ]);

        if($stmt->fetchColumn()){

            $errors[]="Email already exists.";

        }

    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate Matric
    |--------------------------------------------------------------------------
    */

    $stmt=db()->prepare("

        SELECT COUNT(*)

        FROM students

        WHERE matric_no=?

        AND student_id<>?

    ");

    $stmt->execute([

        $matricNo,

        $studentID

    ]);

    if($stmt->fetchColumn()){

        $errors[]="Matric Number already exists.";

    }

    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    if(empty($errors)){

        try{

            db()->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Update User
            |--------------------------------------------------------------------------
            */

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

                $student['user_id']

            ]);

            /*
            |--------------------------------------------------------------------------
            | Update Student
            |--------------------------------------------------------------------------
            */

            $stmt=db()->prepare("

                UPDATE students

                SET

                    matric_no=?,

                    department_id=?,

                    programme_type_id=?,

                    option_id=?,

                    level_id=?,

                    current_session_id=?,

                    admission_year=?,

                    graduation_year=?,

                    current_semester=?,

                    academic_status=?

                WHERE student_id=?

            ");

            $stmt->execute([

                $matricNo,

                $departmentID,

                $programmeTypeID,

                $optionID,

                $levelID,

                $sessionID,

                $admissionYear,

                $graduationYear,

                $semester,

                $academicStatus,

                $studentID

            ]);

            db()->commit();

            redirect(APP_URL."/admin/student_view.php?id=".$studentID."&updated=1");

        }

        catch(Throwable $e){

            db()->rollBack();

            $message=alert(

                "Unable to update student.",

                "danger"

            );

        }

    }

    else{

        $message=alert(

            implode("<br>",$errors),

            "danger"

        );

    }

    /*
    |--------------------------------------------------------------------------
    | Reload Updated Record
    |--------------------------------------------------------------------------
    */

    $student=findStudent($studentID);

}

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="fw-bold">

            Edit Student

        </h2>

        <p class="text-muted">

            Update student information

        </p>

    </div>

    <div>

        <a href="student_view.php?id=<?= $studentID ?>"
           class="btn btn-outline-secondary">

            <i class="bi bi-arrow-left"></i>

            Back

        </a>

    </div>

</div>

<?= $message ?>

<form method="POST">

<div class="row">

<!-- ==========================================================
PERSONAL INFORMATION
========================================================== -->

<div class="col-lg-6">

<div class="card dashboard-card mb-4">

<div class="card-header bg-primary text-white">

Personal Information

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
value="<?= htmlspecialchars($student['full_name']) ?>"
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
value="<?= htmlspecialchars($student['username']) ?>"
required>

</div>

<div class="mb-3">

<label class="form-label">

Email

</label>

<input
type="email"
name="email"
class="form-control"
value="<?= htmlspecialchars($student['email']) ?>">

</div>

</div>

</div>

</div>

<!-- ==========================================================
ACADEMIC INFORMATION
========================================================== -->

<div class="col-lg-6">

<div class="card dashboard-card mb-4">

<div class="card-header bg-success text-white">

Academic Information

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
value="<?= htmlspecialchars($student['matric_no']) ?>"
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

<?php foreach($departments as $department): ?>

<option
value="<?= $department['department_id'] ?>"
<?= ($student['department_id']==$department['department_id'])?'selected':'' ?>>

<?= htmlspecialchars($department['department_name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="mb-3">

<label class="form-label">

Programme

</label>

<select
name="programme_type_id"
class="form-select"
required>

<?php foreach($programmeTypes as $programme): ?>

<option
value="<?= $programme['programme_type_id'] ?>"
<?= ($student['programme_type_id']==$programme['programme_type_id'])?'selected':'' ?>>

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

None

</option>

<?php foreach($programmeOptions as $option): ?>

<option
value="<?= $option['option_id'] ?>"
<?= ($student['option_id']==$option['option_id'])?'selected':'' ?>>

<?= htmlspecialchars($option['option_name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="row">

<div class="col-md-6">

<div class="mb-3">

<label class="form-label">

Level

</label>

<select
name="level_id"
class="form-select"
required>

<?php foreach($levels as $level): ?>

<option
value="<?= $level['level_id'] ?>"
<?= ($student['level_id']==$level['level_id'])?'selected':'' ?>>

<?= htmlspecialchars($level['level_name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

</div>

<div class="col-md-6">

<div class="mb-3">

<label class="form-label">

Academic Session

</label>

<select
name="current_session_id"
class="form-select"
required>

<?php foreach($sessions as $session): ?>

<option
value="<?= $session['session_id'] ?>"
<?= ($student['current_session_id']==$session['session_id'])?'selected':'' ?>>

<?= htmlspecialchars($session['session_name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

</div>

</div>

<div class="row">

<div class="col-md-6">

<div class="mb-3">

<label class="form-label">

Admission Year

</label>

<input
type="number"
name="admission_year"
class="form-control"
value="<?= $student['admission_year'] ?>">

</div>

</div>

<div class="col-md-6">

<div class="mb-3">

<label class="form-label">

Graduation Year

</label>

<input
type="number"
name="graduation_year"
class="form-control"
value="<?= $student['graduation_year'] ?>">

</div>

</div>

</div>

<div class="row">

<div class="col-md-6">

<div class="mb-3">

<label class="form-label">

Semester

</label>

<select
name="current_semester"
class="form-select">

<option
value="First"
<?= ($student['current_semester']=="First")?'selected':'' ?>>

First Semester

</option>

<option
value="Second"
<?= ($student['current_semester']=="Second")?'selected':'' ?>>

Second Semester

</option>

</select>

</div>

</div>

<div class="col-md-6">

<div class="mb-3">

<label class="form-label">

Academic Status

</label>

<select
name="academic_status"
class="form-select">

<?php

$statusList=[
'Active',
'SIWES',
'Deferred',
'Graduated',
'Suspended'
];

foreach($statusList as $status):

?>

<option
value="<?= $status ?>"
<?= ($student['academic_status']==$status)?'selected':'' ?>>

<?= $status ?>

</option>

<?php endforeach; ?>

</select>

</div>

</div>

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

Update Student

</button>

</div>

</form>

</div>

<?php

require_once '../includes/layout_end.php';

?>