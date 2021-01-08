<!--
This page is posted to by the call list to flag a call
as no longer in progress
-->

<?php

$module = new \UWMadison\CustomCallLog\CustomCallLog();

$pid = $_GET['pid'];
$record = $_POST['record'];
$call_id = $_POST['id'];

if( !empty($pid) && !empty($record) && !empty($call_id) ) {
    $module->metadataCallEnded($pid, $record, $call_id);
    echo "Done";
} else {
    echo "Malformed POST to ".__FILE__;
}

?>