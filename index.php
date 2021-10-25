<?php
$startTime = microtime(true);

function printToScreen($string) {
    ?><script>console.log(<?=json_encode($string); ?>);</script><?php
}

// Load, parse, and pack the Call Data for display
list($packagedCallData, $tabs, $alwaysShowCallbackCol, $timeTaken, $issues) = $module->loadCallListData(true);
if ( count($issues) )
    printToScreen('Issues encountered: ' . json_encode($issues));
?>
<script>
CallLog.packagedCallData = <?php echo json_encode($packagedCallData); ?>;
CallLog.tabs = <?php echo json_encode($tabs); ?>;
CallLog.alwaysShowCallbackCol = <?php echo json_encode($alwaysShowCallbackCol); ?>;
CallLog.reloadData = <?php echo json_encode($module->getURL(__FILE__)); ?>;

$(document).ready(function() {
    
    // Custom search options
    $('.card-body').on('input propertychange paste','.customSearch', function() {
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
                CallLog.hideCalls && (
                    (rowData['_atMaxAttempts'] && !rowData['_callbackToday']) || rowData['_callbackNotToday'] || rowData['_noCallsToday']
                )
            );
        }
    );
    $(".toggleHiddenCalls").on('click', toggleHiddenCalls); // Control all toggles at once
    
    // Main table build out
    $('.callTable').each( function(index,el) {
        let tab_id = $(el).closest('.tab-pane').prop('id');
        CallLog.childRows[tab_id] = "";
        CallLog.colConfig[tab_id] = createColConfig(index, tab_id);
        
        // Init the table
        $(el).DataTable({
            lengthMenu: [ [25,50,100,-1], [25,50,100, "All"] ],
            columns: CallLog.colConfig[tab_id],
            createdRow: (row,data,index) => $(row).addClass('dataTablesRow'),
            sDom: 'ltpi'
        });
        
    });
    
    // Tabs are built, show the body now
    $(".card").fadeIn();
    
    // Insert custom search box 
    $('.dataTables_length').after(
            "<div class='dataTables_filter customSearch'><label>Search:<input type='search'></label></div>");
    
    // Select the first tab on the call list
    let savedTab = Cookies.get('CallLog'+pid);
    if ( savedTab ) {
        $(".call-link[data-tabid="+savedTab+"]").click();
    } else {
        $(".call-link").first().click();
    }
    
    // Setup cookie for remembering call tab
    $(".call-link").on('click', function() {
        Cookies.set('CallLog'+pid,$(this).data('tabid'),{sameSite: 'lax'});
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
            let record = data[CallLog.static.record_id];
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
    setInterval( refreshTableData, 5*60*1000);
    
    // Load the initial data
    toggleCallBackCol();
    refreshTableData();
    $(".dataTables_empty").text('Loading...')
});
</script>
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
<?php
printToScreen('Page First Loaded in '.round(((microtime(true)-$startTime)),5).' seconds');
?>