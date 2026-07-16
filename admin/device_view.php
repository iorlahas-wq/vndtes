<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

if(empty($_GET['id'])){
    redirect("devices.php");
}

$device=findDevice((int)$_GET['id']);

if(!$device){
    redirect("devices.php");
}

$pageTitle="Device Details";

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2 class="fw-bold">

Device Details

</h2>

</div>

<div>

<a
href="device_edit.php?id=<?= $device['device_id'] ?>"
class="btn btn-primary">

Edit

</a>

<a
href="devices.php"
class="btn btn-secondary">

Back

</a>

</div>

</div>

<div class="card dashboard-card">

<div class="card-header bg-primary text-white">

Device Information

</div>

<div class="card-body">

<table class="table table-bordered">

<tr>

<th width="30%">Device Code</th>

<td><?= htmlspecialchars($device['device_code']) ?></td>

</tr>

<tr>

<th>Device Name</th>

<td><?= htmlspecialchars($device['device_name']) ?></td>

</tr>

<tr>

<th>Type</th>

<td><?= htmlspecialchars($device['device_type']) ?></td>

</tr>

<tr>

<th>Vendor</th>

<td><?= htmlspecialchars($device['vendor']) ?></td>

</tr>

<tr>

<th>Model</th>

<td><?= htmlspecialchars($device['model']) ?></td>

</tr>

<tr>

<th>Interfaces</th>

<td><?= $device['interface_count'] ?></td>

</tr>

<tr>

<th>Status</th>

<td><?= htmlspecialchars($device['status']) ?></td>

</tr>

<tr>

<th>Description</th>

<td><?= nl2br(htmlspecialchars($device['description'])) ?></td>

</tr>

<tr>

<th>Created</th>

<td><?= date("d F Y h:i A",strtotime($device['created_at'])) ?></td>

</tr>

</table>

</div>

</div>

</div>

<?php require_once '../includes/layout_end.php'; ?>