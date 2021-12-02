CallLog.fn = CallLog.fn || {};
CallLog.hideCalls = true;
CallLog.childRows = {};
CallLog.colConfig = {};
CallLog.displayedData = {};
CallLog.packagedCallData = {};
CallLog.cookie = {};

CallLog.alwaysShowCallbackCol = false;
CallLog.earlyCall = 5 * 60 * 1000; // Grace time on early calling of 5 mins
CallLog.pageRefresh = 5 * 60 * 1000; // Refresh page every 5 minutes

CallLog.fn.setupSearch = function() {

    // Custom search options for regex and "not" (!)
    $('.card-body').on('input propertychange paste', '.customSearch', function() {

        let $table = $('.callTable:visible').DataTable();
        let query = $('.card-body:visible input').val();

        if (query.split(' ')[0] == 'regex') {
            $table.search(query.replace('regex ', ''), true, false).draw();
        } else if (query[0] == '!') {
            $table.search('^(?!.*' + query.slice(1) + ')', true, false).draw();
        } else {
            $table.search(query, false, true).draw();
        }
    });

    // Additional search filtering for hidden calls 
    $.fn.dataTable.ext.search.push(
        (_settings, _searchData, _index, rowData, _counter) => !(
            CallLog.hideCalls && (
                (rowData['_atMaxAttempts'] && !rowData['_callbackToday']) || rowData['_callbackNotToday'] || rowData['_noCallsToday']
            )
        )
    );

    // Control all toggles at once
    $(".toggleHiddenCalls").on('click', function() {
        CallLog.hideCalls = !CallLog.hideCalls;
        CallLog.fn.toggleCallBackCol();
        $('*[data-toggle="tooltip"]').tooltip(); //Enable Tooltips for the info icon
    });
}

CallLog.fn.setupCookies = function() {

    // Load cookie - Select the first tab on the call list
    let cookie = Cookies.get('RedcapCallLog');
    cookie = cookie ? JSON.parse(cookie) : false;
    if (cookie[pid]) {
        CallLog.cookie = cookie;
        $(".call-link[data-tabid=" + cookie[pid] + "]").click();
    } else {
        $(".call-link").first().click();
    }

    // Setup cookie saveing for remembering call tab
    $(".call-link").on('click', function() {
        CallLog.cookie[pid] = $(this).data('tabid');
        Cookies.set('RedcapCallLog', JSON.stringify(CallLog.cookie), { sameSite: 'strict' });
    });
}

CallLog.fn.setupClickToExpand = function() {
    $('.callTable').on('click', '.dataTablesRow', function() {
        let table = $(this).closest('table').DataTable();
        let row = table.row(this);
        if (row.child.isShown()) {
            row.child.hide();
            $(this).removeClass('shown');
        } else {
            let data = row.data()
            let record = data[CallLog.static.record_id];
            let call = data['_call_id'];
            let tab_id = $(this).closest('.tab-pane').prop('id');
            let notes = data['_callNotes'];
            let inCall = data['_callStarted'];
            let cells = table.cells(row, '.expandedInfo').render('display');
            row.child(CallLog.fn.childRowFormat(record, call, inCall, cells, notes, tab_id), 'dataTableChild').show();
            $(this).next().addClass($(this).hasClass('even') ? 'even' : 'odd');
            $(this).addClass('shown');
        }
    });
}

CallLog.fn.projectLog = function(action, call_id, record) {
    $.ajax({
        method: 'POST',
        url: CallLog.router,
        data: {
            route: 'log',
            action: action,
            details: `${action}\nCall ID = ${call_id}`,
            record: record
        },
        error: (jqXHR, textStatus, errorThrown) => console.log(`${jqXHR}\n${textStatus}\n${errorThrown}`),
        success: (data) => console.log(data)
    });
}

CallLog.fn.childRowFormat = function(record, call_id, callStarted, childData, notesData, tab) {
        notesData = notesData.split('|||').map(x => x.split('||')).filter(x => x.length > 2);
        return `<div class="container"><div class="row"><div class="col-4"><div class="row dtChildData"><div class="col-auto">${CallLog.childRows[tab]}</div><div class="col">${childData.map(x => '<div class="row">' + (x || "________") + '</div>').join('')}</div></div><div class="row"><div class="col"><div class="row"><a class="noCallsButton" onclick="CallLog.fn.noCallsToday(${record},\'${call_id}\')">No Calls Today</a>${!callStarted ? '' : `&emsp;<a class="endCallButton" onclick="CallLog.fn.endCall(${record},\'${call_id}\')">End Current Call</a>`}</div></div></div></div><div class="col-8 border-left"><div class="row dtChildNotes"><div class="col">${notesData.map(x => `<div class="row m-2 pb-2 border-bottom"><div class="col-auto"><div class="row">${formatDate(new Date(x[0].split(' ')[0] + "T00:00:00"), CallLog.defaultDateFormat)} ${format_time(x[0].split(' ')[1])}</div><div class="row">${x[1]}</div><div class="row">${x[2]}</div></div><div class="col"><div class="row ml-1">${x[3] == "none" ? "No Notes Taken" : x[3]}</div></div></div>`).join('') || '<div class="text-center mt-4">Call history will display here</div>'}</div></div></div></div></div>`;
}

CallLog.fn.createColConfig = function(index, tab_id) {

    let cols = [{
        title: '',
        data: '_callStarted',
        bSortable: false,
        className: 'callStarted',
        render: (data) => data ? CallLog.templates.phoneIcon : ''
    }];

    $.each(CallLog.tabs['config'][index]['fields'], function(colIndex, fConfig) {

        // Standard Config for all fields
        let colConfig = {
            data: fConfig.field,
            title: fConfig.displayName,
            render: (data) => data || fConfig.default,
            defaultContent: ""
        }

        if (colIndex == 0)
            colConfig['className'] = 'firstDataCol';

        // Check for Validation on the feild
        const dateFormats = ['MM-dd-y', 'y-MM-dd', 'dd-MM-y'];
        let fdate = dateFormats[['_mdy', '_ymd', '_dmy'].map(x => fConfig.validation.includes(x)).indexOf(true)];
        if (fdate) {
            colConfig.render = function(data, type) {
                if (!data)
                    return fConfig.default;
                if (type === 'display' || type === 'filter') {
                    let [date, time] = data.split(' ');
                    let ftime = time ? ' hh:mm' : '';
                    let fsec = time && time.length == 8 ? ':ss' : '';
                    let fmer = time ? 'a' : '';
                    time = time || '00:00:00';
                    return formatDate(new Date(date + 'T' + time), fdate + ftime + fsec + fmer).toLowerCase();
                }
                return data;
            }
        } else if (fConfig.validation == 'time') {
            colConfig.render = (data, _type, _row, _meta) => format_time(data) || fConfig.default;
        } else if (["radio", "select"].includes(fConfig.fieldType)) {
            colConfig.render = (data, _type, _row, _meta) => fConfig.map[data] || fConfig.default;
        } else if (["yesno", "truefalse"].includes(fConfig.fieldType)) {
            let map = fConfig.fieldType == 'truefalse' ? ['False', 'True'] : ['No', 'Yes'];
            colConfig.render = (data, _type, _row, _meta) => map[data] || fConfig.default;
        } else if (fConfig.fieldType == "checkbox") {
            colConfig.render = (data, _type, _row, _meta) => typeof data == "object" ?
                Object.keys(Object.filter(data, x => x == "1")).map(x => fConfig.map[x]).join(', ') || fConfig.default : fConfig.default;
        } else if (fConfig.isFormStatus) {
            colConfig.render = (data, _type, _row, _meta) => ['Incomplete', 'Unverified', 'Complete'][data];
        } else if (colConfig.data == "call_event_name") {
            colConfig.render = (data, _type, _row, _meta) => CallLog.eventNameMap[data] || "";
        } else if (fConfig.validation == 'phone') {
            colConfig.render = (data, type, _row, _meta) => (data && (type === 'filter')) ? data.replace(/[\\(\\)\\-\s]/g, '') : data || "";
        } else if (Object.keys(CallLog.usernameLists).includes(fConfig.field)) {
            colConfig.render = (data, _type, _row, _meta) => data ? data.includes($("#username-reference").text()) ? CallLog.usernameLists[fConfig.field]['include'] : CallLog.usernameLists[fConfig.field]['exclude'] : "";
        }

        // Build out any links
        if (fConfig.link != "none") {

            let url;
            switch (fConfig.link ) {
                case "home":
                    url = `../DataEntry/record_home.php?pid=${pid}&id=RECORD`;
                break;
                case "call":
                    url = `../DataEntry/index.php?pid=${pid}&id=RECORD&event_id=${CallLog.events.callLog.id}&page=${CallLog.static.instrumentLower}&instance=INSTANCE&call_id=CALLID&showReturn=1`;
                break;
                case "instrument":
                    url = `../DataEntry/index.php?pid=${pid}&id=RECORD&event_id=${fConfig.linkedEvent}&page=${fConfig.linkedInstrument}`;
                break;
            }

            colConfig.createdCell = function(td, cellData, rowData, _row, _col) {
                let thisURL = url.replace('RECORD', rowData[CallLog.static.record_id]).
                replace('INSTANCE', rowData['_nextInstance']).
                replace('CALLID', rowData['_call_id']);
                let dt = "";

                if (rowData['call_callback_date'] && rowData['call_callback_time']) {
                    dt = `${rowData['call_callback_date']} ${rowData['call_callback_time']}`;
                } 
                else if (rowData['call_callback_date']) {
                    dt = `${rowData['call_callback_date']}  00:00:00`;
                } 
                else if (rowData['call_callback_time']) {
                    dt = `${today} ${rowData['call_callback_time']}`;
                }

                let record = rowData[CallLog.static.record_id];
                let id = rowData['_call_id'];
                $(td).html(`<a onclick='CallLog.fn.callURLclick(${record},"${id}","${thisURL}","${dt}")'>${cellData}</a>`);
            }
        }

        // Hide Cols that are for expansion only
        if (fConfig.expanded) {
            colConfig.visible = false;
            colConfig.className = 'expandedInfo';
            CallLog.childRows[tab_id] += `<div class="row">${fConfig.displayName}</div>`;
        }

        //Done
        cols.push(colConfig)
    });

    // Tack on Lower and Upper windows for Follow ups
    if (CallLog.tabs['config'][index]['showFollowupWindows']) {
        cols.push({ title: 'Start Calling', data: '_windowLower' });
        cols.push({ title: 'Complete By', data: '_windowUpper' });
    }

    // Tack on Missed Appt date
    if (CallLog.tabs['config'][index]['showMissedDateTime']) {
        cols.push({
            title: 'Missed Date',
            data: '_appt_dt',
            render: (data, type, _row, _meta) =>
                (type === 'display' || type === 'filter') ? formatDate(new Date(data), CallLog.defaultDateTimeFormat).toLowerCase() || "Not Specified" : data || "Not Specified"
        });
    }

    // Tack on Adhoc call info
    if (CallLog.tabs['config'][index]['showAdhocDates']) {
        cols.push({ title: 'Reason', data: '_adhocReason' });
        cols.push({
            title: 'Call on',
            data: '_adhocContactOn',
            render: function(data, type, _row, _meta) {
                if (type === 'display' || type === 'filter') {
                    let format = data.length <= 10 ? CallLog.defaultDateFormat : CallLog.defaultDateTimeFormat;
                    data = data.length <= 10 ? data + "T00:00" : data;
                    return formatDate(new Date(data), format).toLowerCase() || "Not Specified";
                } else {
                    return data;
                }
            }
        });
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
        render: function(_data, type, row, _meta) {
            let displayDate = '';
            if (row['call_requested_callback'] && row['call_requested_callback'][1] == '1') {

                if (row['call_callback_date']) {
                    displayDate += formatDate(new Date(row['call_callback_date'] + 'T00:00:00'), CallLog.defaultDateFormat) + " ";
                }

                displayDate += row['call_callback_time'] ? format_time(row['call_callback_time']) : "";
                if (!displayDate) {
                    displayDate = "Not specified";
                }
            }

            let requestedBy = row['call_callback_requested_by'] ? row['call_callback_requested_by'] == '1' ? 'Participant' : 'Staff' : ' ';
            if (type === 'display') {
                let display = '';
                if (row['_noCallsToday']) {
                    display += CallLog.templates.noCallsToday;
                }
                if (row['_atMaxAttempts']) {
                    display += CallLog.templates.atMaxAttempts;
                }
                if (displayDate) {
                    display += CallLog.templates.callBack
                        .replace('DISPLAYDATE', displayDate)
                        .replace('REQUESTEDBY', requestedBy)
                        .replace('LETTER', requestedBy[0]);
                }
                return display;
            } else if (type === 'filter') {
                if (displayDate) return displayDate;
            } else {
                if (displayDate) return `${row['call_callback_date']} ${row['call_callback_time']}`;
            }
            return '';
        }
    });

    return cols;
}

CallLog.fn.callURLclick = function(record, call_id, url, callbackDateTime) {
    event.stopPropagation();
    if (callbackDateTime && (new Date() < (new Date(callbackDateTime) - CallLog.earlyCall)) ) {
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
        success: () => window.location = url
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
        success: () => {
            CallLog.fn.projectLog("Manually Ended Call", call_id, record);
            console.log('Call ended. Refreshing table data.');
            CallLog.fn.refreshTableData();
        }
    });
}

CallLog.fn.toggleCallBackCol = function() {
    $('.callTable').each(function() {
        $(this).DataTable().column('callbackCol:name').visible(CallLog.alwaysShowCallbackCol || !CallLog.hideCalls);
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
            if (!routerData.success) {
                Swal.fire({
                    title: 'Unable to Load Data',
                    text: "Unable to reach the REDCap server to load data. Please refresh the page or contact a REDCap Administrator for assistance.",
                    icon: 'error',
                });
                return;
            }
            let [packagedCallData, tabConfig, alwaysShowCallbackCol, timeTaken] = routerData.data;
            CallLog.packagedCallData = packagedCallData;
            CallLog.alwaysShowCallbackCol = alwaysShowCallbackCol;

            $('.callTable').each(function(_index, el) {
                let table = $(el).DataTable();
                let page = table.page.info().page;
                let tab_id = $(el).closest('.tab-pane').prop('id');
                table.clear();
                table.rows.add(CallLog.packagedCallData[tab_id]);
                let order = table.order()[0];
                if (CallLog.alwaysShowCallbackCol && order[0] <= 1 && order[1] == "asc")
                    table.order([ // Order by call back times if previous ordered by record_id
                        [CallLog.colConfig[tab_id].length - 1, "desc"]
                    ]);
                table.draw();
                table.page(page).draw('page');
                CallLog.fn.updateDataCache(tab_id);
                CallLog.fn.updateBadges(tab_id);
            });

            CallLog.fn.toggleCallBackCol();
            console.log(`Refreshed data in ${timeTaken} seconds`);
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
        success: (_data) => CallLog.fn.refreshTableData()
    });
}

CallLog.fn.updateDataCache = function(tab_id) {
    CallLog.displayedData[tab_id] = [];
    let table = $(`#${tab_id}table`).DataTable();
    let headers = CallLog.colConfig[tab_id].map(x => x.data);
    CallLog.displayedData[tab_id] = table.rows().data().toArray().map(x => Object.filterKeys(x, headers));
}

CallLog.fn.updateBadges = function(tab_id) {
    if (!CallLog.tabs.showBadges)
        return;
    let badge = 0;
    let user = $("#impersonate-user-select").val() || CallLog.user;
    CallLog.displayedData[tab_id].forEach(x => Object.values(x).includes(CallLog.userNameMap[user]) && badge++);
    if (badge > 0)
        $(`.call-link[data-tabid=${tab_id}]`).append(`<span class="badge badge-secondary">${badge}</span>`);
}