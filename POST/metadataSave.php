<!--
This page is posted to by the call log to save the record's metadata
via the console. This is useful for debugging and resolving enduser issues.
-->

<?php

$module = new \UWMadison\CustomCallLog\CustomCallLog();

$pid = $_GET['pid'];
$record = $_POST['record'];
$metadata = $_POST['metadata'];

if( !empty($pid) && !empty($record) ) {
    $module->saveCallMetadata( $pid, $record, json_decode($metadata, true) );
    echo "Done";
} else {
    echo "Malformed POST to ".__FILE__;
}

?>