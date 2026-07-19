<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

$pageTitle="View Fault";

if(empty($_GET['id'])){
    redirect("faults.php");
}

$faultID=(int)$_GET['id'];

$fault=findFault($faultID);

if(!$fault){
    redirect("faults.php");
}

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2 class="fw-bold">

Fault Details

</h2>

<p class="text-muted">

View details of this troubleshooting fault.

</p>

</div>

<div>

<a
href="fault_edit.php?id=<?= $faultID ?>"
class="btn btn-warning">

<i class="bi bi-pencil-fill"></i>

Edit

</a>

<a
href="faults.php"
class="btn btn-secondary">

<i class="bi bi-arrow-left"></i>

Back

</a>

</div>

</div>

<div class="row">

<div class="col-lg-6">

<div class="card dashboard-card mb-4">

<div class="card-header bg-primary text-white">

Basic Information

</div>

<div class="card-body">

<div class="mb-3">

<label class="fw-bold">

Fault Code

</label>

<div class="form-control">

<?= htmlspecialchars($fault['fault_code']) ?>

</div>

</div>

<div class="mb-3">

<label class="fw-bold">

Fault Title

</label>

<div class="form-control">

<?= htmlspecialchars($fault['fault_title']) ?>

</div>

</div>

<div class="mb-3">

<label class="fw-bold">

Category

</label>

<div class="form-control">

<?= htmlspecialchars($fault['category']) ?>

</div>

</div>

<div class="mb-3">

<label class="fw-bold">

Affected Device

</label>

<div class="form-control">

<?= htmlspecialchars($fault['affected_device_type']) ?>

</div>

</div>

<div class="mb-3">

<label class="fw-bold">

Difficulty

</label>

<div class="form-control">

<?= htmlspecialchars($fault['difficulty']) ?>

</div>

</div>

<div class="mb-3">

<label class="fw-bold">

Estimated Troubleshooting Time

</label>

<div class="form-control">

<?= htmlspecialchars($fault['estimated_time']) ?> Minutes

</div>

</div>

<div class="mb-3">

<label class="fw-bold">

Status

</label>

<div class="form-control">

<?php if($fault['status']=="Active"){ ?>

<span class="badge bg-success">

Active

</span>

<?php }else{ ?>

<span class="badge bg-secondary">

Inactive

</span>

<?php } ?>

</div>

</div>

</div>

</div>

</div>

<div class="col-lg-6">

<div class="card dashboard-card mb-4">

<div class="card-header bg-success text-white">

Troubleshooting Information

</div>

<div class="card-body">

<div class="mb-3">

<label class="fw-bold">

Symptoms

</label>

<div class="form-control" style="min-height:90px;">

<?= !empty($fault['symptoms'])
    ? nl2br(htmlspecialchars($fault['symptoms']))
    : '<span class="text-muted">No symptoms specified.</span>' ?>

</div>

</div>

<div class="mb-3">

<label class="fw-bold">

Probable Cause

</label>

<div class="form-control" style="min-height:90px;">

<?= !empty($fault['probable_cause'])
    ? nl2br(htmlspecialchars($fault['probable_cause']))
    : '<span class="text-muted">No probable cause specified.</span>' ?>

</div>

</div>

<div class="mb-3">

<label class="fw-bold">

Expected Solution

</label>

<div class="form-control" style="min-height:110px;">

<?= !empty($fault['expected_solution'])
    ? nl2br(htmlspecialchars($fault['expected_solution']))
    : '<span class="text-muted">No expected solution specified.</span>' ?>

</div>

</div>

<div class="mb-3">

<label class="fw-bold">

Date Created

</label>

<div class="form-control">

<?= date('F d, Y h:i A',strtotime($fault['created_at'])) ?>

</div>

</div>

<div class="alert alert-info mb-0">

<h6 class="fw-bold">

<i class="bi bi-lightbulb-fill"></i>

Fault Summary

</h6>

<p class="mb-2">

<strong><?= htmlspecialchars($fault['fault_title']) ?></strong>

is classified under

<strong><?= htmlspecialchars($fault['category']) ?></strong>

and affects

<strong><?= htmlspecialchars($fault['affected_device_type']) ?></strong> devices.

</p>

<p class="mb-0">

This troubleshooting record is available for use in practical scenarios and intelligent diagnostic exercises within the VNDTES platform.

</p>

</div>

</div>

</div>

</div>

</div>

<div class="text-end">

<a
href="fault_edit.php?id=<?= $fault['fault_id'] ?>"
class="btn btn-warning">

<i class="bi bi-pencil-fill"></i>

Edit Fault

</a>

<a
href="faults.php"
class="btn btn-secondary">

<i class="bi bi-arrow-left"></i>

Back to List

</a>

</div>

</div>

<?php

require_once '../includes/layout_end.php';

?>