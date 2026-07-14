<?php
/**
 * ==========================================================
 * VNDTES
 * Master Layout Start
 * ==========================================================
 */

if (!isset($pageTitle)) {
    $pageTitle = APP_NAME;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?> | <?= APP_NAME ?></title>

    <!-- Bootstrap CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        rel="stylesheet">

    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500&display=swap"
        rel="stylesheet">

    <!-- Master CSS -->
    <link
        rel="stylesheet"
        href="<?= APP_URL ?>/assets/css/master.css">

</head>

<body>

<div class="wrapper">

<?php if (isLoggedIn()): ?>

    <?php require_once __DIR__ . '/navbar.php'; ?>

    <?php require_once __DIR__ . '/sidebar.php'; ?>

<?php endif; ?>

<div class="content-wrapper">

<div class="container-fluid py-4">