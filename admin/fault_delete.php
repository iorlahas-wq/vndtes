<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

if(empty($_GET['id'])){
    redirect("faults.php");
}

$faultID=(int)$_GET['id'];

$fault=findFault($faultID);

if(!$fault){
    redirect("faults.php");
}

/*
|--------------------------------------------------------------------------
| Delete Fault
|--------------------------------------------------------------------------
*/

$stmt=db()->prepare("
    DELETE FROM faults
    WHERE fault_id=?
");

$stmt->execute([$faultID]);

redirect("faults.php");