<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

$pageTitle="Assign Device to Scenario";

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

    if(empty($errors) && scenarioDeviceExists($scenarioID,$deviceID)){
        $errors[]="This device has already been assigned to the selected scenario.";
    }

    /*
    |--------------------------------------------------------------------------
    | Save Mapping
    |--------------------------------------------------------------------------
    */

    if(empty($errors)){

        $stmt=db()->prepare("
            INSERT INTO scenario_devices
            (
                scenario_id,
                device_id,
                quantity,
                notes
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmt->execute([

            $scenarioID,
            $deviceID,
            $quantity,
            $notes

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

Assign Device to Scenario

</h2>

<p class="text-muted">

Map network devices required for a training scenario

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

<option value="">

-- Select Scenario --

</option>

<?php foreach($scenarios as $scenario){ ?>

<option
value="<?= $scenario['scenario_id'] ?>"
<?= isset($_POST['scenario_id']) && $_POST['scenario_id']==$scenario['scenario_id'] ? 'selected' : '' ?>>

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

<option value="">

-- Select Device --

</option>

<?php foreach($devices as $device){ ?>

<option
value="<?= $device['device_id'] ?>"
<?= isset($_POST['device_id']) && $_POST['device_id']==$device['device_id'] ? 'selected' : '' ?>>

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
value="<?= isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1 ?>"
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
rows="8"
class="form-control"
placeholder="Optional notes about this device assignment..."><?= isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '' ?></textarea>

</div>

<div class="alert alert-info">

<h6 class="fw-bold">

<i class="bi bi-info-circle-fill"></i>

Assignment Guidelines

</h6>

<ul class="mb-0">

<li>Select one scenario.</li>

<li>Select one device from the Device Library.</li>

<li>Specify how many of the selected device are required.</li>

<li>Notes are optional but recommended.</li>

<li>A device cannot be assigned twice to the same scenario.</li>

</ul>

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

Save Mapping

</button>

</div>

</form>

</div>

<?php

require_once '../includes/layout_end.php';

?>