<?php

$role = currentUserRole();

$menu = [];

/*
|--------------------------------------------------------------------------
| Load Role Menu
|--------------------------------------------------------------------------
*/

switch ($role) {

    case 'Administrator':
        require __DIR__ . '/menu_admin.php';
        break;

    case 'Lecturer':
        require __DIR__ . '/menu_lecturer.php';
        break;

    case 'Student':
        require __DIR__ . '/menu_student.php';
        break;
}

?>

<div id="sidebar" class="sidebar">

    <ul class="nav flex-column">

        <li class="sidebar-title">

            Navigation

        </li>

        <?php foreach ($menu as $item): ?>

        <li>

            <a href="<?= $item['url']; ?>">

                <i class="bi <?= $item['icon']; ?>"></i>

                <?= htmlspecialchars($item['title']); ?>

            </a>

        </li>

        <?php endforeach; ?>

    </ul>

</div>