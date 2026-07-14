<?php
?>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top shadow-sm">

<div class="container-fluid">

<button class="btn btn-outline-light me-3"
id="sidebarToggle">

<i class="bi bi-list"></i>

</button>

<a class="navbar-brand" href="<?= APP_URL; ?>">

VNDTES

</a>

<div class="ms-auto">

<?php if(isLoggedIn()): ?>

<span class="text-white me-3">

<i class="bi bi-person-circle"></i>

<?= htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>

</span>

<a href="<?= APP_URL ?>/auth/logout.php"
class="btn btn-light btn-sm">

Logout

</a>

<?php endif; ?>

</div>

</div>

</nav>