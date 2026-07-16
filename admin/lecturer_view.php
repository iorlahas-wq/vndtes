<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!=="Administrator"){
    redirect(APP_URL);
}

if(empty($_GET['id'])){
    redirect("lecturers.php");
}

$lecturer=findLecturer((int)$_GET['id']);

if(!$lecturer){
    redirect("lecturers.php");
}

$pageTitle="Lecturer Profile";

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

<div class="d-flex justify-content-between mb-4">

<div>

<h2 class="fw-bold">

Lecturer Profile

</h2>

</div>

<div>

<a
href="lecturer_edit.php?id=<?= $lecturer['lecturer_id'] ?>"
class="btn btn-primary">

Edit

</a>

<a
href="lecturers.php"
class="btn btn-secondary">

Back

</a>

</div>

</div>

<div class="card table-card">

<div class="card-body">

<table class="table table-bordered">

<tr>

<th width="30%">Full Name</th>

<td><?= htmlspecialchars($lecturer['full_name']) ?></td>

</tr>

<tr>

<th>Username</th>

<td><?= htmlspecialchars($lecturer['username']) ?></td>

</tr>

<tr>

<th>Email</th>

<td><?= htmlspecialchars($lecturer['email']) ?></td>

</tr>

<tr>

<th>Staff ID</th>

<td><?= htmlspecialchars($lecturer['staff_id']) ?></td>

</tr>

<tr>

<th>Department</th>

<td><?= htmlspecialchars($lecturer['department_name']) ?></td>

</tr>

<tr>

<th>Designation</th>

<td><?= htmlspecialchars($lecturer['designation']) ?></td>

</tr>

<tr>

<th>Specialization</th>

<td><?= htmlspecialchars($lecturer['specialization']) ?></td>

</tr>

<tr>

<th>Employment Status</th>

<td><?= htmlspecialchars($lecturer['employment_status']) ?></td>

</tr>

<tr>

<th>Account Status</th>

<td><?= htmlspecialchars($lecturer['account_status']) ?></td>

</tr>

</table>

</div>

</div>

</div>

<?php require_once '../includes/layout_end.php'; ?>