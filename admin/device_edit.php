<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

$pageTitle="Edit Device";

if(empty($_GET['id'])){
    redirect("devices.php");
}

$deviceID=(int)$_GET['id'];

$device=findDevice($deviceID);

if(!$device){
    redirect("devices.php");
}

$message="";

if($_SERVER['REQUEST_METHOD']=="POST"){

    $deviceCode=sanitize($_POST['device_code']);
    $deviceName=sanitize($_POST['device_name']);
    $deviceType=$_POST['device_type'];
    $vendor=$_POST['vendor'];
    $model=sanitize($_POST['model']);
    $interfaceCount=(int)$_POST['interface_count'];
    $description=trim($_POST['description']);
    $status=$_POST['status'];

    $errors=[];

    if($deviceCode==""){
        $errors[]="Device Code is required.";
    }

    if($deviceName==""){
        $errors[]="Device Name is required.";
    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate Device Code
    |--------------------------------------------------------------------------
    */

    $stmt=db()->prepare("
        SELECT COUNT(*)
        FROM devices
        WHERE device_code=?
        AND device_id<>?
    ");

    $stmt->execute([
        $deviceCode,
        $deviceID
    ]);

    if($stmt->fetchColumn()){
        $errors[]="Device Code already exists.";
    }

    /*
    |--------------------------------------------------------------------------
    | Update Device
    |--------------------------------------------------------------------------
    */

    if(empty($errors)){

        $stmt=db()->prepare("
            UPDATE devices
            SET
                device_code=?,
                device_name=?,
                device_type=?,
                vendor=?,
                model=?,
                interface_count=?,
                description=?,
                status=?
            WHERE device_id=?
        ");

        $stmt->execute([

            $deviceCode,
            $deviceName,
            $deviceType,
            $vendor,
            $model,
            $interfaceCount,
            $description,
            $status,
            $deviceID

        ]);

        redirect("devices.php");

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

Edit Device

</h2>

<p class="text-muted">

Update Network Device Information

</p>

</div>

<a
href="devices.php"
class="btn btn-secondary">

Back

</a>

</div>

<?= $message ?>

<form method="POST">

<div class="row">

<div class="col-lg-6">

<div class="card dashboard-card mb-4">

<div class="card-header bg-primary text-white">

Device Information

</div>

<div class="card-body">

<div class="mb-3">

<label>Device Code</label>

<input
type="text"
name="device_code"
class="form-control"
value="<?= htmlspecialchars($device['device_code']) ?>"
required>

</div>

<div class="mb-3">

<label>Device Name</label>

<input
type="text"
name="device_name"
class="form-control"
value="<?= htmlspecialchars($device['device_name']) ?>"
required>

</div>

<div class="mb-3">

<label>Device Type</label>

<select
name="device_type"
class="form-select">

<?php

$types=[
'Router',
'Switch',
'Layer3 Switch',
'PC',
'Server',
'Wireless Router',
'Access Point',
'Firewall',
'Cloud'
];

foreach($types as $type){

?>

<option
value="<?= $type ?>"
<?= $device['device_type']==$type?'selected':'' ?>>

<?= $type ?>

</option>

<?php } ?>

</select>

</div>

<div class="mb-3">

<label>Vendor</label>

<select
name="vendor"
class="form-select">

<?php

$vendors=[
'Cisco',
'Juniper',
'MikroTik',
'Huawei',
'Generic'
];

foreach($vendors as $v){

?>

<option
value="<?= $v ?>"
<?= $device['vendor']==$v?'selected':'' ?>>

<?= $v ?>

</option>

<?php } ?>

</select>

</div>

</div>

</div>

</div>

<div class="col-lg-6">

<div class="card dashboard-card mb-4">

<div class="card-header bg-success text-white">

Technical Details

</div>

<div class="card-body">

<div class="mb-3">

<label>Model</label>

<input
type="text"
name="model"
class="form-control"
value="<?= htmlspecialchars($device['model']) ?>">

</div>

<div class="mb-3">

<label>Interface Count</label>

<input
type="number"
name="interface_count"
class="form-control"
value="<?= htmlspecialchars($device['interface_count']) ?>">

</div>

<div class="mb-3">

<label>Description</label>

<textarea
name="description"
rows="5"
class="form-control"><?= htmlspecialchars($device['description']) ?></textarea>

</div>

<div class="mb-3">

<label>Status</label>

<select
name="status"
class="form-select">

<option
value="Active"
<?= $device['status']=="Active"?'selected':'' ?>>

Active

</option>

<option
value="Inactive"
<?= $device['status']=="Inactive"?'selected':'' ?>>

Inactive

</option>

</select>

</div>

</div>

</div>

</div>

</div>

<div class="text-end">

<button
type="submit"
class="btn btn-primary">

<i class="bi bi-save-fill"></i>

Update Device

</button>

</div>

</form>

</div>

<?php

require_once '../includes/layout_end.php';

?>