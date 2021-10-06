<?php
# This page handles all GET/POST requests from scripts and internally from the EM
$module = new \UWMadison\CustomCallLog\CustomCallLog();
$route = $_POST['route'] ?? $_GET['route'];
$pid = $_GET['pid'];

$sendSuccess = False;
$sendDone = False;
$sendMalformed = True;

switch ( $route ) {
    case "adhocLoad":
        $record = $_POST['record'];
        $call_id = $_POST['id'];
        if( !empty($pid) && !empty($record) && !empty($call_id) ) {
            $module->metadataAdhoc( $pid, $record, [
                'id' => $call_id,
                'date' => $_POST['date'],
                'time' => $_POST['time'],
                'reason' => $_POST['reason'],
                'notes' => $_POST['notes'],
                'reporter' => $_POST['reporter']
            ]);
            $sendDone = True;
        }
        break;
    case "adhocResolve":
        #This page is intended to be posted to by an outside script or DET to resolve an existing adhoc call on a record(s)
        #The URL is of the form: https://ctri-redcap.dom.wisc.edu/redcap/redcap_v10.2.1/ExternalModules/?prefix=CTRI_Custom_CallLog&page=adhocResolve&pid=NNN&adhocCode=NNN&recordList=NNN
        foreach ( explode(',',$_GET['recordList']) as $record ) {
            $module->resolveAdhoc($pid,trim($record),$_GET['adhocCode']);
        }
        $sendDone = True;
        break;
    case "calldataSave":
        $record = $_POST['record'];
        $instance = $_POST['instance'];
        $var = $_POST['dataVar'];
        $val = $_POST['dataVal'];
        if (json_decode($_POST['isCheckbox']))
            $val = json_decode($val,true);

        if( !empty($pid) && !empty($record) && !empty($instance) && !empty($var) && !is_null($val) ) {
            $module->saveCallData($pid, $record, $instance, $var, $val);
            $sendDone = True;
        }
        break;
    case "callDelete":
        $record = $_POST['record'];
        if( !empty($pid) && !empty($record) ) {
            $module->deleteLastCallInstance($pid, $record);
            $sendDone = True;
        }
        break;
    case "metadataSave":
        $record = $_POST['record'];
        if( !empty($pid) && !empty($record) ) {
            $module->saveCallMetadata( $pid, $record, json_decode($_POST['metadata'], true) );
            $sendDone = True;
        }
        break;
    case "newAdhoc":
        $module->metadataAdhoc( $pid, $_GET['record'], [
            'id' => $_GET['type'],
            'date' => $_GET['fudate'],
            'time' => $_GET['futime'],
            'reason' => $_GET['adhocCode'],
            'reporter' => $_GET['reporter']
        ]);
        $sendDone = True;
        break;
    case "newEntryLoad":
        foreach ( explode(',',$_GET['recordList']) as $record ) {
            $module->metadataNewEntry($pid,trim($record));
        }
        $sendDone = True;
        break;
    case "scheduleLoad":
        foreach ( explode(',',$_GET['recordList']) as $record ) {
            $module->metadataReminder($pid,trim($record));
            $module->metadataMissedCancelled($pid,trim($record));
            $module->metadataNeedToSchedule($pid,trim($record));
        }
        $sendDone = True;
        break;
    case "setCallEnded":
        $record = $_POST['record'];
        $call_id = $_POST['id'];
        if( !empty($pid) && !empty($record) && !empty($call_id) ) {
            $module->metadataCallEnded($pid, $record, $call_id);
            $sendDone = True;
        } 
        break;
    case "setCallStarted":
        $record = $_POST['record'];
        $call_id = $_POST['id'];
        $user = $_POST['user'];
        if( !empty($pid) && !empty($record) && !empty($call_id) && !empty($user) ) {
            $module->metadataCallStarted($pid, $record, $call_id, $user);
            $sendDone = True;
        } 
        break;
    case "setNoCallsToday":
        $record = $_POST['record'];
        $call_id = $_POST['id'];
        if( !empty($pid) && !empty($record) && !empty($call_id) ) {
            $module->metadataNoCallsToday($pid, $record, $call_id);
            $sendDone = True;
        } 
        break;
}

if ( $sendDone ) {
    echo json_encode([
        "text" => "Done",
        "success" => false
    ]);
} elseif ( $sendSuccess ) {
    echo json_encode([
        "text" => "Action '$route' was completed successfully",
        "success" => true
    ]);
} else {
    echo json_encode([
        "text" => "Malformed action '$route' was posted to ".__FILE__,
        "success" => false
    ]);
}

?>