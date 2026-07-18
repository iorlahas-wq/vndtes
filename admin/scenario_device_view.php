<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

$pageTitle="View Scenario Device Mapping";

if(empty($_GET['id'])){
    redirect("scenario_devices.php");
}

$mappingID=(int)$_GET['id'];

$mapping=findScenarioDevice($mappingID);

if(!$mapping){
    redirect("scenario_devices.php");
}

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2 class="fw-bold">

Scenario Device Mapping Details

</h2>

<p class="text-muted">

View assigned device information for this training scenario.

</p>

</div>

<div>

<a
href="scenario_device_edit.php?id=<?= $mappingID ?>"
class="btn btn-warning">

<i class="bi bi-pencil-fill"></i>

Edit

</a>

<a
href="scenario_devices.php"
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

Scenario Information

</div>

<div class="card-body">

<div class="mb-3">

<label class="fw-bold">

Scenario Code

</label>

<div class="form-control">

<?= htmlspecialchars($mapping['scenario_code']) ?>

</div>

</div>

<div class="mb-3">

<label class="fw-bold">

Scenario Title

</label>

<div class="form-control">

<?= htmlspecialchars($mapping['scenario_title']) ?>

</div>

</div>

<div class="mb-3">

<label class="fw-bold">

Device Code

</label>

<div class="form-control">

<?= htmlspecialchars($mapping['device_code']) ?>

</div>

</div>

<div class="mb-3">

<label class="fw-bold">

Device Name

</label>

<div class="form-control">

<?= htmlspecialchars($mapping['device_name']) ?>

</div>

</div>

<div class="mb-3">

<label class="fw-bold">

Device Type

</label>

<div class="form-control">

<?= htmlspecialchars($mapping['device_type']) ?>

</div>

</div>
<div class="col-lg-6">

<div class="card dashboard-card mb-4">

<div class="card-header bg-success text-white">

Mapping Details

</div>

<div class="card-body">

<div class="mb-3">

<label class="fw-bold">

Vendor

</label>

<div class="form-control">

<?= htmlspecialchars($mapping['vendor']) ?>

</div>

</div>

<div class="mb-3">

<label class="fw-bold">

Quantity

</label>

<div class="form-control">

<?= htmlspecialchars($mapping['quantity']) ?>

</div>

</div>

<div class="mb-3">

<label class="fw-bold">

Notes

</label>

<div class="form-control" style="min-height:120px;">

<?= !empty($mapping['notes'])
        ? nl2br(htmlspecialchars($mapping['notes']))
        : '<span class="text-muted">No notes provided.</span>' ?>

</div>

</div>

<div class="mb-3">

<label class="fw-bold">

Date Assigned

</label>

<div class="form-control">

<?= date('F d, Y h:i A',strtotime($mapping['created_at'])) ?>

</div>

</div>

<div class="alert alert-info mb-0">

<h6 class="fw-bold">

<i class="bi bi-diagram-3-fill"></i>

Mapping Summary

</h6>

<p class="mb-1">

<strong>Scenario:</strong>

<?= htmlspecialchars($mapping['scenario_title']) ?>

</p>

<p class="mb-1">

<strong>Device:</strong>

<?= htmlspecialchars($mapping['device_name']) ?>

</p>

<p class="mb-1">

<strong>Quantity Required:</strong>

<?= htmlspecialchars($mapping['quantity']) ?>

</p>

<p class="mb-0">

This device is currently assigned to this training scenario and will be available whenever the scenario is used for practical exercises.

</p>

</div>

</div>

</div>

</div>

</div>

<div class="text-end">

<a
href="scenario_device_edit.php?id=<?= $mapping['scenario_device_id'] ?>"
class="btn btn-warning">

<i class="bi bi-pencil-fill"></i>

Edit Mapping

</a>

<a
href="scenario_devices.php"
class="btn btn-secondary">

<i class="bi bi-arrow-left"></i>

Back to List

</a>

</div>

</div>

<?php

require_once '../includes/layout_end.php';

?>