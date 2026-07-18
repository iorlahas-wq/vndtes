<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

$pageTitle="Scenario Device Mapping";

$mappings=getScenarioDeviceMappings();

$totalMappings=countScenarioDeviceMappings();

$totalScenarios=count(getScenarios());

$totalDevices=count(getDevices());

$search="";

if(isset($_GET['search'])){
    $search=trim($_GET['search']);
}

if($search!=""){

    $mappings=array_filter($mappings,function($row) use($search){

        return
            stripos($row['scenario_title'],$search)!==false ||
            stripos($row['device_name'],$search)!==false ||
            stripos($row['device_type'],$search)!==false ||
            stripos($row['vendor'],$search)!==false;

    });

}

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2 class="fw-bold">

Scenario Device Mapping

</h2>

<p class="text-muted">

Manage devices required for each training scenario

</p>

</div>

<a
href="scenario_device_add.php"
class="btn btn-primary">

<i class="bi bi-plus-circle-fill"></i>

Assign Device

</a>

</div>

<div class="row mb-4">

<div class="col-md-4">

<div class="card dashboard-card">

<div class="card-body">

<h6 class="text-muted">

Total Mappings

</h6>

<h2 class="fw-bold">

<?= $totalMappings ?>

</h2>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card dashboard-card">

<div class="card-body">

<h6 class="text-muted">

Scenarios

</h6>

<h2 class="fw-bold">

<?= $totalScenarios ?>

</h2>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card dashboard-card">

<div class="card-body">

<h6 class="text-muted">

Devices

</h6>

<h2 class="fw-bold">

<?= $totalDevices ?>

</h2>

</div>

</div>

</div>

</div>

<div class="card dashboard-card">

<div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">

<span>

Scenario Device List

</span>

<form
method="GET"
class="d-flex">

<input
type="text"
name="search"
class="form-control form-control-sm me-2"
placeholder="Search..."
value="<?= htmlspecialchars($search) ?>">

<button
type="submit"
class="btn btn-light btn-sm">

<i class="bi bi-search"></i>

</button>

</form>

</div>

<div class="card-body p-0">

<div class="table-responsive">

<table class="table table-hover table-striped align-middle mb-0">

<thead class="table-dark">

<tr>

<th width="60">

#

</th>

<th>

Scenario

</th>

<th>

Device

</th>

<th>

Type

</th>

<th>

Vendor

</th>

<th class="text-center">

Qty

</th>

<th>

Notes

</th>

<th width="180" class="text-center">

Actions

</th>

</tr>

</thead>

<tbody>

<?php

$sn=1;

foreach($mappings as $row){

?>

<tr>

<td>

<?= $sn++ ?>

</td>

<td>

<strong><?= htmlspecialchars($row['scenario_title']) ?></strong>

<br>

<small class="text-muted">

<?= htmlspecialchars($row['scenario_code']) ?>

</small>

</td>

<td>

<strong><?= htmlspecialchars($row['device_name']) ?></strong>

<br>

<small class="text-muted">

<?= htmlspecialchars($row['device_code']) ?>

</small>

</td>

<td>

<?= htmlspecialchars($row['device_type']) ?>

</td>

<td>

<?= htmlspecialchars($row['vendor']) ?>

</td>

<td class="text-center">

<span class="badge bg-primary">

<?= $row['quantity'] ?>

</span>

</td>

<td>

<?= !empty($row['notes'])
        ? htmlspecialchars($row['notes'])
        : '<span class="text-muted">None</span>' ?>

</td>

<td class="text-center">

<a
href="scenario_device_view.php?id=<?= $row['scenario_device_id'] ?>"
class="btn btn-sm btn-info"
title="View">

<i class="bi bi-eye-fill"></i>

</a>

<a
href="scenario_device_edit.php?id=<?= $row['scenario_device_id'] ?>"
class="btn btn-sm btn-warning"
title="Edit">

<i class="bi bi-pencil-fill"></i>

</a>

<a
href="scenario_device_delete.php?id=<?= $row['scenario_device_id'] ?>"
class="btn btn-sm btn-danger"
title="Delete"
onclick="return confirm('Are you sure you want to remove this device from the scenario?');">

<i class="bi bi-trash-fill"></i>

</a>

</td>

</tr>

<?php

}

if(empty($mappings)){

?>

<tr>

<td colspan="8" class="text-center py-5">

<i class="bi bi-diagram-3 display-4 text-muted"></i>

<h5 class="mt-3">

No Scenario Device Mappings Found

</h5>

<p class="text-muted">

Click <strong>Assign Device</strong> to create the first mapping.

</p>

<a
href="scenario_device_add.php"
class="btn btn-primary">

<i class="bi bi-plus-circle-fill"></i>

Assign Device

</a>

</td>

</tr>

<?php

}

?>

</tbody>

</table>

</div>

</div>

</div>

</div>

<?php

require_once '../includes/layout_end.php';

?>