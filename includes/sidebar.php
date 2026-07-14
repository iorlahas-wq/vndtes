<?php

$role = currentUserRole();

?>

<div id="sidebar" class="sidebar">

    <ul class="nav flex-column">

        <li class="sidebar-title">

            Navigation

        </li>

<?php

switch($role){

case 'Administrator':

?>

<li><a href="<?= APP_URL ?>/admin/dashboard.php">

<i class="bi bi-speedometer2"></i>

Dashboard

</a></li>

<li><a href="<?= APP_URL ?>/admin/users.php">

<i class="bi bi-people"></i>

Users

</a></li>

<li><a href="<?= APP_URL ?>/admin/scenarios.php">

<i class="bi bi-diagram-3"></i>

Scenarios

</a></li>

<li><a href="<?= APP_URL ?>/admin/settings.php">

<i class="bi bi-gear"></i>

Settings

</a></li>

<?php

break;

case 'Lecturer':

?>

<li><a href="<?= APP_URL ?>/lecturer/dashboard.php">

<i class="bi bi-speedometer2"></i>

Dashboard

</a></li>

<li><a href="<?= APP_URL ?>/lecturer/scenarios.php">

<i class="bi bi-diagram-3"></i>

Scenario Builder

</a></li>

<li><a href="<?= APP_URL ?>/lecturer/reports.php">

<i class="bi bi-graph-up"></i>

Reports

</a></li>

<?php

break;

case 'Student':

?>

<li><a href="<?= APP_URL ?>/student/dashboard.php">

<i class="bi bi-speedometer2"></i>

Dashboard

</a></li>

<li><a href="<?= APP_URL ?>/student/scenarios.php">

<i class="bi bi-router"></i>

Scenarios

</a></li>

<li><a href="<?= APP_URL ?>/student/results.php">

<i class="bi bi-award"></i>

Results

</a></li>

<?php

break;

}

?>

    </ul>

</div>