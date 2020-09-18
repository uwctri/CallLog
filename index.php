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

function loadParsePackCallData() {
    $startTime = microtime(true);
    global $project_id,$module;
    
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
    
    // Large Configs
    $tabs = $module->loadTabConfig();
    $adhoc = $module->loadAdhocTemplateConfig();
    
    if( !$_POST['reloadData'] ) { 
        printToScreen('Config Loaded in '. round((microtime(true)-$startTime),5) .' seconds');
        $startTime = microtime(true);
    }
    
    // Start the Real work
    $packagedCallData = [];
    $alwaysShowCallbackCol = false;
    $today = Date('Y-m-d');
    foreach( $tabs['config'] as $tab )
        $packagedCallData[$tab["tab_id"]] = [];
    foreach( REDCap::getData($project_id,'array') as $record => $recordData ) {
        $meta = json_decode($recordData[$metaEvent][$module->metadataField],true);
        
        // Check if withdrawn
        if ( $recordData[$withdraw['event']][$withdraw['var']] || 
            ($recordData[$withdraw['tmp']['event']][$withdraw['tmp']['var']] && $recordData[$withdraw['tmp']['event']][$withdraw['tmp']['var']]<$today) )
            continue;
        
        foreach( $meta as $callID => $call ) {
            $fullCallID = $callID;
            $callID = explode('|',$callID)[0]; // We only need the simple ID here
            
            // Skip if call complete, debug call, or if call ID isn't assigned to a tab
            if ( $call['complete'] || substr($callID,0,1) == '_' || empty($tabs['call2tabMap'][$callID]) )
                continue;
            
            // Skip when reminders, followups, adhocs aren't in window
            if ( ($call['template'] == 'reminder' || $call['template'] == 'followup' || $call['template'] == 'adhoc') && ($call['start'] > $today) )
                continue;
            
            // Skip reminder calls day of or future
            if ( ($call['template'] == 'reminder') && ($call['end'] <= $today) )
                continue;
            
            $instanceData = $recordData['repeat_instances'][$callEvent][$module->instrumentLower][end($call['instances'])]; // This could be empty for New Entry calls, but it won't matter.
            $instanceEventData = $recordData[$call['event_id']];
            $instanceData = array_merge( array_filter( empty($instanceEventData) ? [] : $instanceEventData, 'isNotBlank' ), array_filter($recordData[$callEvent],'isNotBlank'), array_filter( empty($instanceData) ? [] : $instanceData, 'isNotBlank' ));
            
            // Check if we are at max call attempts for the day
            // While we are at it, assemble all of the note data too
            $attempts = $recordData[$callEvent]['call_open_date'] == $today ? 1 : 0;
            $instanceData['_callNotes'] = "";
            foreach( array_reverse($call['instances']) as $instance ) {
                $itterData = $recordData['repeat_instances'][$callEvent][$module->instrumentLower][$instance];
                //Todo - Maybe add call outcome (what even is that?) to the below.
                $leftMsg = $itterData['call_left_message'][1] == "1" ? 'Left Message' : '&nbsp;';
                $notes = $itterData['call_notes'] ? $itterData['call_notes'] : 'none';
                $instanceData['_callNotes'] .= $itterData['call_open_datetime'].'||'.$itterData['call_open_user_full_name'].'||'.$leftMsg.'||'.$notes.'|||';
                if ( $itterData['call_open_date'] == $today )
                    $attempts++;
            }
            $instanceData['_atMaxAttempts'] = $call['hideAfterAttempt'] <= $attempts;
            $instanceData['call_attempt'] = count($call['instances']); // For displaying the number of past attempts on log
            
            // Add what the next instance should be for possible links
            $instanceData['_nextInstance'] = 1;
            if ( !empty($recordData['repeat_instances'][$callEvent][$module->instrumentLower]) )
                $instanceData['_nextInstance'] = count($recordData['repeat_instances'][$callEvent][$module->instrumentLower])+1;
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
                $instanceData['_callNotes'] .= $call['reported'].'||'.$call['reporter'].'||'.'&nbsp;'.'||'.$call['initNotes'].'|||';
            }
            
            // Make sure we 100% have a call ID (first attempt at a call won't get it from the normal data)
            $instanceData['_call_id'] = $fullCallID;
            
            // Pack data - done
            $packagedCallData[$tabs['call2tabMap'][$callID]][] = $instanceData;
        }
    }
    return array($packagedCallData, $tabs, $alwaysShowCallbackCol, round(((microtime(true)-$startTime)),5));
}

/////////////////////////////////////////////////
// Page Load
/////////////////////////////////////////////////

// Libraries
$module->includeDataTables();
$module->includeCss('css/list.css');

// Load, parse, and pack the Call Data for display
list($packagedCallData, $tabs, $alwaysShowCallbackCol, $timeTaken) = loadParsePackCallData();
printToScreen('Data Transformed in '.$timeTaken.' seconds');
?>

<div class="projhdr"><i class="fas fa-phone"></i> Call List</div>

<div class="card">
    <?php if( count($tabs['config']) > 1) {?>
    <div class="card-header tab-header">
        <ul class="nav nav-tabs card-header-tabs">
            <?php foreach( $tabs['config'] as $tab) {?>
            <li class="nav-item call-tab">
                <a class="nav-link call-link" data-toggle="tab" href="#<?php echo $tab['tab_id'] ?>"><?php echo $tab['tab_name'] ?></a>
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
                <!--<hr class="topSpacer"/>-->
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
    
    function childRowFormat( childData, notesData, tab ) {
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
    
    function callEarlyWarning( url, callbackDateTime ) {
        if (callbackDateTime && (new Date(now) - new Date(callbackDateTime) > (-5*1000*60))) {
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
                  window.location = url;
            })
        } else {
            window.location = url;
        }
    }
    
    function createColConfig(index, tab_id) {
        
        let cols = [];
        $.each( CTRICallLog.tabs['config'][index]['fields'], function(colIndex,fConfig) {
            
            // Standard Config for all fields
            let colConfig = {
                data: fConfig.field,
                title: fConfig.displayName,
                render: (data,type,row,meta) => data || fConfig.default 
            }

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
                colConfig.render = (data,type,row,meta) => CTRICallLog.eventNameMap[data];
            }
            
            // Build out any links
            if ( fConfig.link != "none" ) {
                let url;
                if (fConfig.link == "home")
                    url = '../DataEntry/record_home.php?pid='+pid+'&id=RECORD';
                else if (fConfig.link == "call")
                    url = '../DataEntry/index.php?pid='+pid+'&id=RECORD&event_id='+CTRICallLog.events.callLog.id+'&page='+CTRICallLog.static.instrumentLower+'&instance=INSTANCE&call_id=CALLID';
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
                    $(td).html("<a onclick=\"callEarlyWarning('"+thisURL+"','"+dt+"')\">"+cellData+"</a>");
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
            visible: false,
            className: 'callnotesCol'
        })
        
        // Tack on the Call Back and Info Col
        cols.push({
            title: 'Call Back & Info',
            visible: CTRICallLog.alwaysShowCallbackCol,
            name: 'callbackCol',
            className: 'callbackCol',
            render: function (data, type, row, meta) {
                if ( type === 'display' || type === 'filter' ) {
                    if ( row['call_requested_callback'] && row['call_requested_callback'][1] == '1' )
                        return '<i class="fas fa-stopwatch mr-1" data-toggle="tooltip" data-placement="left" title="Subject\'s requested callback time"></i> '+formatDate(new Date(row['call_callback_date']+'T00:00:00'), CTRICallLog.defaultDateFormat)+" "+conv24to12(row['call_callback_time']);
                    if ( row['_atMaxAttempts'] )
                        return '<i class="fas fa-info-circle float-left" data-toggle="tooltip" data-placement="left" title="This subject has been called the maximum number of times today."></i>';
                } else {
                    if ( row['call_requested_callback'] && row['call_requested_callback'][1] == '1' )
                        return row['call_callback_date']+" "+row['call_callback_time'];
                }
                return '';
            }
        });
        
        return cols;
    }
    
    function toggleHiddenCalls() {
        CTRICallLog.hideCalls = !CTRICallLog.hideCalls;
        $.each( $('.callTable'), function() {
            if ( !CTRICallLog.alwaysShowCallbackCol )
                $(this).DataTable().column( 'callbackCol:name' ).visible(!CTRICallLog.hideCalls);
            $(this).DataTable().draw();
        });
        $('*[data-toggle="tooltip"]').tooltip();//Enable Tooltips for the info icon
    }
    
    function refreshTableData() {
        $.ajax({
            method: 'POST',
            url: CTRICallLog.reloadDataPOST,
            data: {reloadData: true},
            error: (jqXHR, textStatus, errorThrown) => console.log(textStatus + " " +errorThrown),
            success: (data) => {
                data = JSON.parse(data);
                CTRICallLog.packagedCallData = data[0];
                $('.callTable').each( function(index,el) {
                    let table = $(el).DataTable();
                    let page = table.page.info().page;
                    table.clear();
                    table.rows.add(
                        CTRICallLog.packagedCallData[$(el).closest('.tab-pane').prop('id')]);
                    table.draw();
                    table.page(page).draw('page');
                });
                console.log('Refreshed data in '+data[3]+' seconds');
            }
        });
    }
    
    $(document).ready(function() {
        
        // Control display of Calls that have hit max contacts for day
        $.fn.dataTable.ext.search.push(
            function(settings, searchData, index, rowData, counter) {
                return !(CTRICallLog.hideCalls && ((rowData['_atMaxAttempts'] && !rowData['_callbackToday']) || rowData['_callbackNotToday']));
            }
        );
        $(".toggleHiddenCalls").on('click', toggleHiddenCalls); // Control all toggles at once
        
        // Main table build out
        $('.callTable').each( function(index,el) {
            let tab_id = $(el).closest('.tab-pane').prop('id');
            CTRICallLog.childRows[tab_id] = "";
            CTRICallLog.colConfig[tab_id] = createColConfig(index, tab_id);
            
            // Init the table
            $(el).DataTable({
                lengthMenu: [ [25,50,100,-1], [25,50,100, "All"] ],
                columns: CTRICallLog.colConfig[tab_id],
                createdRow: (row,data,index) => $(row).addClass('dataTablesRow'),
                data: CTRICallLog.packagedCallData[tab_id]
            });
        });
        
        // Select the first tab on the call list
        $(".nav-link.call-link").first().addClass("active");
        $(".tab-pane").first().addClass("active");
        
        // Enable click to expand
        $('.callTable').on('click', '.dataTablesRow', function () {
            let row = $(this).closest('table').DataTable().row( this );
            if ( row.child.isShown() ) {
                row.child.hide();
                $(this).removeClass('shown');
            } else {
                let cells = $(this).closest('table').DataTable().cells(row,'.expandedInfo').render('display');
                let notes = $(this).closest('table').DataTable().cells(row,'.callnotesCol').data()[0];
                row.child( childRowFormat(cells, notes, $(this).closest('.tab-pane').prop('id')), 'dataTableChild' ).show();
                $(this).next().addClass( $(this).hasClass('even') ? 'even' : 'odd' );
                $(this).addClass('shown');
            }
        });
        
        // Enable Tooltips
        $('*[data-toggle="tooltip"]').tooltip();
        
        // Refresh the data occasionally
        setInterval( refreshTableData, 2*60*1000);
        
    });
    
</script>
<?php
printToScreen('Page First Loaded in '.round(((microtime(true)-$startTime)),5).' seconds');
?>