<!--
This page is posted to by the call log to delete the last 
saved instance of the call log instrument.
-->

<?php

$module = new \UWMadison\CustomCallLog\CustomCallLog();

$pid = $_GET['pid'];
$record = $_POST['record'];

if( !empty($pid) && !empty($record) ) {
    $module->deleteLastCallInstance($pid, $record);
    echo "Done";
} else {
    echo "Malformed POST to ".__FILE__;
}

?>