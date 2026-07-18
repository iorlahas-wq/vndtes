<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){
    redirect(APP_URL);
}

if(empty($_GET['id'])){
    redirect("scenario_devices.php");
}

$mappingID=(int)$_GET['id'];

$mapping=findScenarioDevice($mappingID);

if(!$mapping){
    redirect("scenario_devices.php");
}

/*
|--------------------------------------------------------------------------
| Delete Mapping
|--------------------------------------------------------------------------
*/

$stmt=db()->prepare("
    DELETE FROM scenario_devices
    WHERE scenario_device_id=?
");

$stmt->execute([$mappingID]);

redirect("scenario_devices.php");