<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!=="Administrator"){

    redirect(APP_URL);

}

$pageTitle="Student Profile";

if(empty($_GET['id'])){

    redirect("students.php");

}

$student=findStudent((int)$_GET['id']);

if(!$student){

    redirect("students.php");

}

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

<div class="d-flex justify-content-between mb-4">

<div>

<h2 class="fw-bold">

Student Profile

</h2>

<p class="text-muted">

Student Information

</p>

</div>

<div>

<a href="student_edit.php?id=<?= $student['student_id'] ?>"
class="btn btn-primary">

<i class="bi bi-pencil-square"></i>

Edit

</a>

<a href="students.php"
class="btn btn-secondary">

Back

</a>

</div>

</div>

<div class="card shadow-sm">

<div class="card-body">

<div class="row">

<div class="col-md-3 text-center">

<img
src="<?= APP_URL ?>/uploads/profile/<?= $student['profile_photo'] ?>"
class="img-fluid rounded-circle border"
style="width:170px;height:170px;object-fit:cover;">

</div>

<div class="col-md-9">

<table class="table table-bordered">

<tr>

<th width="30%">Full Name</th>

<td><?= htmlspecialchars($student['full_name']) ?></td>

</tr>

<tr>

<th>Username</th>

<td><?= htmlspecialchars($student['username']) ?></td>

</tr>

<tr>

<th>Email</th>

<td><?= htmlspecialchars($student['email']) ?></td>

</tr>

<tr>

<th>Matric Number</th>

<td><?= htmlspecialchars($student['matric_no']) ?></td>

</tr>

<tr>

<th>Department</th>

<td><?= htmlspecialchars($student['department_name']) ?></td>

</tr>

<tr>

<th>Programme</th>

<td><?= htmlspecialchars($student['programme_name']) ?></td>

</tr>

<tr>

<th>Option</th>

<td><?= htmlspecialchars($student['option_name']) ?></td>

</tr>

<tr>

<th>Level</th>

<td><?= htmlspecialchars($student['level_name']) ?></td>

</tr>

<tr>

<th>Academic Session</th>

<td><?= htmlspecialchars($student['session_name']) ?></td>

</tr>

<tr>

<th>Admission Year</th>

<td><?= $student['admission_year'] ?></td>

</tr>

<tr>

<th>Graduation Year</th>

<td><?= $student['graduation_year'] ?></td>

</tr>

<tr>

<th>Semester</th>

<td><?= $student['current_semester'] ?></td>

</tr>

<tr>

<th>Academic Status</th>

<td><?= $student['academic_status'] ?></td>

</tr>

<tr>

<th>Account Status</th>

<td><?= $student['account_status'] ?></td>

</tr>

</table>

</div>

</div>

</div>

</div>

</div>

<?php

require_once '../includes/layout_end.php';

?>