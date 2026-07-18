<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

$pageTitle="Edit Scenario Device Mapping";

if(empty($_GET['id'])){
    redirect("scenario_devices.php");
}

$mappingID=(int)$_GET['id'];

$mapping=findScenarioDevice($mappingID);

if(!$mapping){
    redirect("scenario_devices.php");
}

$message="";

$scenarios=getScenarios();

$devices=getDevices();

if($_SERVER['REQUEST_METHOD']=="POST"){

    $scenarioID=(int)$_POST['scenario_id'];
    $deviceID=(int)$_POST['device_id'];
    $quantity=(int)$_POST['quantity'];
    $notes=trim($_POST['notes']);

    $errors=[];

    if($scenarioID<=0){
        $errors[]="Please select a scenario.";
    }

    if($deviceID<=0){
        $errors[]="Please select a device.";
    }

    if($quantity<1){
        $errors[]="Quantity must be at least 1.";
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent Duplicate Mapping
    |--------------------------------------------------------------------------
    */

    if(
        empty($errors)
        &&
        scenarioDeviceExists(
            $scenarioID,
            $deviceID,
            $mappingID
        )
    ){

        $errors[]="This device has already been assigned to the selected scenario.";

    }

    /*
    |--------------------------------------------------------------------------
    | Update Mapping
    |--------------------------------------------------------------------------
    */

    if(empty($errors)){

        $stmt=db()->prepare("

            UPDATE scenario_devices

            SET

                scenario_id=?,
                device_id=?,
                quantity=?,
                notes=?

            WHERE

                scenario_device_id=?

        ");

        $stmt->execute([

            $scenarioID,
            $deviceID,
            $quantity,
            $notes,
            $mappingID

        ]);

        redirect("scenario_devices.php");

    }

    else{

        $message=alert(

            implode("<br>",$errors),

            "danger"

        );

    }

}

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2 class="fw-bold">

Edit Scenario Device Mapping

</h2>

<p class="text-muted">

Update device assignment for this training scenario.

</p>

</div>

<a
href="scenario_devices.php"
class="btn btn-secondary">

<i class="bi bi-arrow-left"></i>

Back

</a>

</div>

<?= $message ?>

<form method="POST">

<div class="row">

<div class="col-lg-6">

<div class="card dashboard-card mb-4">

<div class="card-header bg-primary text-white">

Scenario Information

</div>

<div class="card-body">

<div class="mb-3">

<label class="form-label">

Scenario

</label>

<select
name="scenario_id"
class="form-select"
required>

<?php

foreach($scenarios as $scenario){

$currentScenario=

$_POST['scenario_id']

??

$mapping['scenario_id'];

?>

<option

value="<?= $scenario['scenario_id'] ?>"

<?=

$currentScenario==$scenario['scenario_id']

?

'selected'

:

''

?>>

<?= htmlspecialchars($scenario['scenario_code']) ?>

-

<?= htmlspecialchars($scenario['scenario_title']) ?>

</option>

<?php } ?>

</select>

</div>

<div class="mb-3">

<label class="form-label">

Device

</label>

<select
name="device_id"
class="form-select"
required>

<?php

foreach($devices as $device){

$currentDevice=

$_POST['device_id']

??

$mapping['device_id'];

?>

<option

value="<?= $device['device_id'] ?>"

<?=

$currentDevice==$device['device_id']

?

'selected'

:

''

?>>

<?= htmlspecialchars($device['device_name']) ?>

(

<?= htmlspecialchars($device['device_type']) ?>

)

</option>

<?php } ?>

</select>

</div>

<div class="mb-3">

<label class="form-label">

Quantity

</label>

<input
type="number"
name="quantity"
class="form-control"
min="1"
value="<?= htmlspecialchars($_POST['quantity'] ?? $mapping['quantity']) ?>"
required>

</div>

</div>

</div>

</div>

<div class="col-lg-6">

<div class="card dashboard-card mb-4">

<div class="card-header bg-success text-white">

Additional Information

</div>

<div class="card-body">

<div class="mb-3">

<label class="form-label">

Notes

</label>

<textarea
name="notes"
rows="7"
class="form-control"
placeholder="Optional notes about this device assignment..."><?= htmlspecialchars($_POST['notes'] ?? $mapping['notes']) ?></textarea>

</div>

<div class="alert alert-info">

<h6 class="fw-bold mb-3">

<i class="bi bi-info-circle-fill"></i>

Current Mapping Summary

</h6>

<table class="table table-sm mb-0">

<tr>

<th width="35%">

Scenario

</th>

<td>

<?= htmlspecialchars($mapping['scenario_title']) ?>

</td>

</tr>

<tr>

<th>

Device

</th>

<td>

<?= htmlspecialchars($mapping['device_name']) ?>

</td>

</tr>

<tr>

<th>

Type

</th>

<td>

<?= htmlspecialchars($mapping['device_type']) ?>

</td>

</tr>

<tr>

<th>

Vendor

</th>

<td>

<?= htmlspecialchars($mapping['vendor']) ?>

</td>

</tr>

<tr>

<th>

Current Quantity

</th>

<td>

<?= htmlspecialchars($mapping['quantity']) ?>

</td>

</tr>

</table>

</div>

</div>

</div>

</div>

</div>

<div class="text-end">

<a
href="scenario_devices.php"
class="btn btn-secondary">

<i class="bi bi-x-circle"></i>

Cancel

</a>

<button
type="submit"
class="btn btn-primary">

<i class="bi bi-save-fill"></i>

Update Mapping

</button>

</div>

</form>

</div>

<?php

require_once '../includes/layout_end.php';

?>