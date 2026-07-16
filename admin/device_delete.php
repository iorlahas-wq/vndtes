<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

if(empty($_GET['id'])){
    redirect("devices.php");
}

$stmt=db()->prepare("

DELETE FROM devices

WHERE device_id=?

");

$stmt->execute([

(int)$_GET['id']

]);

redirect("devices.php");