<?php
function printToScreen($string) {
?>
    <script type='text/javascript'>
       $(function() {
          console.log(<?=json_encode($string); ?>);
       });
    </script>
    <?php
}

// Todo - 100% forgot about the Withdraw condition

// Load libraries
echo '<link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css"/>';
echo '<script type="text/javascript" src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>';
echo '<link rel="stylesheet" href="' . $module->getUrl('calllist.css') . '"/>';

// Load Event IDs
$callEvent = $module->getProjectSetting("call_log_event");
$metaEvent = $module->getProjectSetting("metadata_event");

// Load withdraw Conditon Config
$withdraw = [
    'event' => $module->getProjectSetting("withdraw_event"),
    'var' => $module->getProjectSetting("withdraw_var"),
    'tmp' => [
        'event' => $module->getProjectSetting("withdraw_tmp_event"),
        'var' => $module->getProjectSetting("withdraw_tmp_var")
    ]
];

// Load Tab Config (and Call Type 2 Tab Name map)
$tabs = $module->loadTabConfig();

// Load, parse, and pack the Call Data for display
$packagedCallData = [];
$today = Date('Y-m-d');
$alwaysShowCallbackCol = false;
foreach( $tabs['config'] as $tab )
    $packagedCallData[$tab["tab_id"]] = [];
foreach( REDCap::getData($project_id,'array') as $record => $recordData ) {
    $meta = json_decode($recordData[$metaEvent][$module->metadataField],true);
    
    // Check if withdrawn
    if ( $recordData[$withdraw['event']][$withdraw['var']] || 
        ($recordData[$withdraw['tmp']['event']][$withdraw['tmp']['var']] && $recordData[$withdraw['tmp']['event']][$withdraw['tmp']['var']]<$today) )
        continue;
    
    foreach( $meta as $callID => $call ) {
        $callID = explode('||',$callID)[0]; // Correct for MCV call ID format
        
        // Skip if call complete, debug call, or if call ID isn't assigned to a tab
        if ( $call['complete'] || substr($callID,0,1) == '_' || empty($tabs['call2tabMap'][$callID]) )
            continue;
        
        // Skip when reminders and followups aren't in window
        if ( ($call['template'] == 'reminder' || $call['template'] == 'followup') && $call['start'] > $today )
            continue;
        $instanceData = $recordData['repeat_instances'][$callEvent][$module->instrumentLower][end($call['instances'])]; // This could be empty for New Entry calls, but it won't matter.
        $instanceData = array_merge( $recordData[$callEvent], array_filter($instanceData));
        
        // Check if we are at max call attempts for the day
        $attempts = $recordData[$callEvent]['call_open_date'] == $today ? 1 : 0;
        foreach( array_reverse($call['instances']) as $instance ) {
            if ( $recordData['repeat_instances'][$callEvent][$module->instrumentLower][$instance]['call_open_date'] == $today )
                $attempts++;
            else
                break;
        }
        $instanceData['_atMaxAttempts'] = $call['hideAfterAttempt'] <= $attempts;
        
        // Add what the next instance should be for possible links
        $instanceData['_nextInstance'] = 1;
        if ( !empty($recordData['repeat_instances'][$callEvent][$module->instrumentLower] ) )
            $instanceData['_nextInstance'] = end(array_keys($recordData['repeat_instances'][$callEvent][$module->instrumentLower]))+1;
        
        // Check to see if a call back was request for tomorrow+, set global if any Callback will be shown
        $instanceData['_callbackNotToday'] = ($instanceData['call_callback_requested'][1] == '1' && $instanceData['call_callback_date'] > $today);
        $alwaysShowCallbackCol = $alwaysShowCallbackCol ? true : ($instanceData['call_callback_requested'][1] == '1' && $instanceData['call_callback_date'] <= $today);
        
        // Pack data - done
        $packagedCallData[$tabs['call2tabMap'][$callID]][] = $instanceData;
    }
}
?>

<div class="projhdr"><i class="fas fa-phone"></i> Call List</div>

<div class="card">
    <?php if( count($tabs['config']) > 1) {?>
    <div class="card-header" id="pre-header">
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
                        <button type="button" class="btn btn-light btn-sm toggleHiddenCalls mt-1">Toggle Hidden Calls</button>
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
                        <table class="callTable table" style="width:100%">
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
    CTRICallLog.callEvent = <?php echo $callEvent; ?>;
    CTRICallLog.alwaysShowCallbackCol = <?php echo json_encode($alwaysShowCallbackCol); ?>;
    CTRICallLog.callInstrument = "<?php echo $module->instrumentLower; ?>";
    CTRICallLog.hideCalls = true;
    CTRICallLog.dateFormats = ['MM-dd-y','y-MM-dd','dd-MM-y'];
    
    function toggleHiddenCalls() {
        CTRICallLog.hideCalls = !CTRICallLog.hideCalls;
        if ( !CTRICallLog.alwaysShowCallbackCol ) {
            $.each( $('.callTable'), function() {
                $(this).DataTable().column( 'callbackCol:name' ).visible(!CTRICallLog.hideCalls).draw();
            });
        }
        $('*[data-toggle="tooltip"]').tooltip();//Enable Tooltips for the info icon
    }
    
    $(document).ready(function() {
        // Control display of Calls that have hit max contacts for day
        $.fn.dataTable.ext.search.push(
            function(settings, searchData, index, rowData, counter) {
                return !(CTRICallLog.hideCalls && (rowData['_atMaxAttempts'] || rowData['_callbackNotToday']));
            }
        );
        $(".toggleHiddenCalls").on('click', toggleHiddenCalls); // Control all toggles at once
        
        // Main table build out
        $('.callTable').each( function(index,el) {
            
            // Build out the feilds w/ formatting
            let cols = [];
            $.each( CTRICallLog.tabs['config'][index]['fields'], function() {
                
                // Standard Config for all fields
                let fieldConfig = {
                    data: this.field,
                    title: this.displayName
                }
                
                // Check for Validation on the feild
                let fdate = CTRICallLog.dateFormats[['_mdy','_ymd','_dmy'].map(x=>this.validation.includes(x)).indexOf(true)];
                if ( fdate ) {
                    fieldConfig.render = function ( data, type, row, meta ) {
                        if ( !data )
                            return "";
                        let [date, time] = data.split(' ');
                        let ftime = time ? ' hh:mm' : '';
                        let fsec = time && time.length == 8 ? ':ss' : '';
                        let fmer = time ? 'a' : '';
                        time = time || '00:00:00';
                        return formatDate(new Date( date +'T' + time), fdate+ftime+fsec+fmer).toLowerCase();
                    }
                } else if ( this.validation == 'time' ) {
                    fieldConfig.render = function ( data, type, row, meta ) {
                        return conv24to12(data);
                    }
                }
                
                // Build out any links
                if ( this.link ) {
                    let url;
                    if (this.link == "home")
                        url = '../DataEntry/record_home.php?pid='+pid+'&id=RECORD';
                    else if (this.link == "call")
                        url = '../DataEntry/index.php?pid='+pid+'&id=RECORD&event_id='+CTRICallLog.callEvent+'&page='+CTRICallLog.callInstrument+'&instance=INSTANCE&callID=CALLID';
                    fieldConfig.createdCell = function (td, cellData, rowData, row, col) {
                        $(td).html("<a href='"+url.replace('RECORD',rowData['record_id']).replace('INSTANCE',rowData['_nextInstance']).replace('CALLID',rowData['call_id'])+"'>"+cellData+"</a>");
                    }
                }
                
                //Done
                cols.push(fieldConfig)
            });
            
            // Tack on the Call Back and Info Col
            cols.push({
                title: 'Call Back & Info',
                visible: CTRICallLog.alwaysShowCallbackCol,
                name: 'callbackCol',
                render: function (td, cellData, rowData, row, col) {
                    if ( rowData['call_requested_callback'][1] == '1' )
                        return "<i class='fas fa-alarm-clock'></i> "+formatDate(new Date(rowData['call_callback_date']), 'MM-dd-y')+" "+conv24to12(rowData['call_callback_date']);
                    if ( rowData['_atMaxAttempts'] )
                        return "<i class='fas fa-info-circle float-right mr-2' data-toggle='tooltip' data-placement='left' title='This subject has been called the maximum number of times today.'></i>";
                    return "";
                }
            })
            
            // Init the table
            $(el).DataTable({
                lengthMenu: [ [25,50,100,-1], [25,50,100, "All"] ],
                fixedHeader: true,
                columns: cols,
                data: CTRICallLog.packagedCallData[$(this).closest('.tab-pane').prop('id')]
            });
        });
        
        // Select the first tab on the call list
        $(".nav-link.call-link").first().addClass("active");
        $(".tab-pane").first().addClass("active");
        
        //Enable Tooltips
        $('*[data-toggle="tooltip"]').tooltip();
    });
    
</script>