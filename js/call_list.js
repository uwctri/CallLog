CallLog.fn = CallLog.fn || {};
CallLog.hideCalls = true;
CallLog.childRows = {};
CallLog.colConfig = {};
CallLog.displayedData = {};
CallLog.packagedCallData = {};

CallLog.alwaysShowCallbackCol = false;
CallLog.earlyCall = 5 * 60 * 1000; // Grace time on early calling of 5 mins
CallLog.pageRefresh = 1 * 60 * 1000; // Refresh page every 1 minutes

Object.filter = (obj, predicate) =>
    Object.keys(obj)
        .filter(key => predicate(obj[key]))
        .reduce((res, key) => (res[key] = obj[key], res), {});

Object.filterKeys = (obj, allowedKeys) =>
    Object.keys(obj)
        .filter(key => (Array.isArray(allowedKeys) ? allowedKeys : [allowedKeys]).includes(key))
        .reduce((res, key) => {
            res[key] = obj[key];
            return res;
        }, {});

CallLog.fn.setupSearch = function () {

    // Custom search options for regex and "not" (!)
    $('.card-body').on('input propertychange paste', '.customSearch', function () {

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
                (rowData['_atMaxAttempts'] && !rowData['_callbackToday']) || rowData['_callbackNotToday'] || rowData['_noCallsToday'] || rowData['_futureAdhoc']
            )
        )
    );

    // Control all toggles at once
    $(".toggleHiddenCalls").on('click', function () {
        CallLog.hideCalls = !CallLog.hideCalls;
        CallLog.fn.toggleCallBackCol();
        $('*[data-toggle="tooltip"]').tooltip(); //Enable Tooltips for the info icon
    });
}

CallLog.fn.setupLocalSettings = function () {
    const key = `ExternalModules.UWMadison.CallLog.${pid}`;
    // Setup localStorage saving 
    $(".call-link").on('click', (el) => {
        let tab = $(el.currentTarget).data('tabid');
        localStorage.setItem(key, JSON.stringify({ tab }));
    });
    // Load localStorage
    let storage = localStorage.getItem(key);
    storage = storage ? JSON.parse(storage) : false;
    if (storage) {
        $(`.call-link[data-tabid=${storage.tab}]`).click();
        return;
    }
    $(".call-link").first().click();
}

CallLog.fn.setupClickToExpand = function () {
    $('.callTable').on('click', '.dataTablesRow', function () {
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

CallLog.fn.projectLog = function (action, call_id, record) {
    CallLog.em.ajax("log", {
        text: `${action}\nCall ID: ${call_id}`,
        record: record,
        event: null
    }).then(function (response) {
        console.log(response)
    }).catch(function (err) {
        console.log(err)
    });
}

CallLog.fn.childRowFormat = function (record, call_id, callStarted, childData, notesData, tab) {
    notesData = notesData.split('|||').map(x => x.split('||')).filter(x => x.length > 2);
    return `<div class="container"><div class="row"><div class="col-4"><div class="row dtChildData"><div class="col-auto">${CallLog.childRows[tab]}</div><div class="col">${childData.map(x => '<div class="row">' + (x || "________") + '</div>').join('')}</div></div><div class="row"><div class="col"><div class="row"><a class="noCallsButton" onclick="CallLog.fn.noCallsToday(${record},\'${call_id}\')">No Calls Today</a>${!callStarted ? '' : `&emsp;<a class="endCallButton" onclick="CallLog.fn.endCall(${record},\'${call_id}\')">End Current Call</a>`}</div></div></div></div><div class="col-8 border-left"><div class="row dtChildNotes"><div class="col">${notesData.map(x => `<div class="row m-2 pb-2 border-bottom"><div class="col-auto"><div class="row">${formatDate(new Date(x[0].split(' ')[0] + "T00:00:00"), CallLog.defaultDateFormat)} ${format_time(x[0].split(' ')[1])}</div><div class="row">${x[1]}</div><div class="row">${x[2]}</div></div><div class="col"><div class="row ml-1">${x[3] == "none" ? "No Notes Taken" : x[3]}</div></div></div>`).join('') || '<div class="text-center mt-4">Call history will display here</div>'}</div></div></div></div></div>`;
}

CallLog.fn.createColConfig = function (index, tab_id) {

    let cols = [{
        title: '',
        data: '_callStarted',
        bSortable: false,
        className: 'leftListIcon',
        render: (data, type, row, meta) => {
            const record = row['record_id'];
            if (CallLog.activeCallCache.includes(record)) return CallLog.templates.phoneIcon;
            const multi = CallLog.multiTabCache[record];
            if (multi) return CallLog.templates.manyTabIcons.replace('LIST', multi.map(el => CallLog.tabs.tabNameMap[el]).join('&#010;'));
            return "";
        }
    }];

    $.each(CallLog.tabs['config'][index]['fields'], function (colIndex, fConfig) {

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
            colConfig.render = function (data, type) {
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
            colConfig.render = (data, _type, _row, _meta) => data ? data.includes(CallLog.user) ? CallLog.usernameLists[fConfig.field]['include'] : CallLog.usernameLists[fConfig.field]['exclude'] : "";
        }

        // Build out any links
        if (fConfig.link && fConfig.link != "none") {

            let url;
            switch (fConfig.link) {
                case "":
                case "home":
                    url = `../DataEntry/record_home.php?pid=${pid}&id=RECORD`;
                    break;
                case "call":
                    url = `../DataEntry/index.php?pid=${pid}&id=RECORD&event_id=${CallLog.static.instrumentEvent}&page=${CallLog.static.instrument}&instance=INSTANCE&call_id=CALLID&showReturn=1`;
                    break;
                case "instrument":
                    url = `../DataEntry/index.php?pid=${pid}&id=RECORD&event_id=${fConfig.linkedEvent}&page=${fConfig.linkedInstrument}`;
                    break;
            }

            colConfig.createdCell = function (td, cellData, rowData, _row, _col) {
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
                (type === 'display' || type === 'filter') ? formatDate(new Date(data), CallLog.format.dateTime).toLowerCase() || "Not Specified" : data || "Not Specified"
        });
    }

    // Tack on Adhoc call info
    if (CallLog.tabs['config'][index]['showAdhocDates']) {
        cols.push({ title: 'Reason', data: '_adhocReason' });
        cols.push({
            title: 'Call on',
            data: '_adhocContactOn',
            render: function (data, type, _row, _meta) {
                if (type === 'display' || type === 'filter') {
                    let format = data.length <= 10 ? CallLog.format.date : CallLog.format.dateTime;
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
        render: function (_data, type, row, _meta) {
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

CallLog.fn.callURLclick = function (record, call_id, url, callbackDateTime) {
    event.stopPropagation();
    if (callbackDateTime && (new Date() < (new Date(callbackDateTime) - CallLog.earlyCall))) {
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

CallLog.fn.startCall = function (record, call_id, url) {
    CallLog.fn.projectLog("Started Call", call_id, record);
    CallLog.em.ajax("setCallStarted", {
        record: record,
        id: call_id,
        user: CallLog.user
    }).then(function (response) {
        console.log(response);
        window.location = url;
        // window.open(url, "_blank")
        // TODO update call started icon
    }).catch(function (err) {
        console.log(err)
    });
}

CallLog.fn.endCall = function (record, call_id) {
    CallLog.em.ajax("setCallEnded", {
        record: record,
        id: call_id
    }).then(function (response) {
        console.log(response);
        CallLog.fn.projectLog("Manually Ended Call", call_id, record);
        CallLog.fn.refreshTableData();
    }).catch(function (err) {
        console.log(err);
    });
}

CallLog.fn.toggleCallBackCol = function () {
    $('.callTable').each(function () {
        $(this).DataTable().column('callbackCol:name').visible(CallLog.alwaysShowCallbackCol || !CallLog.hideCalls);
        $(this).DataTable().draw();
    });
}

CallLog.fn.refreshTableData = function () {
    console.time('getData');
    CallLog.em.ajax("getData", {}).then(function (response) {
        if (!response.success) {
            Swal.fire({
                title: 'Unable to Load Data',
                text: "Unable to reach the REDCap server to load data. Please refresh the page or contact a REDCap Administrator for assistance.",
                icon: 'error',
            });
            return;
        }
        let result = response.data;
        CallLog.packagedCallData = result.data;
        CallLog.alwaysShowCallbackCol = result.showCallback;

        // Keep track of users in multiple tabs
        CallLog.multiTabCache = {};
        CallLog.activeCallCache = [];
        $.each(CallLog.packagedCallData, (tab, data) => {
            data.forEach((el) => {
                CallLog.multiTabCache[el.record_id] ||= [];
                CallLog.multiTabCache[el.record_id].push(tab);
                if (el._callStarted) CallLog.activeCallCache.push(el.record_id);
            });
        });
        CallLog.multiTabCache = Object.fromEntries(Object.entries(CallLog.multiTabCache).filter((el) => el[1].length > 1));

        $('.callTable').each(function (_index, el) {
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

        // Enable Tooltips for the call-back column
        $('*[data-toggle="tooltip"]').tooltip();

        // Report and setup the next refresh
        console.timeEnd('getData');
        setTimeout(CallLog.fn.refreshTableData, CallLog.pageRefresh);
    }).catch(function (err) {
        console.log(err);
    });
}

CallLog.fn.noCallsToday = function (record, call_id) {
    CallLog.fn.projectLog("No Calls Today", call_id, record);
    CallLog.em.ajax("setNoCallsToday", {
        record: record,
        id: call_id
    }).then(function (response) {
        console.log(response);
        CallLog.fn.refreshTableData()
    }).catch(function (err) {
        console.log(err);
    });
}

CallLog.fn.updateDataCache = function (tab_id) {
    CallLog.displayedData[tab_id] = [];
    let table = $(`#${tab_id}table`).DataTable();
    let headers = CallLog.colConfig[tab_id].map(x => x.data);
    CallLog.displayedData[tab_id] = table.rows().data().toArray().map(x => Object.filterKeys(x, headers));
}

CallLog.fn.updateBadges = function (tab_id) {
    let badge = 0;
    let user = $("#impersonate-user-select").val() || CallLog.user;
    CallLog.displayedData[tab_id].forEach(x => Object.values(x).includes(CallLog.userNameMap[user]) && badge++);
    if (badge > 0)
        $(`.call-link[data-tabid=${tab_id}]`).append(`<span class="badge badge-secondary">${badge}</span>`);
}

$(document).ready(function () {
    if (CallLog.configError) {
        Swal.fire({
            icon: 'error',
            title: 'Call Log config Issue',
            text: 'The Call Log External Module requries the Call Long, and Call Metadata instruments to exist on one event and the former to be enable as a repeatable instrument. Please invesitage and resovle.',
        });
        return;
    }

    // Setup search, must happen before table init
    CallLog.fn.setupSearch();

    // Main table build out
    $('.callTable').each(function (index, el) {

        let tab_id = $(el).closest('.tab-pane').prop('id');
        CallLog.childRows[tab_id] = "";
        CallLog.colConfig[tab_id] = CallLog.fn.createColConfig(index, tab_id);

        // Init the table
        $(el).DataTable({
            lengthMenu: [
                [25, 50, 100, -1],
                [25, 50, 100, "All"]
            ],
            language: {
                emptyTable: "No calls to display"
            },
            columns: CallLog.colConfig[tab_id],
            createdRow: (row, data, index) => $(row).addClass('dataTablesRow'),
            sDom: 'ltpi'
        });

    });

    // Insert search box, must happen after table init
    $('.dataTables_length').after(
        "<div class='dataTables_filter customSearch'><label>Search:<input type='search'></label></div>");

    // Exactly what it looks like
    CallLog.fn.setupLocalSettings();

    // Everything is built out, show the body now
    $(".card").fadeIn();

    // Enable click to expand for all rows
    CallLog.fn.setupClickToExpand();

    // Load the initial data
    CallLog.fn.toggleCallBackCol();
    CallLog.fn.refreshTableData();
    $(".dataTables_empty").text('Loading...')
});