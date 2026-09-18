<?php

/*
|--------------------------------------------------------------------------
| VNDTES Role-Based Sidebar
|--------------------------------------------------------------------------
|
| Loads a different navigation menu according to the logged-in user's role.
|
| Administrator:
| - Full system and training-resource management
|
| Lecturer:
| - Own scenario creation and management
| - Scenario-device and scenario-fault management through scenarios
|
| Student:
| - Student-facing training functions
|
|--------------------------------------------------------------------------
*/

$role = currentUserRole();

$menu = [];

/*
|--------------------------------------------------------------------------
| Load Role-Specific Menu
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


/*
|--------------------------------------------------------------------------
| Current Page
|--------------------------------------------------------------------------
|
| Used to highlight the active sidebar item.
|
|--------------------------------------------------------------------------
*/

$currentPage = basename($_SERVER['PHP_SELF']);

?>

<div id="sidebar" class="sidebar">

    <ul class="nav flex-column">

        <?php

        $currentGroup = '';

        foreach ($menu as $item) {

            /*
            |--------------------------------------------------------------------------
            | Validate Menu Item
            |--------------------------------------------------------------------------
            */

            $itemGroup = $item['group'] ?? '';
            $itemTitle = $item['title'] ?? '';
            $itemUrl   = $item['url'] ?? '#';
            $itemIcon  = $item['icon'] ?? 'bi-circle';


            /*
            |--------------------------------------------------------------------------
            | Print Group Heading
            |--------------------------------------------------------------------------
            |
            | Dashboard remains outside a group when group is empty.
            |
            |--------------------------------------------------------------------------
            */

            if ($currentGroup !== $itemGroup) {

                $currentGroup = $itemGroup;

                if ($currentGroup !== '') {
                    ?>

                    <li class="sidebar-title mt-4">
                        <?= htmlspecialchars(strtoupper($currentGroup)) ?>
                    </li>

                    <?php
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Determine Active Page
            |--------------------------------------------------------------------------
            |
            | parse_url() ensures query strings such as:
            |
            | scenario_faults.php?id=9
            |
            | do not interfere with page comparison.
            |
            |--------------------------------------------------------------------------
            */

            $menuPath = parse_url($itemUrl, PHP_URL_PATH);
            $menuPage = basename($menuPath);

            $isActive = ($currentPage === $menuPage);

            ?>

            <li>

                <a
                    href="<?= htmlspecialchars($itemUrl) ?>"
                    class="<?= $isActive ? 'active' : '' ?>"
                >

                    <i class="bi <?= htmlspecialchars($itemIcon) ?>"></i>

                    <?= htmlspecialchars($itemTitle) ?>

                </a>

            </li>

            <?php

        }

        ?>

    </ul>

</div>