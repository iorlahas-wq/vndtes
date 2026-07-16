<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

if(empty($_GET['id'])){
    redirect("scenarios.php");
}

$scenarioID=(int)$_GET['id'];

$scenario=findScenario($scenarioID);

if(!$scenario){
    redirect("scenarios.php");
}

$pageTitle="Scenario Details";

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2 class="fw-bold">

Scenario Details

</h2>

<p class="text-muted">

Network Training Scenario Information

</p>

</div>

<div>

<a
href="scenario_edit.php?id=<?= $scenario['scenario_id'] ?>"
class="btn btn-primary">

<i class="bi bi-pencil-fill"></i>

Edit

</a>

<a
href="scenarios.php"
class="btn btn-secondary">

Back

</a>

</div>

</div>

<div class="row">

<div class="col-lg-8">

<div class="card dashboard-card mb-4">

<div class="card-header bg-primary text-white">

Scenario Information

</div>

<div class="card-body">

<table class="table table-bordered">

<tr>

<th width="30%">Scenario Code</th>

<td><?= htmlspecialchars($scenario['scenario_code']) ?></td>

</tr>

<tr>

<th>Scenario Title</th>

<td><?= htmlspecialchars($scenario['scenario_title']) ?></td>

</tr>

<tr>

<th>Category</th>

<td><?= htmlspecialchars($scenario['category']) ?></td>

</tr>

<tr>

<th>Difficulty</th>

<td><?= htmlspecialchars($scenario['difficulty']) ?></td>

</tr>

<tr>

<th>Estimated Time</th>

<td><?= $scenario['estimated_time'] ?> Minutes</td>

</tr>

<tr>

<th>Status</th>

<td><?= htmlspecialchars($scenario['status']) ?></td>

</tr>

<tr>

<th>Created By</th>

<td><?= htmlspecialchars($scenario['full_name']) ?></td>

</tr>

<tr>

<th>Date Created</th>

<td><?= date("d F, Y h:i A",strtotime($scenario['created_at'])) ?></td>

</tr>

</table>

</div>

</div>

<div class="card dashboard-card mb-4">

<div class="card-header bg-success text-white">

Scenario Instructions

</div>

<div class="card-body">

<?= nl2br(htmlspecialchars($scenario['instructions'])) ?>

</div>

</div>

<div class="card dashboard-card">

<div class="card-header bg-info text-white">

Expected Outcome

</div>

<div class="card-body">

<?= nl2br(htmlspecialchars($scenario['expected_outcome'])) ?>

</div>

</div>

</div>

<div class="col-lg-4">

<div class="card dashboard-card mb-4">

<div class="card-header bg-dark text-white">

Future Modules

</div>

<div class="card-body">

<table class="table">

<tr>

<td>

<i class="bi bi-router-fill text-primary"></i>

Devices

</td>

<td class="text-end">

<span class="badge bg-secondary">

0

</span>

</td>

</tr>

<tr>

<td>

<i class="bi bi-bug-fill text-danger"></i>

Faults

</td>

<td class="text-end">

<span class="badge bg-secondary">

0

</span>

</td>

</tr>

<tr>

<td>

<i class="bi bi-question-circle-fill text-success"></i>

Assessment Questions

</td>

<td class="text-end">

<span class="badge bg-secondary">

0

</span>

</td>

</tr>

<tr>

<td>

<i class="bi bi-person-workspace text-warning"></i>

Student Attempts

</td>

<td class="text-end">

<span class="badge bg-secondary">

0

</span>

</td>

</tr>

<tr>

<td>

<i class="bi bi-cpu-fill text-info"></i>

AI Diagnostic Rules

</td>

<td class="text-end">

<span class="badge bg-secondary">

0

</span>

</td>

</tr>

</table>

</div>

</div>

<div class="card dashboard-card">

<div class="card-header bg-secondary text-white">

Scenario Summary

</div>

<div class="card-body">

<p>

This scenario is ready to become a complete virtual networking laboratory.

</p>

<ul class="mb-0">

<li>Network Topology</li>

<li>Device Configuration</li>

<li>Fault Injection</li>

<li>Automatic Assessment</li>

<li>AI Diagnosis</li>

<li>Performance Reports</li>

</ul>

</div>

</div>

</div>

</div>

</div>

<?php

require_once '../includes/layout_end.php';

?>