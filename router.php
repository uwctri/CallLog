<?php
# This page handles all GET/POST requests from scripts and internally from the EM
$module = new \UWMadison\CustomCallLog\CustomCallLog();
$route = $_POST['route'] ?? $_GET['route'];
$pid = $_GET['pid'];

$sendSuccess = False;
$sendDone = False;
$sendMalformed = True;

switch ( $route ) {
    case "newAdhoc":
        # This page is intended to be posted to by an outside script or DET to make a singe new adhoc call on a record
        # url: ExternalModules/?prefix=CTRI_Custom_CallLog&page=newAdhoc&pid=NNN&adhocCode=NNN&record=NNN&type=NNN&fudate=NNN&futime=NNN&reporter=NAME
        $module->metadataAdhoc( $pid, $_GET['record'], [
            'id' => $_GET['type'],
            'date' => $_GET['fudate'],
            'time' => $_GET['futime'],
            'reason' => $_GET['adhocCode'],
            'reporter' => $_GET['reporter']
        ]);
        $sendDone = True;
        break;
    case "adhocLoad":
        # Posted to by the call log to save a new adhoc call
        # It should not be posted to from any other source
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
        # Intended to be posted to by an outside script or DET to resolve an existing adhoc call on a record(s)
        # url: /ExternalModules/?prefix=CTRI_Custom_CallLog&page=router&route=adhocResolve&pid=NNN&adhocCode=NNN&recordList=NNN
        foreach ( explode(',',$_GET['recordList']) as $record ) {
            $module->resolveAdhoc($pid,trim($record),$_GET['adhocCode']);
        }
        $sendDone = True;
        break;
    case "calldataSave":
        # Posted to by the call log to save the record's data via the console.
        # This is useful for debugging and resolving enduser issues.
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
        # Posted to by the call log to delete the last saved instance of the call log instrument.
        $record = $_POST['record'];
        if( !empty($pid) && !empty($record) ) {
            $module->deleteLastCallInstance($pid, $record);
            $sendDone = True;
        }
        break;
    case "metadataSave":
        # Posted to by the call log to save the record's data via the console.
        # This is useful for debugging and resolving enduser issues.
        $record = $_POST['record'];
        if( !empty($pid) && !empty($record) ) {
            $module->saveCallMetadata( $pid, $record, json_decode($_POST['metadata'], true) );
            $sendDone = True;
        }
        break;
    case "newEntryLoad":
        # This page is intended to be posted to by an outside script to load New Entry calls for any number of records
        # url: /ExternalModules/?prefix=CTRI_Custom_CallLog&page=newEntryLoad&pid=NNN&recordList=NNN
        foreach ( explode(',',$_GET['recordList']) as $record ) {
            $module->metadataNewEntry($pid,trim($record));
        }
        $sendDone = True;
        break;
    case "scheduleLoad":
        # This page is intended to be posted to by an outside script after scheduling occurs. 
        # url: /ExternalModules/?prefix=CTRI_Custom_CallLog&page=scheduleLoad&pid=NNN&recordList=NNN
        foreach ( explode(',',$_GET['recordList']) as $record ) {
            $module->metadataReminder($pid,trim($record));
            $module->metadataMissedCancelled($pid,trim($record));
            $module->metadataNeedToSchedule($pid,trim($record));
        }
        $sendDone = True;
        break;
    case "setCallEnded":
        # This page is posted to by the call list to flag a call as no longer in progress
        $record = $_POST['record'];
        $call_id = $_POST['id'];
        if( !empty($pid) && !empty($record) && !empty($call_id) ) {
            $module->metadataCallEnded($pid, $record, $call_id);
            $sendDone = True;
        } 
        break;
    case "setCallStarted":
        # This page is posted to by the call list to flag a call as in progress
        $record = $_POST['record'];
        $call_id = $_POST['id'];
        $user = $_POST['user'];
        if( !empty($pid) && !empty($record) && !empty($call_id) && !empty($user) ) {
            $module->metadataCallStarted($pid, $record, $call_id, $user);
            $sendDone = True;
        } 
        break;
    case "setNoCallsToday":
        # This page is posted to by the call list to flag a call as "no calls today"
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