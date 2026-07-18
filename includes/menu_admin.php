<?php

$menu = [

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    [
        'group' => '',
        'title' => 'Dashboard',
        'icon'  => 'bi-speedometer2',
        'url'   => APP_URL . '/admin/dashboard.php'
    ],

    /*
    |--------------------------------------------------------------------------
    | User Management
    |--------------------------------------------------------------------------
    */

    [
        'group' => 'User Management',
        'title' => 'User Accounts',
        'icon'  => 'bi-people',
        'url'   => APP_URL . '/admin/users.php'
    ],

    [
        'group' => 'User Management',
        'title' => 'Students',
        'icon'  => 'bi-mortarboard',
        'url'   => APP_URL . '/admin/students.php'
    ],

    [
        'group' => 'User Management',
        'title' => 'Lecturers',
        'icon'  => 'bi-person-workspace',
        'url'   => APP_URL . '/admin/lecturers.php'
    ],

    /*
    |--------------------------------------------------------------------------
    | Training
    |--------------------------------------------------------------------------
    */

    [
        'group' => 'Training',
        'title' => 'Scenarios',
        'icon'  => 'bi-diagram-3',
        'url'   => APP_URL . '/admin/scenarios.php'
    ],

    [
        'group' => 'Training',
        'title' => 'Device Library',
        'icon'  => 'bi-pc-display',
        'url'   => APP_URL . '/admin/devices.php'
    ],

    /*
    |--------------------------------------------------------------------------
    | System
    |--------------------------------------------------------------------------
    */

    [
        'group' => 'System',
        'title' => 'Reports',
        'icon'  => 'bi-graph-up',
        'url'   => APP_URL . '/admin/reports.php'
    ],

    [
        'group' => 'System',
        'title' => 'Settings',
        'icon'  => 'bi-gear',
        'url'   => APP_URL . '/admin/settings.php'
    ]

];