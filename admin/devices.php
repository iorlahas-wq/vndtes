<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

$pageTitle="Device Library";

$totalDevices=db()->query("
SELECT COUNT(*)
FROM devices
")->fetchColumn();

$devices=db()->query("

SELECT *

FROM devices

ORDER BY device_type,device_name

")->fetchAll();

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2 class="fw-bold">

Device Library

</h2>

<p class="text-muted">

Manage Network Devices

</p>

</div>

<a
href="device_add.php"
class="btn btn-primary">

<i class="bi bi-plus-circle-fill"></i>

Add Device

</a>

</div>

<div class="row mb-4">

<div class="col-lg-3">

<div class="card dashboard-card">

<div class="card-body">

<h5>Total Devices</h5>

<h2>

<?= number_format($totalDevices) ?>

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

<th>Name</th>

<th>Type</th>

<th>Vendor</th>

<th>Model</th>

<th>Interfaces</th>

<th>Status</th>

<th width="150">

Action

</th>

</tr>

</thead>

<tbody>

<?php foreach($devices as $device): ?>

<tr>

<td><?= htmlspecialchars($device['device_code']) ?></td>

<td><?= htmlspecialchars($device['device_name']) ?></td>

<td><?= htmlspecialchars($device['device_type']) ?></td>

<td><?= htmlspecialchars($device['vendor']) ?></td>

<td><?= htmlspecialchars($device['model']) ?></td>

<td><?= $device['interface_count'] ?></td>

<td>

<?php

if($device['status']=="Active"){

echo '<span class="badge bg-success">Active</span>';

}else{

echo '<span class="badge bg-secondary">Inactive</span>';

}

?>

</td>

<td>

<a
href="device_view.php?id=<?= $device['device_id'] ?>"
class="btn btn-info btn-sm">

<i class="bi bi-eye-fill"></i>

</a>

<a
href="device_edit.php?id=<?= $device['device_id'] ?>"
class="btn btn-warning btn-sm">

<i class="bi bi-pencil-fill"></i>

</a>

<a
href="device_delete.php?id=<?= $device['device_id'] ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Delete this device?')">

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

<?php

require_once '../includes/layout_end.php';

?>