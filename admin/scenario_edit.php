<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

if(empty($_GET['id'])){
    redirect("scenarios.php");
}

$scenarioID=(int)$_GET['id'];

$scenario=findScenario($scenarioID);

if(!$scenario){
    redirect("scenarios.php");
}

$pageTitle="Edit Scenario";

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
    ------------------------------------------------------------
    Duplicate Code
    ------------------------------------------------------------
    */

    $stmt=db()->prepare("

        SELECT COUNT(*)

        FROM scenarios

        WHERE scenario_code=?

        AND scenario_id<>?

    ");

    $stmt->execute([

        $scenarioCode,

        $scenarioID

    ]);

    if($stmt->fetchColumn()){

        $errors[]="Scenario Code already exists.";

    }

    /*
    ------------------------------------------------------------
    Update
    ------------------------------------------------------------
    */

    if(empty($errors)){

        $stmt=db()->prepare("

            UPDATE scenarios

            SET

                scenario_code=?,

                scenario_title=?,

                category=?,

                difficulty=?,

                estimated_time=?,

                instructions=?,

                expected_outcome=?,

                status=?

            WHERE scenario_id=?

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

            $scenarioID

        ]);

        redirect("scenario_view.php?id=".$scenarioID);

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

Edit Scenario

</h2>

<p class="text-muted">

Update Network Training Scenario

</p>

</div>

<a href="scenario_view.php?id=<?= $scenarioID ?>"
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
value="<?= htmlspecialchars($scenario['scenario_code']) ?>"
required>

</div>

<div class="mb-3">

<label>Scenario Title</label>

<input
type="text"
name="scenario_title"
class="form-control"
value="<?= htmlspecialchars($scenario['scenario_title']) ?>"
required>

</div>

<div class="mb-3">

<label>Category</label>

<select
name="category"
class="form-select">

<?php

$categories=[
"Routing",
"Switching",
"Subnetting",
"Wireless",
"Security",
"Network Services",
"Mixed"
];

foreach($categories as $cat){

?>

<option
value="<?= $cat ?>"
<?= $scenario['category']==$cat ? 'selected':'' ?>>

<?= $cat ?>

</option>

<?php } ?>

</select>

</div>

<div class="row">

<div class="col-md-6">

<div class="mb-3">

<label>Difficulty</label>

<select
name="difficulty"
class="form-select">

<?php

$levels=[
"Beginner",
"Intermediate",
"Advanced"
];

foreach($levels as $level){

?>

<option
value="<?= $level ?>"
<?= $scenario['difficulty']==$level ? 'selected':'' ?>>

<?= $level ?>

</option>

<?php } ?>

</select>

</div>

</div>

<div class="col-md-6">

<div class="mb-3">

<label>Estimated Time</label>

<input
type="number"
name="estimated_time"
class="form-control"
value="<?= $scenario['estimated_time'] ?>">

</div>

</div>

</div>

<div class="mb-3">

<label>Status</label>

<select
name="status"
class="form-select">

<?php

$statuses=[
"Draft",
"Published",
"Archived"
];

foreach($statuses as $state){

?>

<option
value="<?= $state ?>"
<?= $scenario['status']==$state ? 'selected':'' ?>>

<?= $state ?>

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

Scenario Content

</div>

<div class="card-body">

<div class="mb-3">

<label>Instructions</label>

<textarea
name="instructions"
rows="8"
class="form-control"
required><?= htmlspecialchars($scenario['instructions']) ?></textarea>

</div>

<div class="mb-3">

<label>Expected Outcome</label>

<textarea
name="expected_outcome"
rows="6"
class="form-control"><?= htmlspecialchars($scenario['expected_outcome']) ?></textarea>

</div>

</div>

</div>

</div>

</div>

<div class="text-end">

<button
type="submit"
class="btn btn-primary">

<i class="bi bi-check-circle-fill"></i>

Update Scenario

</button>

</div>

</form>

</div>

<?php require_once '../includes/layout_end.php'; ?>