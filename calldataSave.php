<!--
This page is posted to by the call log to save the record's data
via the console. This is useful for debugging and resolving enduser issues.
-->

<?php

$module = new \UWMadison\CustomCallLog\CustomCallLog();

$pid = $_GET['pid'];
$record = $_POST['record'];
$instance = $_POST['instance'];
$var = $_POST['dataVar'];
$val = $_POST['dataVal'];

if (json_decode($_POST['isCheckbox']))
    $val = json_decode($val,true);

if( !empty($pid) && !empty($record) && !empty($instance) && !empty($var) && !is_null($val) ) {
    $module->saveCallData($pid, $record, $instance, $var, $val);
    echo "Done";
} else {
    echo "Malformed POST to ".__FILE__;
}

?>