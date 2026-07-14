<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!=="Lecturer"){

redirect(APP_URL);

}

$pageTitle="Lecturer Dashboard";

require_once '../includes/layout_start.php';

?>

<div class="container">

<div class="card">

<div class="card-body">

<h2>

Lecturer Dashboard

</h2>

<p>

Welcome

<strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong>

</p>

<p>

Scenario Builder,

Student Reports,

Analytics

will appear here.

</p>

</div>

</div>

</div>

<?php require_once '../includes/layout_end.php'; ?>