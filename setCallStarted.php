<!--
This page is posted to by the call list to flag a call
as in progress
-->

<?php

$module = new \UWMadison\CustomCallLog\CustomCallLog();

$pid = $_GET['pid'];
$record = $_POST['record'];
$call_id = $_POST['id'];
$user = $_POST['user'];

if( !empty($pid) && !empty($record) && !empty($call_id) && !empty($user) ) {
    $module->metadataCallStarted($pid, $record, $call_id, $user);
    echo "Done";
} else {
    echo "Malformed POST to ".__FILE__;
}

?>