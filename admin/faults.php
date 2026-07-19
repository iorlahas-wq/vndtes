<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

$pageTitle="Fault Library";

$search=trim($_GET['search'] ?? '');

$sql="

SELECT *

FROM faults

";

$params=[];

if($search!=""){

    $sql.="

    WHERE

        fault_code LIKE ?

        OR fault_title LIKE ?

        OR category LIKE ?

        OR affected_device_type LIKE ?

    ";

    $keyword="%".$search."%";

    $params=[

        $keyword,
        $keyword,
        $keyword,
        $keyword

    ];

}

$sql.="

ORDER BY

fault_title ASC

";

$stmt=db()->prepare($sql);

$stmt->execute($params);

$faults=$stmt->fetchAll();

$totalFaults=countFaults();

$activeFaults=countActiveFaults();

$inactiveFaults=countInactiveFaults();

$totalCategories=countFaultCategories();

require_once '../includes/layout_start.php';

?>

<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2 class="fw-bold">

Fault Library

</h2>

<p class="text-muted">

Manage all network troubleshooting faults available in VNDTES.

</p>

</div>

<a
href="fault_add.php"
class="btn btn-primary">

<i class="bi bi-plus-circle"></i>

Add Fault

</a>

</div>

<div class="row mb-4">

<div class="col-md-3">

<div class="card dashboard-card border-primary">

<div class="card-body">

<h6>Total Faults</h6>

<h2><?= $totalFaults ?></h2>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card dashboard-card border-success">

<div class="card-body">

<h6>Active</h6>

<h2><?= $activeFaults ?></h2>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card dashboard-card border-danger">

<div class="card-body">

<h6>Inactive</h6>

<h2><?= $inactiveFaults ?></h2>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card dashboard-card border-warning">

<div class="card-body">

<h6>Categories</h6>

<h2><?= $totalCategories ?></h2>

</div>

</div>

</div>

</div>

<div class="card">

<div class="card-header bg-light">

<form method="GET">

<div class="row">

<div class="col-md-10">

<input
type="text"
name="search"
class="form-control"
placeholder="Search by Code, Title, Category or Device Type..."
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-2 d-grid">

<button
class="btn btn-primary">

<i class="bi bi-search"></i>

Search

</button>

</div>

</div>

</form>

</div>

<div class="table-responsive">

<table class="table table-hover table-striped align-middle mb-0">

<thead class="table-dark">

<tr>

<th>Code</th>

<th>Title</th>

<th>Category</th>

<th>Device</th>

<th>Difficulty</th>

<th>Status</th>

<th width="150">

Actions

</th>

</tr>

</thead>

<tbody>
    <?php if(count($faults)>0){ ?>

<?php foreach($faults as $fault){ ?>

<tr>

<td>

<?= htmlspecialchars($fault['fault_code']) ?>

</td>

<td>

<?= htmlspecialchars($fault['fault_title']) ?>

</td>

<td>

<span class="badge bg-info">

<?= htmlspecialchars($fault['category']) ?>

</span>

</td>

<td>

<?= htmlspecialchars($fault['affected_device_type']) ?>

</td>

<td>

<?php

switch($fault['difficulty']){

    case 'Beginner':
        $badge='success';
        break;

    case 'Intermediate':
        $badge='warning';
        break;

    default:
        $badge='danger';

}

?>

<span class="badge bg-<?= $badge ?>">

<?= htmlspecialchars($fault['difficulty']) ?>

</span>

</td>

<td>

<?php if($fault['status']=="Active"){ ?>

<span class="badge bg-success">

Active

</span>

<?php }else{ ?>

<span class="badge bg-secondary">

Inactive

</span>

<?php } ?>

</td>

<td>

<div class="btn-group btn-group-sm">

<a
href="fault_view.php?id=<?= $fault['fault_id'] ?>"
class="btn btn-info"
title="View">

<i class="bi bi-eye-fill"></i>

</a>

<a
href="fault_edit.php?id=<?= $fault['fault_id'] ?>"
class="btn btn-warning"
title="Edit">

<i class="bi bi-pencil-fill"></i>

</a>

<a
href="fault_delete.php?id=<?= $fault['fault_id'] ?>"
class="btn btn-danger"
title="Delete"
onclick="return confirm('Are you sure you want to delete this fault?');">

<i class="bi bi-trash-fill"></i>

</a>

</div>

</td>

</tr>

<?php } ?>

<?php }else{ ?>

<tr>

<td colspan="7" class="text-center py-5">

<div class="text-muted">

<i class="bi bi-database-x fs-1 d-block mb-3"></i>

No faults found.

</div>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</div>

<?php

require_once '../includes/layout_end.php';

?>