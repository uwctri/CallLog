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
    CallLog.packagedCallData = <?php echo json_encode($packagedCallData); ?>;
    CallLog.tabs = <?php echo json_encode($tabs); ?>;
    CallLog.alwaysShowCallbackCol = <?php echo json_encode($alwaysShowCallbackCol); ?>;
    CallLog.reloadData = <?php echo json_encode($module->getURL(__FILE__)); ?>;
    CallLog.hideCalls = true;
    CallLog.childRows = {};
    CallLog.colConfig = {};
    CallLog.displayedData = {};
    
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
                            CallLog.childRows[tab]+
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
                                    '<div class="row">'+formatDate(new Date(x[0].split(' ')[0]+"T00:00:00"),CallLog.defaultDateFormat)+" "+format_time(x[0].split(' ')[1])+'</div>'+
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
        
        $.each( CallLog.tabs['config'][index]['fields'], function(colIndex,fConfig) {
            
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
                colConfig.render = (data,type,row,meta) => format_time(data) || fConfig.default;
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
                colConfig.render = (data,type,row,meta) => CallLog.eventNameMap[data] || "";
            } else if ( fConfig.validation == 'phone' ) {
                colConfig.render = (data,type,row,meta) => (data && (type === 'filter')) ? data.replace(/[\\(\\)\\-\s]/g,'') : data || "";
            } else if ( Object.keys(CallLog.usernameLists).includes(fConfig.field) ) {
                colConfig.render = (data,type,row,meta) => data ? data.includes($("#username-reference").text()) ? CallLog.usernameLists[fConfig.field]['include'] : CallLog.usernameLists[fConfig.field]['exclude'] : "";
            }
            
            // Build out any links
            if ( fConfig.link != "none" ) {
                let url;
                if (fConfig.link == "home")
                    url = '../DataEntry/record_home.php?pid='+pid+'&id=RECORD';
                else if (fConfig.link == "call")
                    url = '../DataEntry/index.php?pid='+pid+'&id=RECORD&event_id='+CallLog.events.callLog.id+'&page='+CallLog.static.instrumentLower+'&instance=INSTANCE&call_id=CALLID&showReturn=1';
                else if (fConfig.link == "instrument")
                    url = '../DataEntry/index.php?pid='+pid+'&id=RECORD&event_id='+fConfig.linkedEvent+'&page='+fConfig.linkedInstrument;
                colConfig.createdCell = function (td, cellData, rowData, row, col) {
                    let thisURL = url.replace('RECORD',rowData[CallLog.static.record_id]).
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
                    let record = rowData[CallLog.static.record_id];
                    let id = rowData['_call_id'];
                    $(td).html("<a onclick=\"callURLclick("+record+",'"+id+"','"+thisURL+"','"+dt+"')\">"+cellData+"</a>");
                }
            }
            
            // Hide Cols that are for expansion only
            if ( fConfig.expanded ) {
                colConfig.visible = false;
                colConfig.className = 'expandedInfo';
                CallLog.childRows[tab_id] += '<div class="row">'+fConfig.displayName+'</div>';
            }
            
            //Done
            cols.push(colConfig)
        });
        
        // Tack on Lower and Upper windows for Follow ups
        if ( CallLog.tabs['config'][index]['showFollowupWindows'] ) {
            cols.push({title: 'Start Calling',data: '_windowLower'});
            cols.push({title: 'Complete By',data: '_windowUpper'});
        }
        
        // Tack on Missed Appt date
        if ( CallLog.tabs['config'][index]['showMissedDateTime'] ) {
            cols.push({title: 'Missed Date',data: '_appt_dt', render: (data,type,row,meta) =>
                ( type === 'display' || type === 'filter' ) ? formatDate(new Date(data),CallLog.defaultDateTimeFormat).toLowerCase() || "Not Specified" : data || "Not Specified"
            });
        }
        
        // Tack on Adhoc call info
        if ( CallLog.tabs['config'][index]['showAdhocDates'] ) {
            cols.push({title: 'Reason',data: '_adhocReason'});
            cols.push({title: 'Call on',data: '_adhocContactOn', render: function (data,type,row,meta) {
                if ( type === 'display' || type === 'filter' ) {
                    let format =  data.length <= 10 ? CallLog.defaultDateFormat : CallLog.defaultDateTimeFormat;
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
                        displayDate += formatDate(new Date(row['call_callback_date']+'T00:00:00'), CallLog.defaultDateFormat)+" ";
                    displayDate += row['call_callback_time'] ? format_time(row['call_callback_time']) : "";
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
            url: CallLog.router,
            data: {
                route: 'setCallStarted',
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
            url: CallLog.router,
            data: {
                route: 'setCallEnded',
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
        CallLog.hideCalls = !CallLog.hideCalls;
        toggleCallBackCol();
        $('*[data-toggle="tooltip"]').tooltip();//Enable Tooltips for the info icon
    }
    
    function toggleCallBackCol() {
        $('.callTable').each( function() {
            $(this).DataTable().column( 'callbackCol:name' ).visible(CallLog.alwaysShowCallbackCol || !CallLog.hideCalls);
            $(this).DataTable().draw();
        });
    }
    
    function refreshTableData() {
        $.ajax({
            method: 'POST',
            url: CallLog.router,
            data: {
                route: 'dataLoad'
            },
            error: (jqXHR, textStatus, errorThrown) => console.log(textStatus + " " +errorThrown),
            success: (routerData) => {
                routerData = JSON.parse(routerData);
                if ( !routerData.success ) {
                    Swal.fire({
                        title: 'Unable to Load Data',
                        text: "Unable to reach the REDCap server to load data. Please refresh the page or contact a REDCap Administrator for assistance.",
                        icon: 'error',
                    });
                    return;
                }
                let [ packagedCallData, tabConfig, alwaysShowCallbackCol, timeTaken, issues ] = routerData.data;
                CallLog.packagedCallData = packagedCallData;
                CallLog.alwaysShowCallbackCol = alwaysShowCallbackCol;
                
                $('.callTable').each( function(index,el) {
                    let table = $(el).DataTable();
                    let page = table.page.info().page;
                    let tab_id = $(el).closest('.tab-pane').prop('id');
                    table.clear();
                    table.rows.add(CallLog.packagedCallData[tab_id]);
                    if ( CallLog.alwaysShowCallbackCol && ArraysEqual(table.order()[0],[ 1, "asc" ]) )
                        table.order( [[ CallLog.colConfig[tab_id].length-1, "desc" ]] );
                    table.draw();
                    table.page(page).draw('page');
                    updateDataCache(tab_id);
                    updateBadges(tab_id);
                });
                
                toggleCallBackCol();
                console.log('Refreshed data in '+timeTaken+' seconds');
            }
        });
    }
    
    function noCallsToday(record, call_id) {
        $.ajax({
            method: 'POST',
            url: CallLog.router,
            data: {
                route: 'setNoCallsToday',
                record: record,
                id: call_id
            },
            error: (jqXHR, textStatus, errorThrown) => console.log(textStatus + " " +errorThrown),
            success: (data) => refreshTableData()
        });
    }
    
    function updateDataCache(tab_id) {
        CallLog.displayedData[tab_id] = [];
        let table = $("#"+tab_id+" table").DataTable();
        let headers = CallLog.colConfig[tab_id].map( x => x.data );
        CallLog.displayedData[tab_id] = table.rows().data().toArray().map( x=> Object.filterKeys(x, headers));
    }
    
    function updateBadges(tab_id) {
        if ( !CallLog.tabs.showBadges )
            return;
        let badge = 0;
        let user = $("#impersonate-user-select").val() || CallLog.user;
        CallLog.displayedData[tab_id].forEach( x=>Object.values(x).includes(CallLog.userNameMap[user]) && badge++ );
        if ( badge > 0 )
            $(".call-link[data-tabid="+tab_id+"]").append('<span class="badge badge-secondary">'+badge+'</span>');
    }
    
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
<?php
printToScreen('Page First Loaded in '.round(((microtime(true)-$startTime)),5).' seconds');
?>