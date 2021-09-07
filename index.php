<?php
/////////////////////////////////////////////////
// Post Check
/////////////////////////////////////////////////
if( $_POST['reloadData'] ) {
    echo json_encode(loadParsePackCallData());
    return;
}

$startTime = microtime(true);

/////////////////////////////////////////////////
// All Functions
/////////////////////////////////////////////////

function printToScreen($string) {
    ?><script>console.log(<?=json_encode($string); ?>);</script><?php
}

function isNotBlank($string) {
    return $string != "";
}

function loadParsePackCallData($skipDataPack = false) {
    $startTime = microtime(true);
    global $project_id,$module;

    // Issue reporting array
    $issues = [];
    
    // Event IDs
    $callEvent = $module->getProjectSetting("call_log_event");
    $metaEvent = $module->getProjectSetting("metadata_event");
    
    // Withdraw Conditon Config
    $withdraw = [
        'event' => $module->getProjectSetting("withdraw_event"),
        'var' => $module->getProjectSetting("withdraw_var"),
        'tmp' => [
            'event' => $module->getProjectSetting("withdraw_tmp_event"),
            'var' => $module->getProjectSetting("withdraw_tmp_var")
        ]
    ];
    
    // MCV and Scheduled Vists Config for Live Data
    $autoRemoveConfig = $module->loadAutoRemoveConfig();
    
    // Large Configs
    $tabs = $module->loadTabConfig();
    $adhoc = $module->loadAdhocTemplateConfig();
    
    if( !$_POST['reloadData'] ) { 
        printToScreen('Config Loaded in '. round((microtime(true)-$startTime),5) .' seconds');
        $startTime = microtime(true);
    }
    
    // Minor Prep
    $packagedCallData = [];
    $alwaysShowCallbackCol = false;
    $today = Date('Y-m-d');
    foreach( $tabs['config'] as $tab )
        $packagedCallData[$tab["tab_id"]] = [];
    
    // Construct the needed feilds (This saves almost no time currently)
    $fields = array_merge([REDCap::getRecordIdField(), $module->metadataField, $withdraw['var'], $withdraw['tmp']['var'], 
    'call_open_date', 'call_left_message', 'call_requested_callback', 'call_notes', 'call_open_datetime', 'call_open_user_full_name', 'call_attempt', 'call_template', 'call_event_name', 'call_callback_date'], 
    array_values($autoRemoveConfig[$callID]), $tabs['allFields']); 
    
    
    // Main Loop
    $records = $skipDataPack ? '-1' : null;
    $dataLoad = REDCap::getData($project_id,'array', $records, $fields);
    foreach( $dataLoad as $record => $recordData ) {
        
        // Check if the dag is empty or if it matches the User's DAG
        if ( !$module->isInDAG($record) )
            continue;
        
        $meta = json_decode($recordData[$metaEvent][$module->metadataField],true);
        
        // Check if withdrawn or tmp withdrawn
        if ( $recordData[$withdraw['event']][$withdraw['var']] )
            continue; 
        if ( $recordData[$withdraw['tmp']['event']][$withdraw['tmp']['var']] && $recordData[$withdraw['tmp']['event']][$withdraw['tmp']['var']]<$today )
            continue;
        
        foreach( $meta as $callID => $call ) {
            $fullCallID = $callID;
            $callID = explode('|',$callID)[0]; // We only need the simple ID here
            
            // Skip if call complete, debug call, or if call ID isn't assigned to a tab
            if ( $call['complete'] || substr($callID,0,1) == '_' || empty($tabs['call2tabMap'][$callID]) )
                continue;
            
            // Skip when reminders, followups, adhocs aren't in window
            if ( ($call['template'] == 'reminder' || $call['template'] == 'followup' ) && ($call['start'] > $today) )
                continue;
            
            // Skip reminder calls day-of or future
            if ( ($call['template'] == 'reminder') && ($call['end'] <= $today) )
                continue;
            
            // Skip followups that are flagged for auto remove and are out of window (after the last day)
            if ( ($call['template'] == 'followup') && $call['autoRemove'] && ($call['end'] < $today) )
                continue;
            
            // Skip New (onload) calls that have expire days
            if ( ($call['template'] == 'new') && $call['expire'] && (date('Y-m-d', strtotime($call['load']."+".$call['expire']." days")) < $today) )
                continue;
            
            $instanceData = $recordData['repeat_instances'][$callEvent][$module->instrumentLower][end($call['instances'])]; // This could be empty for New Entry calls, but it won't matter.
            $instanceEventData = $recordData[$call['event_id']];
            $instanceData = array_merge( array_filter( empty($instanceEventData) ? [] : $instanceEventData, 'isNotBlank' ), array_filter($recordData[$callEvent],'isNotBlank'), array_filter( empty($instanceData) ? [] : $instanceData, 'isNotBlank' ));
            
            // Skip MCV calls if past the autoremove date. Need Instance data
            if ( ($call['template'] == 'mcv') && $autoRemoveConfig[$callID] && $instanceData[$autoRemoveConfig[$callID]] &&( $instanceData[$autoRemoveConfig[$callID]] < $today) )
                continue;
                
            // Skip Scheduled Visit calls if past the autoremove date. Need Instance data
            if ( ($call['template'] == 'visit') && $autoRemoveConfig[$callID] && $instanceData[$autoRemoveConfig[$callID]] &&( $instanceData[$autoRemoveConfig[$callID]] < $today) )
                continue;
            
            // Check if the call was recently opened
            $instanceData['_callStarted'] = strtotime($call['callStarted']) > strtotime('-'.$module->startedCallGrace.' minutes');
            
            // Check if No Calls Today flag is set
            if ( $call['noCallsToday'] == $today )
                $instanceData['_noCallsToday'] = true;
            
            // Check if we are at max call attempts for the day
            // While we are at it, assemble all of the note data too
            $attempts = $recordData[$callEvent]['call_open_date'] == $today ? 1 : 0;
            $instanceData['_callNotes'] = "";
            foreach( array_reverse($call['instances']) as $instance ) {
                $itterData = $recordData['repeat_instances'][$callEvent][$module->instrumentLower][$instance];
                $leftMsg = $itterData['call_left_message'][1] == "1" ? '<b>Left Message</b>' : '';
                $setCB = $itterData['call_requested_callback'][1] == "1" ? 'Set Callback' : '';
                $text = $leftMsg && $setCB ? $leftMsg." & ".$setCB : $leftMsg.$setCB.'&nbsp;';
                $notes = $itterData['call_notes'] ? $itterData['call_notes'] : 'none';
                $instanceData['_callNotes'] .= $itterData['call_open_datetime'].'||'.$itterData['call_open_user_full_name'].'||'.$text.'||'.$notes.'|||';
                if ( $itterData['call_open_date'] == $today )
                    $attempts++;
            }
            $instanceData['_atMaxAttempts'] = $call['hideAfterAttempt'] <= $attempts;
            $instanceData['call_attempt'] = count($call['instances']); // For displaying the number of past attempts on log
            
            // Add what the next instance should be for possible links
            $instanceData['_nextInstance'] = 1;
            if ( !empty($recordData['repeat_instances'][$callEvent][$module->instrumentLower]) )
                $instanceData['_nextInstance'] = end(array_keys($recordData['repeat_instances'][$callEvent][$module->instrumentLower]))+1;
            else if ( !empty($recordData[$callEvent]['call_template']) )
                $instanceData['_nextInstance'] = 2;
            
            // Add event_id for possible link to instruments
            $instanceData['_event'] = $call['event_id'];
            
            // Add the Event's name for possible display (only used by MCV?)
            $instanceData['call_event_name'] = $call['event'];
            
            // Check to see if a call back was request for tomorrow+, set global if any Callback will be shown
            $instanceData['_callbackNotToday'] = ($instanceData['call_requested_callback'][1] == '1' && $instanceData['call_callback_date'] > $today);
            $instanceData['_callbackToday'] = ($instanceData['call_requested_callback'][1] == '1' && $instanceData['call_callback_date'] <= $today);
            $alwaysShowCallbackCol = $alwaysShowCallbackCol ? true : ($instanceData['call_requested_callback'][1] == '1' && $instanceData['call_callback_date'] <= $today);
            
            // Add lower and upper windows (data is on reminders too but isn't displayed now)
            if ( $call['template'] == 'followup' ) { 
                $instanceData['_windowLower'] = $call['start'];
                $instanceData['_windowUpper'] = $call['end'];
            }
            
            if ( $call['template'] == 'mcv' ) {
                $instanceData['_appt_dt'] = $call['appt'];
            }
            
            // Adhoc call time and reason
            if ( $call['template'] == 'adhoc' ) {
                $instanceData['_adhocReason'] = $adhoc['config'][$callID]['reasons'][$call['reason']];
                $instanceData['_adhocContactOn'] = $call['contactOn'];
                $notes = $call['initNotes'] ?  $call['initNotes'] : "No Notes Taken";
                if ( $call['reporter'] != "" ) 
                    $instanceData['_callNotes'] .= $call['reported'].'||'.$call['reporter'].'||'.'&nbsp;'.'||'.$notes.'|||';
            }
            
            // Make sure we 100% have a call ID (first attempt at a call won't get it from the normal data)
            $instanceData['_call_id'] = $fullCallID;
            
            if ( !$instanceData['record_id'] ) {
                $issues[] = $record . ' - ' . $callID . ' has a call without a record id. Poor save from call log.';
                continue;
            }
            
            // Pack data - done
            $packagedCallData[$tabs['call2tabMap'][$callID]][] = $instanceData;
        }
    }
    return array($packagedCallData, $tabs, $alwaysShowCallbackCol, round(((microtime(true)-$startTime)),5), $issues);
}

/////////////////////////////////////////////////
// Page Load
/////////////////////////////////////////////////

// Libraries
$module->includeDataTables();
$module->includeCss('css/list.css');

// Load, parse, and pack the Call Data for display
list($packagedCallData, $tabs, $alwaysShowCallbackCol, $timeTaken, $issues) = loadParsePackCallData(true);
if ( count($issues) )
    printToScreen('Issues encountered: ' . json_encode($issues));
?>

<div class="projhdr"><i class="fas fa-phone"></i> Call List</div>

<div class="card" style="display:none">
    <?php if( count($tabs['config']) > 1) {?>
    <div class="card-header tab-header">
        <ul class="nav nav-tabs card-header-tabs">
            <?php foreach( $tabs['config'] as $tab) {?>
            <li class="nav-item call-tab">
                <a class="nav-link call-link" data-toggle="tab" data-tabid="<?php echo $tab['tab_id'] ?>" href="#<?php echo $tab['tab_id'] ?>"><?php echo $tab['tab_name'] ?></a>
            </li>
            <?php } ?>
        </ul>
    </div>
    <div class="tab-content">
    <?php } ?>
    <?php foreach( $tabs['config'] as $tab_index => $tab) {?>
        <div id="<?php echo $tab["tab_id"] ?>" class="tab-pane">
            <div class="card-header">
                <div class="row header-row">
                    <div class="col-6">
                        <h4><?php echo $tab["tab_name"] ?></h4>
                    </div>
                    <div class="col-6">
                        <button type="button" class="btn btn-light btn-sm toggleHiddenCalls">Toggle Hidden Calls</button>
                    </div>
                </div>
                <?php if( !empty($tab["description"]) ) {?>
                <div class="row">
                    <div class="col">
                        <h6 class="mb-0">{$tab["description"]}</h6>
                    </div>
                </div>
                <?php } ?>
            </div>
            <div class="card-body table-responsive">
                <div class="row fit">
                    <div class="col">
                        <table class="callTable" style="width:100%">
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
    <?php if( count($tabs['config']) > 1) {?>
    </div>
    <?php } ?>
</div>

<script>
    CTRICallLog.packagedCallData = <?php echo json_encode($packagedCallData); ?>;
    CTRICallLog.tabs = <?php echo json_encode($tabs); ?>;
    CTRICallLog.alwaysShowCallbackCol = <?php echo json_encode($alwaysShowCallbackCol); ?>;
    CTRICallLog.reloadDataPOST = <?php echo json_encode($module->getURL(__FILE__)); ?>;
    CTRICallLog.hideCalls = true;
    CTRICallLog.childRows = {};
    CTRICallLog.colConfig = {};
    CTRICallLog.displayedData = {};
    
    function projectLog( action, call_id, record ) {
        if (typeof ez !== "undefined") {
            ez.loge(action, "Call ID = "+call_id, record, "", pid);
        }
    }
    
    function childRowFormat( record, call_id, callStarted, childData, notesData, tab ) {
        notesData = notesData.split('|||').map(x=>x.split('||')).filter(x=>x.length>2);
        return '<div class="container">'+
            '<div class="row">'+
                '<div class="col-4">'+
                    '<div class="row dtChildData">'+
                        '<div class="col-auto">'+
                            CTRICallLog.childRows[tab]+
                        '</div>'+
                        '<div class="col">'+
                            childData.map(x=>'<div class="row">'+(x||"________")+'</div>').join('')+
                        '</div>'+
                    '</div>'+
                    '<div class="row">'+
                        '<div class="col">'+
                            '<div class="row">'+
                                '<a class="noCallsButton" onclick="noCallsToday('+record+',\''+call_id+'\')">No Calls Today</a>'+
                                ( !callStarted ? '' :
                                '&emsp;<a class="endCallButton" onclick="endCall('+record+',\''+call_id+'\')">End Current Call</a>')+
                            '</div>'+
                        '</div>'+
                    '</div>'+
                '</div>'+
                '<div class="col-8 border-left">'+
                    '<div class="row dtChildNotes">'+
                        '<div class="col">'+
                            (notesData.map(x=>
                            '<div class="row m-2 pb-2 border-bottom">'+
                                '<div class="col-auto">'+
                                    '<div class="row">'+formatDate(new Date(x[0].split(' ')[0]+"T00:00:00"),CTRICallLog.defaultDateFormat)+" "+conv24to12(x[0].split(' ')[1])+'</div>'+
                                    '<div class="row">'+x[1]+'</div>'+
                                    '<div class="row">'+x[2]+'</div>'+
                                '</div>'+
                                '<div class="col">'+
                                    '<div class="row ml-1">'+(x[3]=="none"?"No Notes Taken":x[3])+'</div>'+
                                '</div>'+
                            '</div>').join('')||'<div class="text-center mt-4">Call history will display here</div>')+
                        '</div>'+
                    '</div>'+
                '</div>'+
            '</div>'+
        '</div>';
    }
    
    function createColConfig(index, tab_id) {
        
        let cols = [{
            title: '',
            data: '_callStarted',
            bSortable: false,
            className: 'callStarted',
            render: (data,type,row,meta) => data ? 
                '<span style="font-size:2em;color:#dc3545;">'+
                '<i class="fas fa-phone-square-alt" data-toggle="tooltip" data-placement="left" '+
                'title="This subject may already be in a call."></i></span>' : ''
        }];
        
        $.each( CTRICallLog.tabs['config'][index]['fields'], function(colIndex,fConfig) {
            
            // Standard Config for all fields
            let colConfig = {
                data: fConfig.field,
                title: fConfig.displayName,
                render: (data,type,row,meta) => data || fConfig.default,
                defaultContent: ""
            }
            
            if ( colIndex == 0 )
                colConfig['className'] = 'firstDataCol';
            
            // Check for Validation on the feild
            const dateFormats = ['MM-dd-y','y-MM-dd','dd-MM-y'];
            let fdate = dateFormats[['_mdy','_ymd','_dmy'].map(x=>fConfig.validation.includes(x)).indexOf(true)];
            if ( fdate ) {
                colConfig.render = function ( data, type, row, meta ) {
                    if ( !data )
                        return fConfig.default;
                    if ( type === 'display' || type === 'filter' ) {
                        let [date, time] = data.split(' ');
                        let ftime = time ? ' hh:mm' : '';
                        let fsec = time && time.length == 8 ? ':ss' : '';
                        let fmer = time ? 'a' : '';
                        time = time || '00:00:00';
                        return formatDate(new Date( date +'T' + time), fdate+ftime+fsec+fmer).toLowerCase();
                    } else {
                        return data;
                    }
                }
            } else if ( fConfig.validation == 'time' ) {
                colConfig.render = (data,type,row,meta) => conv24to12(data) || fConfig.default;
            } else if ( ["radio","select"].includes(fConfig.fieldType) ){
                colConfig.render = (data,type,row,meta) => fConfig.map[data] || fConfig.default;
            } else if ( ["yesno","truefalse"].includes(fConfig.fieldType) ){
                let map = fConfig.fieldType == 'truefalse' ? ['False','True'] : ['No','Yes'];
                colConfig.render = (data,type,row,meta) => map[data] || fConfig.default;
            } else if ( fConfig.fieldType == "checkbox" ) {
                colConfig.render = (data,type,row,meta) => typeof data == "object" ? 
                    Object.keys(Object.filter(data,x=>x=="1")).map(x=>fConfig.map[x]).join(', ') || fConfig.default : fConfig.default;
            } else if ( fConfig.isFormStatus ) {
                colConfig.render = (data,type,row,meta) => ['Incomplete','Unverified','Complete'][data];
            } else if ( colConfig.data == "call_event_name" ) {
                colConfig.render = (data,type,row,meta) => CTRICallLog.eventNameMap[data] || "";
            } else if ( fConfig.validation == 'phone' ) {
                colConfig.render = (data,type,row,meta) => (data && (type === 'filter')) ? data.replace(/[\\(\\)\\-\s]/g,'') : data || "";
            } else if ( Object.keys(CTRICallLog.usernameLists).includes(fConfig.field) ) {
                colConfig.render = (data,type,row,meta) => data ? data.includes($("#username-reference").text()) ? CTRICallLog.usernameLists[fConfig.field]['include'] : CTRICallLog.usernameLists[fConfig.field]['exclude'] : "";
            }
            
            // Build out any links
            if ( fConfig.link != "none" ) {
                let url;
                if (fConfig.link == "home")
                    url = '../DataEntry/record_home.php?pid='+pid+'&id=RECORD';
                else if (fConfig.link == "call")
                    url = '../DataEntry/index.php?pid='+pid+'&id=RECORD&event_id='+CTRICallLog.events.callLog.id+'&page='+CTRICallLog.static.instrumentLower+'&instance=INSTANCE&call_id=CALLID&showReturn=1';
                else if (fConfig.link == "instrument")
                    url = '../DataEntry/index.php?pid='+pid+'&id=RECORD&event_id='+fConfig.linkedEvent+'&page='+fConfig.linkedInstrument;
                colConfig.createdCell = function (td, cellData, rowData, row, col) {
                    let thisURL = url.replace('RECORD',rowData[CTRICallLog.static.record_id]).
                        replace('INSTANCE',rowData['_nextInstance']).
                        replace('CALLID',rowData['_call_id']);
                    let dt = "";
                    if ( rowData['call_callback_date'] && rowData['call_callback_time'] ) {
                        dt = rowData['call_callback_date']+" "+rowData['call_callback_time'];
                    } else if ( rowData['call_callback_date'] ) {
                        dt = rowData['call_callback_date']+" 00:00:00";
                    } else if ( rowData['call_callback_time'] ) {
                        dt = today+" "+rowData['call_callback_time'];
                    }
                    let record = rowData[CTRICallLog.static.record_id];
                    let id = rowData['_call_id'];
                    $(td).html("<a onclick=\"callURLclick("+record+",'"+id+"','"+thisURL+"','"+dt+"')\">"+cellData+"</a>");
                }
            }
            
            // Hide Cols that are for expansion only
            if ( fConfig.expanded ) {
                colConfig.visible = false;
                colConfig.className = 'expandedInfo';
                CTRICallLog.childRows[tab_id] += '<div class="row">'+fConfig.displayName+'</div>';
            }
            
            //Done
            cols.push(colConfig)
        });
        
        // Tack on Lower and Upper windows for Follow ups
        if ( CTRICallLog.tabs['config'][index]['showFollowupWindows'] ) {
            cols.push({title: 'Start Calling',data: '_windowLower'});
            cols.push({title: 'Complete By',data: '_windowUpper'});
        }
        
        // Tack on Missed Appt date
        if ( CTRICallLog.tabs['config'][index]['showMissedDateTime'] ) {
            cols.push({title: 'Missed Date',data: '_appt_dt', render: (data,type,row,meta) =>
                ( type === 'display' || type === 'filter' ) ? formatDate(new Date(data),CTRICallLog.defaultDateTimeFormat).toLowerCase() || "Not Specified" : data || "Not Specified"
            });
        }
        
        // Tack on Adhoc call info
        if ( CTRICallLog.tabs['config'][index]['showAdhocDates'] ) {
            cols.push({title: 'Reason',data: '_adhocReason'});
            cols.push({title: 'Call on',data: '_adhocContactOn', render: function (data,type,row,meta) {
                if ( type === 'display' || type === 'filter' ) {
                    let format =  data.length <= 10 ? CTRICallLog.defaultDateFormat : CTRICallLog.defaultDateTimeFormat;
                    data = data.length <= 10 ? data + "T00:00" : data;
                    return formatDate(new Date(data),format).toLowerCase() || "Not Specified";
                } else {
                    return data;
                }
            }});
        }
        
        // Tack on Cols for the Call Notes generation
        cols.push({
            title: 'Call Note HTML',
            data: '_callNotes',
            visible: false
        })
        
        // Tack on the Call Back and Info Col
        // Note: THIS MUST BE THE LAST COL
        cols.push({
            title: 'Call Back & Info',
            name: 'callbackCol',
            className: 'callbackCol',
            render: function (data, type, row, meta) {
                let displayDate = '';
                if ( row['call_requested_callback'] && row['call_requested_callback'][1] == '1' ) {
                    if ( row['call_callback_date'] )
                        displayDate += formatDate(new Date(row['call_callback_date']+'T00:00:00'), CTRICallLog.defaultDateFormat)+" ";
                    displayDate += conv24to12(row['call_callback_time']);
                    if (!displayDate)
                        displayDate = "Not specified";
                }
                let requestedBy = row['call_callback_requested_by'] ? row['call_callback_requested_by'] == '1' ? 'Participant' : 'Staff' : ' ';
                if ( type === 'display'  ) {
                    let display = '';
                    if ( row['_noCallsToday'] )
                        display += '<i class="fas fa-info-circle float-left infocircle" data-toggle="tooltip" data-placement="left" title="A provider requested that this subject not be contacted today."></i>';
                    if ( row['_atMaxAttempts'] )
                        display += '<i class="fas fa-info-circle float-left infocircle" data-toggle="tooltip" data-placement="left" title="This subject has been called the maximum number of times today."></i>';
                    if ( displayDate )
                        display += '<i class="fas fa-stopwatch mr-1" data-toggle="tooltip" data-placement="left" title="Subject\'s requested callback time"></i> '+displayDate+
                            ' <span class="callbackRequestor" data-toggle="tooltip" data-placement="left" title="Callback set by '+requestedBy+'">'+requestedBy[0]+'</span>';
                    return display;
                } 
                else if ( type === 'filter' ) {
                    if ( displayDate )
                        return displayDate;
                }
                else {
                    if ( displayDate )
                        return row['call_callback_date']+" "+row['call_callback_time'];
                }
                return '';
            }
        });
        
        return cols;
    }
    
    function callURLclick( record, call_id, url, callbackDateTime ) {
        event.stopPropagation();
        if (callbackDateTime && ((new Date(now) - new Date(callbackDateTime)) < (-5*1000*60))) {
            Swal.fire({
                title: 'Calling Early?',
                text: "This subject has a callback scheduled, you may not want to call them now.",
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#337ab7',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Continue'
            }).then((result) => {
              if (result.isConfirmed)
                  startCall(record, call_id, url);
            })
        } else {
            startCall(record, call_id, url);
        }
    }
    
    function startCall(record, call_id, url) {
        projectLog("Started Call", call_id, record);
        $.ajax({
            method: 'POST',
            url: CTRICallLog.callStartedPOST,
            data: {
                record: record,
                id: call_id,
                user: $("#username-reference").text()
            },
            error: (jqXHR, textStatus, errorThrown) => console.log(textStatus + " " +errorThrown),
            success: (data) => window.location = url
        });
    }
    
    function endCall(record, call_id) {
        $.ajax({
            method: 'POST',
            url: CTRICallLog.callEndedPOST,
            data: {
                record: record,
                id: call_id
            },
            error: (jqXHR, textStatus, errorThrown) => console.log(textStatus + " " +errorThrown),
            success: (data) => { 
                projectLog("Manually Ended Call", call_id, record);
                console.log('Call ended. Refreshing table data.');
                refreshTableData()
            }
        });
    }
    
    function toggleHiddenCalls() {
        CTRICallLog.hideCalls = !CTRICallLog.hideCalls;
        toggleCallBackCol();
        $('*[data-toggle="tooltip"]').tooltip();//Enable Tooltips for the info icon
    }
    
    function toggleCallBackCol() {
        $.each( $('.callTable'), function() {
            $(this).DataTable().column( 'callbackCol:name' ).visible(CTRICallLog.alwaysShowCallbackCol || !CTRICallLog.hideCalls);
            $(this).DataTable().draw();
        });
    }
    
    function refreshTableData() {
        $.ajax({
            method: 'POST',
            url: CTRICallLog.reloadDataPOST,
            data: {reloadData: true},
            error: (jqXHR, textStatus, errorThrown) => console.log(textStatus + " " +errorThrown),
            success: (data) => {
                let [ packagedCallData, tabConfig, alwaysShowCallbackCol, timeTaken, issues ] = JSON.parse(data);
                CTRICallLog.packagedCallData = packagedCallData;
                CTRICallLog.alwaysShowCallbackCol = alwaysShowCallbackCol;
                $('.callTable').each( function(index,el) {
                    let table = $(el).DataTable();
                    let page = table.page.info().page;
                    let tab_id = $(el).closest('.tab-pane').prop('id');
                    table.clear();
                    table.rows.add(CTRICallLog.packagedCallData[tab_id]);
                    if ( CTRICallLog.alwaysShowCallbackCol && ArraysEqual(table.order()[0],[ 1, "asc" ]) )
                        table.order( [[ CTRICallLog.colConfig[tab_id].length-1, "desc" ]] );
                    table.draw();
                    table.page(page).draw('page');
                });
                toggleCallBackCol();
                console.log('Refreshed data in '+timeTaken+' seconds');
            }
        });
    }
    
    function noCallsToday(record, call_id) {
        $.ajax({
            method: 'POST',
            url: CTRICallLog.noCallsTodayPOST,
            data: {
                record: record,
                id: call_id
            },
            error: (jqXHR, textStatus, errorThrown) => console.log(textStatus + " " +errorThrown),
            success: (data) => refreshTableData()
        });
    }
    
    $(document).ready(function() {
        
        // Custom search options
        $('.card-body').on('input propertychange paste','.customSearch', function() {
            console.log('new search hit');
            let $table = $('.callTable:visible').DataTable();
            let query = $('.card-body:visible input').val();
            if ( query.split(' ')[0] == 'regex' )
                $table.search(query.replace('regex ',''),true,false).draw();
            else if ( query[0] == '!' )
                $table.search('^(?!.*'+query.slice(1)+')',true,false).draw();
            else
                $table.search(query,false,true).draw();
        });
        
        // Control display of Calls
        $.fn.dataTable.ext.search.push(
            function(settings, searchData, index, rowData, counter) {
                return !(
                    CTRICallLog.hideCalls && (
                        (rowData['_atMaxAttempts'] && !rowData['_callbackToday']) || rowData['_callbackNotToday'] || rowData['_noCallsToday']
                    )
                );
            }
        );
        $(".toggleHiddenCalls").on('click', toggleHiddenCalls); // Control all toggles at once
        
        // Main table build out
        $('.callTable').each( function(index,el) {
            let tab_id = $(el).closest('.tab-pane').prop('id');
            CTRICallLog.childRows[tab_id] = "";
            CTRICallLog.colConfig[tab_id] = createColConfig(index, tab_id);
            
            // Init the table
            let defaultOrder = CTRICallLog.alwaysShowCallbackCol ? [[ CTRICallLog.colConfig[tab_id].length-1, "desc" ]] : [[ 1, "asc" ]];
            $(el).DataTable({
                lengthMenu: [ [25,50,100,-1], [25,50,100, "All"] ],
                columns: CTRICallLog.colConfig[tab_id],
                order: defaultOrder,
                createdRow: (row,data,index) => $(row).addClass('dataTablesRow'),
                data: CTRICallLog.packagedCallData[tab_id],
                sDom: 'ltpi'
            });
            
            // Create a data object for reports to access and for below
            //let visibleCols = CTRICallLog.colConfig[tab_id].map(x=>x['visible']!=false || (x['data'] && x['data'].includes('callback')) ? x['title']: null).filter(x=>x&&!x.startsWith('_'));
            //let visibleColsIndex = CTRICallLog.colConfig[tab_id].map((e,i)=>e['visible']!=false || (e['data'] && e['data'].includes('callback')) ? i: null).filter(x=>x);
            //CTRICallLog.displayedData[tab_id] = [];
            //$(el).DataTable().rows().every( function() {
            //    let values = this.cells().render('display').toArray().filter((e,i)=>visibleColsIndex.includes(i));
            //    console.log(this.cells().render('display').toArray())
            //    let merger = visibleCols.reduce((obj, keys, index) => ({ ...obj, [keys]: values[index] }), {})
            //    CTRICallLog.displayedData[tab_id].push( merger );
            //});
            let visibleCols = CTRICallLog.colConfig[tab_id].map(x=>x['visible']!=false ? x['data']: null).filter(x=>x&&!x.startsWith('_'));
            visibleCols = visibleCols.concat( CTRICallLog.packagedCallData[tab_id].length > 0 ? Object.keys(CTRICallLog.packagedCallData[tab_id][0]).filter(x=>x.includes('callback')&&!x.startsWith('_')) : [])
            CTRICallLog.displayedData[tab_id] = $(el).DataTable().rows().data().toArray().map( x=> Object.filterKeys(x, visibleCols));
            
            // Create tab badges
            let badge = 0;
            let user = $("#impersonate-user-select").val() || CTRICallLog.user;
            CTRICallLog.displayedData[tab_id].forEach( x=>Object.values(x).includes(CTRICallLog.userNameMap[user]) && badge++ );
            if ( badge > 0 && CTRICallLog.tabs.showBadges)
                $(".call-link[data-tabid="+tab_id+"]").append('<span class="badge badge-secondary">'+badge+'</span>');
        });
        
        // Tabs are built, show the body now
        $(".card").fadeIn();
        
        // Insert custom search box 
        $('.dataTables_length').after(
                "<div class='dataTables_filter customSearch'><label>Search:<input type='search'></label></div>");
        
        // Select the first tab on the call list
        let savedTab = Cookies.get('CTRICallLog'+pid);
        if ( savedTab )
            $(".call-link[data-tabid="+savedTab+"]").click();
        else
            $(".call-link").first().click();
        
        // Setup cookie for remembering call tab
        $(".call-link").on('click', function() {
            Cookies.set('CTRICallLog'+pid,$(this).data('tabid'),{sameSite: 'lax'});
        });
        
        // Enable click to expand
        $('.callTable').on('click', '.dataTablesRow', function () {
            let table = $(this).closest('table').DataTable();
            let row = table.row( this );
            if ( row.child.isShown() ) {
                row.child.hide();
                $(this).removeClass('shown');
            } else {
                let data = row.data()
                let record = data[CTRICallLog.static.record_id];
                let call = data['_call_id'];
                let tab_id = $(this).closest('.tab-pane').prop('id');
                let notes = data['_callNotes'];
                let inCall = data['_callStarted'];
                let cells = table.cells(row,'.expandedInfo').render('display');
                row.child( childRowFormat(record, call, inCall, cells, notes, tab_id), 'dataTableChild' ).show();
                $(this).next().addClass( $(this).hasClass('even') ? 'even' : 'odd' );
                $(this).addClass('shown');
            }
        });
        
        // Enable Tooltips
        $('*[data-toggle="tooltip"]').tooltip();
        
        // Refresh the data occasionally
        setInterval( refreshTableData, 2*60*1000);
        
        // Load the initial data
        toggleCallBackCol();
        refreshTableData();
        $(".dataTables_empty").text('Loading...')
        
    });
</script>
<?php
printToScreen('Page First Loaded in '.round(((microtime(true)-$startTime)),5).' seconds');
?>