<?php

require_once '../includes/init.php';
require_once '../includes/auth.php';

if(currentUserRole()!="Administrator"){

    redirect(APP_URL);

}

if(empty($_GET['id'])){

    redirect("scenarios.php");

}

$scenarioID=(int)$_GET['id'];

$scenario=findScenario($scenarioID);

if(!$scenario){

    redirect("scenarios.php");

}

try{

    $stmt=db()->prepare("

        DELETE FROM scenarios

        WHERE scenario_id=?

    ");

    $stmt->execute([

        $scenarioID

    ]);

}
catch(Throwable $e){

    die($e->getMessage());

}

redirect("scenarios.php");