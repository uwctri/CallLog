<!--
This page is posted to by the call log to save a new adhoc call
It should not be posted to from any other source
-->

<?php

$module = new \UWMadison\CustomCallLog\CustomCallLog();

$pid = $_GET['pid'];
$record = $_POST['record'];
$callID = $_POST['id'];

if( !empty($pid) && !empty($record) && !empty($callID) ) {
    $module->metadataAdhoc( $pid, $record, [
        'id' => $callID,
        'date' => $_POST['date'],
        'time' => $_POST['time'],
        'reason' => $_POST['reason'],
        'notes' => $_POST['notes'],
        'reporter' => $_POST['reporter']
    ]);
    echo "Done";
} else {
    echo "Malformed POST to ".__FILE__;
}

?>