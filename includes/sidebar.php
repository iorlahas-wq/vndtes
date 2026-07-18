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

        

       <?php

$currentGroup = '';

foreach($menu as $item){

    if($currentGroup != $item['group']){

        $currentGroup = $item['group'];
        if($currentGroup != ''){

        // print heading

    
        ?>

        <li class="sidebar-title mt-4">

            <?= strtoupper($currentGroup) ?>

        </li>

        <?php
    }
    }

    ?>

    <li>

        <a
        href="<?= $item['url'] ?>"
        class="<?= basename($_SERVER['PHP_SELF'])==basename($item['url']) ? 'active':'' ?>">

            <i class="bi <?= $item['icon'] ?>"></i>

            <?= $item['title'] ?>

        </a>

    </li>

    <?php

}

?>

    </ul>

</div>