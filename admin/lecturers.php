<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!=="Administrator"){
    redirect(APP_URL);
}

$pageTitle="Lecturer Management";

$totalLecturers=db()->query("
SELECT COUNT(*)
FROM lecturers
")->fetchColumn();

$sql="

SELECT

lecturers.lecturer_id,

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

WHERE users.deleted_at IS NULL

ORDER BY users.full_name

";

$lecturers=db()->query($sql)->fetchAll();

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

<div class="page-toolbar">

<div>

<h2 class="fw-bold">

Lecturer Management

</h2>

<p class="text-muted">

Manage Lecturer Accounts

</p>

</div>

<a
href="lecturer_add.php"
class="btn btn-primary">

<i class="bi bi-person-plus-fill"></i>

Add Lecturer

</a>

</div>

<div class="row mb-4">

<div class="col-lg-3">

<div class="card dashboard-card">

<div class="card-body">

<h5>

<i class="bi bi-person-workspace text-primary me-2"></i>

Total Lecturers

</h5>

<h2 class="fw-bold">

<?= number_format($totalLecturers) ?>

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

<th>Name</th>

<th>Username</th>

<th>Email</th>

<th>Department</th>

<th>Status</th>

<th width="140">

Action

</th>

</tr>

</thead>

<tbody>

<?php foreach($lecturers as $lecturer): ?>

<tr>

<td>

<img
src="<?= APP_URL ?>/uploads/profile/<?= $lecturer['profile_photo'] ?>"
width="45"
class="rounded-circle">

</td>

<td>

<?= htmlspecialchars($lecturer['full_name']) ?>

</td>

<td>

<?= htmlspecialchars($lecturer['username']) ?>

</td>

<td>

<?= htmlspecialchars($lecturer['email']) ?>

</td>

<td>

<?= htmlspecialchars($lecturer['department_name']) ?>

</td>

<td>

<?= htmlspecialchars($lecturer['account_status']) ?>

</td>

<td>

<a
href="lecturer_view.php?id=<?= $lecturer['lecturer_id'] ?>"
class="btn btn-sm btn-info">

<i class="bi bi-eye-fill"></i>

</a>

<a
href="lecturer_edit.php?id=<?= $lecturer['lecturer_id'] ?>"
class="btn btn-sm btn-warning">

<i class="bi bi-pencil-fill"></i>

</a>

<a
href="lecturer_delete.php?id=<?= $lecturer['lecturer_id'] ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Delete this lecturer permanently?');">

<i class="bi bi-trash"></i>

</a>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

<?php

require_once '../includes/layout_end.php';

?>
