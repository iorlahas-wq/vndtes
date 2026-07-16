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

    if($deviceCode=="")
        $errors[]="Device Code is required.";

    if($deviceName=="")
        $errors[]="Device Name is required.";

    $stmt=db()->prepare("
        SELECT COUNT(*)
        FROM devices
        WHERE device_code=?
    ");

    $stmt->execute([$deviceCode]);

    if($stmt->fetchColumn()){

        $errors[]="Device Code already exists.";

    }

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

        )

        WHERE device_id=?

        )

        ");

        $stmt->execute([

            $deviceCode,
            $deviceName,
            $deviceType,
            $vendor,
            $model,
            $interfaceCount,
            $description,
            $status

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

Add Device

</h2>

<p class="text-muted">

Register Network Device

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
class="form-select" selected="<?= htmlspecialchars($device['device_type']) ?>">

<option>Router</option>
<option>Switch</option>
<option>Layer3 Switch</option>
<option>PC</option>
<option>Server</option>
<option>Wireless Router</option>
<option>Access Point</option>
<option>Firewall</option>
<option>Cloud</option>

</select>

</div>

<div class="mb-3">

<label>Vendor</label>

<select
name="vendor"
class="form-select" selected="<?= htmlspecialchars($device['vendor']) ?>">

<option>Cisco</option>
<option>Juniper</option>
<option>MikroTik</option>
<option>Huawei</option>
<option>Generic</option>

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
value="<?= htmlspecialchars($device['model']) ?>"

</div>

<div class="mb-3">

<label>Interface Count</label>

<input
type="number"
name="interface_count"
class="form-control"
value="<?= $device['interface_count'] ?>">

</div>

<div class="mb-3">

<label>Description</label>

<textarea
name="description"
rows="5"
class="form-control"></textarea>

</div>

<div class="mb-3">

<label>Status</label>

<select
name="status"
class="form-select" selected="<?= htmlspecialchars($device['status']) ?>">

<option>Active</option>
<option>Inactive</option>

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

Save Device

</button>

</div>

</form>

</div>

<?php require_once '../includes/layout_end.php'; ?>