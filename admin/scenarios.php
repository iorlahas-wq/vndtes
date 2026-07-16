<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

$pageTitle="Scenario Management";

$totalScenarios=db()->query("
SELECT COUNT(*)
FROM scenarios
")->fetchColumn();

$sql="

SELECT

scenarios.*,

users.full_name

FROM scenarios

INNER JOIN users
ON users.user_id=scenarios.created_by

ORDER BY scenario_title

";

$scenarios=db()->query($sql)->fetchAll();

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2 class="fw-bold">

Scenario Management

</h2>

<p class="text-muted">

Manage Network Training Scenarios

</p>

</div>

<a
href="scenario_add.php"
class="btn btn-primary">

<i class="bi bi-plus-circle"></i>

Create Scenario

</a>

</div>

<div class="row mb-4">

<div class="col-lg-3">

<div class="card dashboard-card">

<div class="card-body">

<h5>Total Scenarios</h5>

<h2>

<?= number_format($totalScenarios) ?>

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

<th>Code</th>

<th>Title</th>

<th>Category</th>

<th>Difficulty</th>

<th>Time</th>

<th>Status</th>

<th>Author</th>

<th width="150">

Action

</th>

</tr>

</thead>

<tbody>

<?php foreach($scenarios as $scenario): ?>

<tr>

<td>

<?= htmlspecialchars($scenario['scenario_code']) ?>

</td>

<td>

<?= htmlspecialchars($scenario['scenario_title']) ?>

</td>

<td>

<?= htmlspecialchars($scenario['category']) ?>

</td>

<td>

<?php

switch($scenario['difficulty']){

    case "Beginner":

        echo '<span class="badge bg-primary">Beginner</span>';

    break;

    case "Intermediate":

        echo '<span class="badge bg-warning text-dark">Intermediate</span>';

    break;

    case "Advanced":

        echo '<span class="badge bg-danger">Advanced</span>';

    break;

}

?>

</td>

<td>

<?= $scenario['estimated_time'] ?> mins

</td>

<td>

<?php

switch($scenario['status']){

    case "Published":

        echo '<span class="badge bg-success">Published</span>';

    break;

    case "Draft":

        echo '<span class="badge bg-warning text-dark">Draft</span>';

    break;

    case "Archived":

        echo '<span class="badge bg-secondary">Archived</span>';

    break;

}

?>

</td>

<td>

<?= htmlspecialchars($scenario['full_name']) ?>

</td>

<td>

<a
href="scenario_view.php?id=<?= $scenario['scenario_id'] ?>"
class="btn btn-info btn-sm">

<i class="bi bi-eye-fill"></i>

</a>

<a
href="scenario_edit.php?id=<?= $scenario['scenario_id'] ?>"
class="btn btn-warning btn-sm">

<i class="bi bi-pencil-fill"></i>

</a>

<a
href="scenario_delete.php?id=<?= $scenario['scenario_id'] ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Delete this scenario?')">

<i class="bi bi-trash-fill"></i>

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

<?php require_once '../includes/layout_end.php'; ?>