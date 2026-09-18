<?php

/*
|--------------------------------------------------------------------------
| VNDTES Lecturer Navigation
|--------------------------------------------------------------------------
|
| Complete navigation structure for the Lecturer role.
|
| IMPORTANT:
|
| Some pages listed below may not exist yet. They are intentionally
| included as part of the planned Lecturer module structure so that
| the sidebar also serves as a development roadmap.
|
| Record-specific operations such as EDIT and DELETE are deliberately
| NOT placed in the sidebar. Those operations should be reached from
| the relevant record's listing/view page.
|
|--------------------------------------------------------------------------
*/


$menu = [

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    [
        'title' => 'Dashboard',
        'url'   => 'dashboard.php',
        'icon'  => 'bi-speedometer2',
        'group' => ''
    ],


    /*
    |--------------------------------------------------------------------------
    | Training
    |--------------------------------------------------------------------------
    */

    [
        'title' => 'My Scenarios',
        'url'   => 'scenarios.php',
        'icon'  => 'bi-diagram-3',
        'group' => 'Training'
    ],

    [
        'title' => 'Create Scenario',
        'url'   => 'scenario_add.php',
        'icon'  => 'bi-plus-circle',
        'group' => 'Training'
    ],

    [
        'title' => 'Scenario Devices',
        'url'   => 'scenario_devices.php',
        'icon'  => 'bi-router',
        'group' => 'Training'
    ],

    [
        'title' => 'Scenario Faults',
        'url'   => 'scenario_faults.php',
        'icon'  => 'bi-bug',
        'group' => 'Training'
    ],


    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    |
    | These pages are planned lecturer-facing report pages.
    | They can be created as development progresses.
    |
    */

    [
        'title' => 'Training Reports',
        'url'   => 'reports.php',
        'icon'  => 'bi-bar-chart-line',
        'group' => 'Reports'
    ],

    [
        'title' => 'Student Performance',
        'url'   => 'student_reports.php',
        'icon'  => 'bi-people',
        'group' => 'Reports'
    ],


    /*
    |--------------------------------------------------------------------------
    | Account
    |--------------------------------------------------------------------------
    |
    | Future lecturer account/profile management.
    |
    */

    [
        'title' => 'My Profile',
        'url'   => 'profile.php',
        'icon'  => 'bi-person-circle',
        'group' => 'Account'
    ],

];

?>