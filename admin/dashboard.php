<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!=="Administrator"){

redirect(APP_URL);

}

$pageTitle="Administrator Dashboard";

require_once '../includes/layout_start.php';
?>

<div class="container-fluid">

<div class="row">

<div class="col-md-12 mb-4">

<h2>

Administrator Dashboard

</h2>

<p>

Welcome back,

<strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong>

</p>

</div>

<div class="col-lg-3 col-md-6 mb-4">

<div class="card">

<div class="card-body">

<h5>

<i class="bi bi-people-fill text-primary"></i>

Users

</h5>

<h2>

0

</h2>

</div>

</div>

</div>

<div class="col-lg-3 col-md-6 mb-4">

<div class="card">

<div class="card-body">

<h5>

<i class="bi bi-mortarboard-fill text-success"></i>

Students

</h5>

<h2>

0

</h2>

</div>

</div>

</div>

<div class="col-lg-3 col-md-6 mb-4">

<div class="card">

<div class="card-body">

<h5>

<i class="bi bi-person-workspace text-warning"></i>

Lecturers

</h5>

<h2>

0

</h2>

</div>

</div>

</div>

<div class="col-lg-3 col-md-6 mb-4">

<div class="card">

<div class="card-body">

<h5>

<i class="bi bi-router-fill text-danger"></i>

Scenarios

</h5>

<h2>

0

</h2>

</div>

</div>

</div>

</div>

</div>

<?php require_once '../includes/layout_end.php'; ?>