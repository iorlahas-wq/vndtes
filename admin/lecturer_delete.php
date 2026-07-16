<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole() !== "Administrator"){
    redirect(APP_URL);
}

if(empty($_GET['id'])){
    redirect("lecturers.php");
}

$lecturerID = (int)$_GET['id'];

$lecturer = findLecturer($lecturerID);

if(!$lecturer){
    redirect("lecturers.php");
}

try{

    db()->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Delete Lecturer Record
    |--------------------------------------------------------------------------
    */

    $stmt = db()->prepare("
        DELETE FROM lecturers
        WHERE lecturer_id = ?
    ");

    $stmt->execute([$lecturerID]);

    /*
    |--------------------------------------------------------------------------
    | Delete User Account
    |--------------------------------------------------------------------------
    */

    $stmt = db()->prepare("
        DELETE FROM users
        WHERE user_id = ?
    ");

    $stmt->execute([$lecturer['user_id']]);

    db()->commit();

}
catch(Throwable $e){

    db()->rollBack();

    die($e->getMessage());

}

redirect("lecturers.php");