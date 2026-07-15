<?php

require_once '../includes/init.php';

requireRole('Administrator');

$pageTitle = "User Management";

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalUsers = db()->query("SELECT COUNT(*) FROM users")->fetchColumn();

$totalAdmins = db()->query("
SELECT COUNT(*)
FROM users
WHERE role_id=1
")->fetchColumn();

$totalLecturers = db()->query("
SELECT COUNT(*)
FROM users
WHERE role_id=2
")->fetchColumn();

$totalStudents = db()->query("
SELECT COUNT(*)
FROM users
WHERE role_id=3
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| User List
|--------------------------------------------------------------------------
*/

$sql="

SELECT

u.*,

r.role_name

FROM users u

INNER JOIN roles r

ON u.role_id=r.role_id

WHERE deleted_at IS NULL

ORDER BY full_name

";

$users=db()->query($sql)->fetchAll();

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>User Management</h2>

<p class="text-muted">

Manage all system accounts

</p>

</div>

<button
class="btn btn-primary">

<i class="bi bi-person-plus-fill"></i>

Add User

</button>

</div>

<div class="row">

<div class="col-lg-3 mb-4">

<div class="card shadow-sm">

<div class="card-body">

<h6>Total Users</h6>

<h2><?= $totalUsers ?></h2>

</div>

</div>

</div>

<div class="col-lg-3 mb-4">

<div class="card shadow-sm">

<div class="card-body">

<h6>Administrators</h6>

<h2><?= $totalAdmins ?></h2>

</div>

</div>

</div>

<div class="col-lg-3 mb-4">

<div class="card shadow-sm">

<div class="card-body">

<h6>Lecturers</h6>

<h2><?= $totalLecturers ?></h2>

</div>

</div>

</div>

<div class="col-lg-3 mb-4">

<div class="card shadow-sm">

<div class="card-body">

<h6>Students</h6>

<h2><?= $totalStudents ?></h2>

</div>

</div>

</div>

</div>

<div class="card shadow-sm">

<div class="card-body">

<div class="table-responsive">

<table class="table table-hover align-middle">

<thead>

<tr>

<th>Photo</th>

<th>Name</th>

<th>Username</th>

<th>Email</th>

<th>Role</th>

<th>Status</th>

<th width="160">

Action

</th>

</tr>

</thead>

<tbody>

<?php foreach($users as $user): ?>

<tr>

<td>

<img
src="<?= APP_URL ?>/uploads/profile/<?= htmlspecialchars($user['profile_photo']) ?>"
width="40"
height="40"
class="rounded-circle">

</td>

<td>

<?= htmlspecialchars($user['full_name']) ?>

</td>

<td>

<?= htmlspecialchars($user['username']) ?>

</td>

<td>

<?= htmlspecialchars($user['email']) ?>

</td>

<td>

<span class="badge bg-primary">

<?= $user['role_name'] ?>

</span>

</td>

<td>

<?php if($user['account_status']=="Active"): ?>

<span class="badge bg-success">

Active

</span>

<?php else: ?>

<span class="badge bg-danger">

<?= $user['account_status'] ?>

</span>

<?php endif; ?>

</td>

<td>

<button class="btn btn-sm btn-warning">

<i class="bi bi-pencil"></i>

</button>

<button class="btn btn-sm btn-danger">

<i class="bi bi-trash"></i>

</button>

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