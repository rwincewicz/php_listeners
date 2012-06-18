<?php

include_once 'fedoraConnection.php';

$fedora_url = 'http://localhost:8080/fedora';
$user_string = 'fedoraAdmin';
$pass_string = 'fedoraAdmin';
$user = new stdClass();
$user->name = $user_string;
$user->pass = $pass_string;

while (TRUE) {
  try {
    $fedora_object = new ListenerObject($user, $fedora_url, 'islandora:root');
    $pid = 'islandora:' . rand('100', '999999');
    $object = $fedora_object->repository->constructObject($pid);
    $object->label = 'Test';
//    $datastream = $object->constructDatastream('TN', 'M');
//    $datastream->label = 'TN';
//    $datastream->mimetype = 'image/png';
//    $datastream->setContentFromFile('Crystal_Clear_action_filenew.png');
//    $object->ingestDatastream($datastream);
    $datastream2 = $object->constructDatastream('TIFF', 'M');
    $datastream2->label = 'Tiff';
    $datastream2->mimetype = 'image/tiff';
    $datastream2->setContentFromFile('00103-a_0103.tif');
    $object->ingestDatastream($datastream2);
    $fedora_object->repository->ingestObject($object);
    echo "Object $pid ingested!\n\n";
  } catch (Exception $e) {
    echo $e;
  }
  sleep('30');
}
?>
