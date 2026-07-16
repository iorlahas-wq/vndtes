<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

$pageTitle="Create Scenario";

$message="";

if($_SERVER['REQUEST_METHOD']=="POST"){

    $scenarioCode=sanitize($_POST['scenario_code']);
    $scenarioTitle=sanitize($_POST['scenario_title']);
    $category=$_POST['category'];
    $difficulty=$_POST['difficulty'];
    $estimatedTime=(int)$_POST['estimated_time'];

    $instructions=trim($_POST['instructions']);
    $expectedOutcome=trim($_POST['expected_outcome']);

    $status=$_POST['status'];

    $errors=[];

    if($scenarioCode=="")
        $errors[]="Scenario Code is required.";

    if($scenarioTitle=="")
        $errors[]="Scenario Title is required.";

    if($instructions=="")
        $errors[]="Instructions are required.";

    /*
    -------------------------------------------------------
    Duplicate Scenario Code
    -------------------------------------------------------
    */

    $stmt=db()->prepare("
        SELECT COUNT(*)
        FROM scenarios
        WHERE scenario_code=?
    ");

    $stmt->execute([$scenarioCode]);

    if($stmt->fetchColumn()){

        $errors[]="Scenario Code already exists.";

    }

    /*
    -------------------------------------------------------
    Save
    -------------------------------------------------------
    */

    if(empty($errors)){

        $stmt=db()->prepare("

            INSERT INTO scenarios(

                scenario_code,
                scenario_title,
                category,
                difficulty,
                estimated_time,
                instructions,
                expected_outcome,
                status,
                created_by

            )

            VALUES(

                ?,?,?,?,?,?,?,?,?

            )

        ");

        $stmt->execute([

            $scenarioCode,

            $scenarioTitle,

            $category,

            $difficulty,

            $estimatedTime,

            $instructions,

            $expectedOutcome,

            $status,

            currentUserId()

        ]);

        redirect("scenarios.php");

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

Create Scenario

</h2>

<p class="text-muted">

Create a Virtual Network Training Scenario

</p>

</div>

<a href="scenarios.php"
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

Scenario Information

</div>

<div class="card-body">

<div class="mb-3">

<label>Scenario Code</label>

<input
type="text"
name="scenario_code"
class="form-control"
placeholder="SCN-001"
required>

</div>

<div class="mb-3">

<label>Scenario Title</label>

<input
type="text"
name="scenario_title"
class="form-control"
required>

</div>

<div class="mb-3">

<label>Category</label>

<select
name="category"
class="form-select">

<option>Routing</option>
<option>Switching</option>
<option>Subnetting</option>
<option>Wireless</option>
<option>Security</option>
<option>Network Services</option>
<option>Mixed</option>

</select>

</div>

<div class="row">

<div class="col-md-6">

<div class="mb-3">

<label>Difficulty</label>

<select
name="difficulty"
class="form-select">

<option>Beginner</option>
<option>Intermediate</option>
<option>Advanced</option>

</select>

</div>

</div>

<div class="col-md-6">

<div class="mb-3">

<label>Estimated Time (mins)</label>

<input
type="number"
name="estimated_time"
class="form-control"
value="30">

</div>

</div>

</div>

<div class="mb-3">

<label>Status</label>

<select
name="status"
class="form-select">

<option>Draft</option>
<option>Published</option>
<option>Archived</option>

</select>

</div>

</div>

</div>

</div>

<div class="col-lg-6">

<div class="card dashboard-card mb-4">

<div class="card-header bg-success text-white">

Scenario Content

</div>

<div class="card-body">

<div class="mb-3">

<label>Instructions</label>

<textarea
name="instructions"
rows="8"
class="form-control"
required></textarea>

</div>

<div class="mb-3">

<label>Expected Outcome</label>

<textarea
name="expected_outcome"
rows="6"
class="form-control"></textarea>

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

Save Scenario

</button>

</div>

</form>

</div>

<?php require_once '../includes/layout_end.php'; ?>