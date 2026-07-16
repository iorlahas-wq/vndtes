<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!=="Administrator"){

    redirect(APP_URL);

}

if(empty($_GET['id'])){

    redirect("students.php");

}

$student=findStudent((int)$_GET['id']);

if(!$student){

    redirect("students.php");

}

try{

    db()->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Disable User
    |--------------------------------------------------------------------------
    */

   $stmt = db()->prepare("

    UPDATE users

    SET

        account_status='Disabled',

        updated_by=?,

        updated_at=NOW()

    WHERE user_id=?

");

    $stmt->execute([

        currentUserId(),

        $student['user_id']

    ]);

    db()->commit();

}
catch(Throwable $e){

    db()->rollBack();

}

redirect(APP_URL."/admin/students.php");

