<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

$pageTitle="Edit Fault";

if(empty($_GET['id'])){
    redirect("faults.php");
}

$faultID=(int)$_GET['id'];

$fault=findFault($faultID);

if(!$fault){
    redirect("faults.php");
}

$message="";

if($_SERVER['REQUEST_METHOD']=="POST"){

    $faultCode=trim($_POST['fault_code']);
    $faultTitle=trim($_POST['fault_title']);
    $category=$_POST['category'];
    $difficulty=$_POST['difficulty'];
    $deviceType=$_POST['affected_device_type'];

    $symptoms=trim($_POST['symptoms']);
    $cause=trim($_POST['probable_cause']);
    $solution=trim($_POST['expected_solution']);

    $estimatedTime=(int)$_POST['estimated_time'];

    $status=$_POST['status'];

    $errors=[];

    if($faultCode==""){
        $errors[]="Fault Code is required.";
    }

    if($faultTitle==""){
        $errors[]="Fault Title is required.";
    }

    if($category==""){
        $errors[]="Category is required.";
    }

    if($deviceType==""){
        $errors[]="Affected Device Type is required.";
    }

    if($estimatedTime<1){
        $errors[]="Estimated troubleshooting time must be greater than zero.";
    }

    if(
        empty($errors)
        &&
        faultExists($faultCode,$faultID)
    ){
        $errors[]="Fault Code already exists.";
    }

    if(empty($errors)){

        $stmt=db()->prepare("

            UPDATE faults

            SET

                fault_code=?,
                fault_title=?,
                category=?,
                difficulty=?,
                affected_device_type=?,
                symptoms=?,
                probable_cause=?,
                expected_solution=?,
                estimated_time=?,
                status=?

            WHERE

                fault_id=?

        ");

        $stmt->execute([

            $faultCode,
            $faultTitle,
            $category,
            $difficulty,
            $deviceType,
            $symptoms,
            $cause,
            $solution,
            $estimatedTime,
            $status,
            $faultID

        ]);

        redirect("faults.php");

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

Edit Network Fault

</h2>

<p class="text-muted">

Update an existing troubleshooting fault.

</p>

</div>

<a
href="faults.php"
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

Basic Information

</div>

<div class="card-body">

<div class="mb-3">

<label class="form-label">

Fault Code

</label>

<input
type="text"
name="fault_code"
class="form-control"
value="<?= htmlspecialchars($_POST['fault_code'] ?? $fault['fault_code']) ?>"
required>

</div>

<div class="mb-3">

<label class="form-label">

Fault Title

</label>

<input
type="text"
name="fault_title"
class="form-control"
value="<?= htmlspecialchars($_POST['fault_title'] ?? $fault['fault_title']) ?>"
required>

</div>

<div class="mb-3">

<label class="form-label">

Category

</label>

<select
name="category"
class="form-select"
required>

<?php

$categories=[

"IP Addressing",

"Subnetting",

"Routing",

"Switching",

"VLAN",

"DHCP",

"DNS",

"Firewall",

"Wireless",

"Physical Connectivity",

"Security",

"Network Services"

];

$currentCategory=$_POST['category'] ?? $fault['category'];

foreach($categories as $cat){

?>

<option

value="<?= $cat ?>"

<?= $currentCategory==$cat?'selected':'' ?>

>

<?= $cat ?>

</option>

<?php } ?>

</select>

</div>

<div class="mb-3">

<label class="form-label">

Difficulty

</label>

<select
name="difficulty"
class="form-select">

<?php

$currentDifficulty=$_POST['difficulty'] ?? $fault['difficulty'];

?>

<option value="Beginner" <?= $currentDifficulty=="Beginner"?'selected':'' ?>>

Beginner

</option>

<option value="Intermediate" <?= $currentDifficulty=="Intermediate"?'selected':'' ?>>

Intermediate

</option>

<option value="Advanced" <?= $currentDifficulty=="Advanced"?'selected':'' ?>>

Advanced

</option>

</select>

</div>

<div class="mb-3">

<label class="form-label">

Affected Device Type

</label>

<select
name="affected_device_type"
class="form-select"
required>

<?php

$deviceTypes=[

"Router",

"Switch",

"Layer 3 Switch",

"PC",

"Server",

"Firewall",

"Access Point",

"Wireless Router",

"Cloud",

"General"

];

$currentDevice=$_POST['affected_device_type'] ?? $fault['affected_device_type'];

foreach($deviceTypes as $device){

?>

<option
value="<?= $device ?>"
<?= $currentDevice==$device?'selected':'' ?>>

<?= $device ?>

</option>

<?php } ?>

</select>

</div>

<div class="mb-3">

<label class="form-label">

Estimated Troubleshooting Time (Minutes)

</label>

<input
type="number"
name="estimated_time"
class="form-control"
min="1"
value="<?= htmlspecialchars($_POST['estimated_time'] ?? $fault['estimated_time']) ?>"
required>

</div>

<div class="mb-3">

<label class="form-label">

Status

</label>

<?php

$currentStatus=$_POST['status'] ?? $fault['status'];

?>

<select
name="status"
class="form-select">

<option value="Active" <?= $currentStatus=="Active"?'selected':'' ?>>

Active

</option>

<option value="Inactive" <?= $currentStatus=="Inactive"?'selected':'' ?>>

Inactive

</option>

</select>

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

<label class="form-label">

Symptoms

</label>

<textarea
name="symptoms"
rows="4"
class="form-control"><?= htmlspecialchars($_POST['symptoms'] ?? $fault['symptoms']) ?></textarea>

</div>

<div class="mb-3">

<label class="form-label">

Probable Cause

</label>

<textarea
name="probable_cause"
rows="4"
class="form-control"><?= htmlspecialchars($_POST['probable_cause'] ?? $fault['probable_cause']) ?></textarea>

</div>

<div class="mb-3">

<label class="form-label">

Expected Solution

</label>

<textarea
name="expected_solution"
rows="5"
class="form-control"><?= htmlspecialchars($_POST['expected_solution'] ?? $fault['expected_solution']) ?></textarea>

</div>

<div class="alert alert-info mb-0">

<i class="bi bi-lightbulb-fill"></i>

<strong>Reminder:</strong>

Update the troubleshooting information carefully. These details will later be used by the Scenario Fault Mapping module and the Intelligent Diagnostic Engine to guide students through troubleshooting exercises.

</div>

</div>

</div>

</div>

</div>

<div class="text-end">

<a
href="faults.php"
class="btn btn-secondary">

<i class="bi bi-x-circle"></i>

Cancel

</a>

<button
type="submit"
class="btn btn-primary">

<i class="bi bi-save-fill"></i>

Update Fault

</button>

</div>

</form>

</div>

<?php

require_once '../includes/layout_end.php';

?>