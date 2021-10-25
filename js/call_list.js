CallLog.html = CallLog.html || {};
CallLog.fn = CallLog.fn || {};
CallLog.hideCalls = true;
CallLog.childRows = {};
CallLog.colConfig = {};
CallLog.displayedData = {};
CallLog.packagedCallData = {};

CallLog.html.noCallsToday = '<i class="fas fa-info-circle float-left infocircle" data-toggle="tooltip" data-placement="left" title="A provider requested that this subject not be contacted today."></i>';
CallLog.html.atMaxAttempts = '<i class="fas fa-info-circle float-left infocircle" data-toggle="tooltip" data-placement="left" title="This subject has been called the maximum number of times today."></i>';
CallLog.html.callBack = '<i class="fas fa-stopwatch mr-1" data-toggle="tooltip" data-placement="left" title="Subject\'s requested callback time"></i>DISPLAYDATE<span class="callbackRequestor" data-toggle="tooltip" data-placement="left" title="Callback set by REQUESTEDBY">LETTER</span>';
CallLog.html.phoneIcon = '<span style="font-size:2em;color:#dc3545;"><i class="fas fa-phone-square-alt" data-toggle="tooltip" data-placement="left" title="This subject may already be in a call."></i></span>'

CallLog.fn.projectLog = function( action, call_id, record ) {
    $.ajax({
        method: 'POST',
        url: CallLog.router,
        data: {
            route: 'log',
            action: action,
            details: `Call ID = ${call_id}`,
            record: record
        },
        error: (jqXHR, textStatus, errorThrown) => console.log(`${jqXHR}\n${textStatus}\n${errorThrown}`),
        success: (data) => console.log(data)
    });
}

CallLog.fn.childRowFormat = function( record, call_id, callStarted, childData, notesData, tab ) {
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
                            '<a class="noCallsButton" onclick="CallLog.fn.noCallsToday('+record+',\''+call_id+'\')">No Calls Today</a>'+
                            ( !callStarted ? '' :
                            '&emsp;<a class="endCallButton" onclick="CallLog.fn.endCall('+record+',\''+call_id+'\')">End Current Call</a>')+
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

CallLog.fn.createColConfig = function(index, tab_id) {
    
    let cols = [{
        title: '',
        data: '_callStarted',
        bSortable: false,
        className: 'callStarted',
        render: (data,type,row,meta) => data ? CallLog.html.phoneIcon : ''
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
                $(td).html("<a onclick=\"CallLog.fn.callURLclick("+record+",'"+id+"','"+thisURL+"','"+dt+"')\">"+cellData+"</a>");
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
                    display += CallLog.html.noCallsToday;
                if ( row['_atMaxAttempts'] )
                    display += CallLog.html.atMaxAttempts;
                if ( displayDate )
                    display += CallLog.html.callBack.replace('DISPLAYDATE',displayDate).replace('REQUESTEDBY',requestedBy).replace('LETTER',requestedBy[0]);
                return display;
            } 
            else if ( type === 'filter' ) {
                if ( displayDate )
                    return displayDate;
            }
            else {
                if ( displayDate )
                    return `${row['call_callback_date']} ${row['call_callback_time']}`;
            }
            return '';
        }
    });
    
    return cols;
}

CallLog.fn.callURLclick = function( record, call_id, url, callbackDateTime ) {
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
              CallLog.fn.startCall(record, call_id, url);
        })
    } else {
        CallLog.fn.startCall(record, call_id, url);
    }
}

CallLog.fn.startCall = function(record, call_id, url) {
    CallLog.fn.projectLog("Started Call", call_id, record);
    $.ajax({
        method: 'POST',
        url: CallLog.router,
        data: {
            route: 'setCallStarted',
            record: record,
            id: call_id,
            user: $("#username-reference").text()
        },
        error: (jqXHR, textStatus, errorThrown) => console.log(`${jqXHR}\n${textStatus}\n${errorThrown}`),
        success: (data) => window.location = url
    });
}

CallLog.fn.endCall = function(record, call_id) {
    $.ajax({
        method: 'POST',
        url: CallLog.router,
        data: {
            route: 'setCallEnded',
            record: record,
            id: call_id
        },
        error: (jqXHR, textStatus, errorThrown) => console.log(`${jqXHR}\n${textStatus}\n${errorThrown}`),
        success: (data) => { 
            CallLog.fn.projectLog("Manually Ended Call", call_id, record);
            console.log('Call ended. Refreshing table data.');
            CallLog.fn.refreshTableData()
        }
    });
}

CallLog.fn.toggleHiddenCalls = function() {
    CallLog.hideCalls = !CallLog.hideCalls;
    CallLog.fn.toggleCallBackCol();
    $('*[data-toggle="tooltip"]').tooltip();//Enable Tooltips for the info icon
}

CallLog.fn.toggleCallBackCol = function() {
    $('.callTable').each( function() {
        $(this).DataTable().column( 'callbackCol:name' ).visible(CallLog.alwaysShowCallbackCol || !CallLog.hideCalls);
        $(this).DataTable().draw();
    });
}

CallLog.fn.refreshTableData = function() {
    $.ajax({
        method: 'POST',
        url: CallLog.router,
        data: {
            route: 'dataLoad'
        },
        error: (jqXHR, textStatus, errorThrown) => console.log(`${jqXHR}\n${textStatus}\n${errorThrown}`),
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
                CallLog.fn.updateDataCache(tab_id);
                CallLog.fn.updateBadges(tab_id);
            });
            
            CallLog.fn.toggleCallBackCol();
            console.log('Refreshed data in '+timeTaken+' seconds');
        }
    });
}

CallLog.fn.noCallsToday = function(record, call_id) {
    $.ajax({
        method: 'POST',
        url: CallLog.router,
        data: {
            route: 'setNoCallsToday',
            record: record,
            id: call_id
        },
        error: (jqXHR, textStatus, errorThrown) => console.log(`${jqXHR}\n${textStatus}\n${errorThrown}`),
        success: (data) => CallLog.fn.refreshTableData()
    });
}

CallLog.fn.updateDataCache = function(tab_id) {
    CallLog.displayedData[tab_id] = [];
    let table = $("#"+tab_id+" table").DataTable();
    let headers = CallLog.colConfig[tab_id].map( x => x.data );
    CallLog.displayedData[tab_id] = table.rows().data().toArray().map( x=> Object.filterKeys(x, headers));
}

CallLog.fn.updateBadges = function(tab_id) {
    if ( !CallLog.tabs.showBadges )
        return;
    let badge = 0;
    let user = $("#impersonate-user-select").val() || CallLog.user;
    CallLog.displayedData[tab_id].forEach( x=>Object.values(x).includes(CallLog.userNameMap[user]) && badge++ );
    if ( badge > 0 )
        $(".call-link[data-tabid="+tab_id+"]").append(`<span class="badge badge-secondary">${badge}</span>`);
}