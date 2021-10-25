<?php
# This page handles all GET/POST requests from scripts and internally from the EM
$module = new \UWMadison\CallLog\CallLog();
$route = $_POST['route'] ?? $_GET['route'];
$record = $_POST['record'] ?? $_GET['record'] ?? $_GET['recordList'];
$pid = $_GET['pid'];

$sendSuccess = False;
$sendDone = False;

if ( empty($pid) || (empty($record) && $route != "dataLoad") || empty($route) ) {
    echo json_encode([
        "text" => "Malformed action missing PID ('$pid'), record ('$record'), or route ('$route') was posted to ".__FILE__,
        "success" => false
    ]);
    return;
}

switch ( $route ) {
    case "dataLoad":
        // Load for the Call List
        $data = $module->loadCallListData();
        if ( !empty($data) )
            $sendSuccess = True;
        break;
    case "log":
        $module->projectLog();
        $sendDone = True;
        break;
    case "newAdhoc":
        # Intended to be posted to by an outside script or DET to make a singe new adhoc call on a record
        # url: ExternalModules/?prefix=call_log&page=router&route=newAdhoc&pid=NNN&adhocCode=NNN&record=NNN&type=NNN&fudate=NNN&futime=NNN&reporter=NAME
        # Identical to adhocLoad but via GET, seperated for possible future changes
        if ( !empty($_POST['type']) ) {
            $module->metadataAdhoc( $pid, $record, [
                'id' => $_GET['type'],
                'date' => $_GET['fudate'],
                'time' => $_GET['futime'],
                'reason' => $_GET['adhocCode'],
                'reporter' => $_GET['reporter']
            ]);
            $sendDone = True;
        }
        break;
    case "adhocLoad":
        # Posted to by the call log to save a new adhoc call
        if( !empty($_POST['id']) ) {
            $module->metadataAdhoc( $pid, $record, [
                'id' => $_POST['id'],
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
        # url: /ExternalModules/?prefix=call_log&page=router&route=adhocResolve&pid=NNN&adhocCode=NNN&recordList=NNN
        if ( !empty($_GET['adhocCode']) ) {
            foreach ( explode(',',$record) as $rcrd ) {
                $module->resolveAdhoc($pid,trim($rcrd),$_GET['adhocCode']);
            }
            $sendDone = True;
        }
        break;
    case "calldataSave":
        # Posted to by the call log to save the record's data via the console.
        # This is useful for debugging and resolving enduser issues.
        $instance = $_POST['instance'];
        $var = $_POST['dataVar'];
        $val = $_POST['dataVal'];
        if (json_decode($_POST['isCheckbox']))
            $val = json_decode($val,true);
        if( !empty($instance) && !empty($var) && !is_null($val) ) {
            $module->saveCallData($pid, $record, $instance, $var, $val);
            $sendDone = True;
        }
        break;
    case "callDelete":
        # Posted to by the call log to delete the last saved instance of the call log instrument.
        $module->deleteLastCallInstance($pid, $record);
        $sendDone = True;
        break;
    case "metadataSave":
        # Posted to by the call log to save the record's data via the console.
        # This is useful for debugging and resolving enduser issues.
        if( !empty($_POST['metadata']) ) {
            $module->saveCallMetadata( $pid, $record, json_decode($_POST['metadata'], true) );
            $sendDone = True;
        }
        break;
    case "newEntryLoad":
        # This page is intended to be posted to by an outside script to load New Entry calls for any number of records
        # url: /ExternalModules/?prefix=call_log&page=router&route=newEntryLoad&pid=NNN&recordList=NNN
        foreach ( explode(',',$record) as $rcrd ) {
            $module->metadataNewEntry($pid,trim($rcrd));
        }
        $sendDone = True;
        break;
    case "scheduleLoad":
        # This page is intended to be posted to by an outside script after scheduling occurs. 
        # url: /ExternalModules/?prefix=call_log&page=router&route=scheduleLoad&pid=NNN&recordList=NNN
        foreach ( explode(',',$record) as $rcrd ) {
            $module->metadataReminder($pid,trim($rcrd));
            $module->metadataMissedCancelled($pid,trim($rcrd));
            $module->metadataNeedToSchedule($pid,trim($rcrd));
        }
        $sendDone = True;
        break;
    case "setCallEnded":
        # This page is posted to by the call list to flag a call as no longer in progress
        if( !empty($_POST['id']) ) {
            $module->metadataCallEnded($pid, $record, $_POST['id']);
            $sendDone = True;
        } 
        break;
    case "setCallStarted":
        # This page is posted to by the call list to flag a call as in progress
        if( !empty($_POST['id']) && !empty($_POST['user']) ) {
            $module->metadataCallStarted($pid, $record, $_POST['id'], $_POST['user']);
            $sendDone = True;
        } 
        break;
    case "setNoCallsToday":
        # This page is posted to by the call list to flag a call as "no calls today"
        if( !empty($_POST['id']) ) {
            $module->metadataNoCallsToday($pid, $record, $_POST['id']);
            $sendDone = True;
        } 
        break;
}

if ( $sendDone ) {
    $result = [
        "text" => "Done",
        "success" => false
    ];
} elseif ( $sendSuccess ) {
    $result = [
        "text" => "Action '$route' was completed successfully",
        "success" => true
    ];
} else {
    $result = [
        "text" => "Malformed action '$route' was posted to ".__FILE__,
        "success" => false
    ];
}

if ( !empty($data) ) {
    $result['data'] = $data;
}

echo json_encode($result);

?>