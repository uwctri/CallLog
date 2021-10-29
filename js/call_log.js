CallLog.html = CallLog.html || {};
CallLog.fn = CallLog.fn || {};

CallLog.html.wrapper = `
<tr style="border: 1px solid #ddd"><td colspan="2">
<div class="card-header">
    <ul class="nav nav-tabs card-header-tabs">
    </ul>
</div>
</td></tr>`;

CallLog.html.tab = `
<li class="nav-item call-tab">
    <a class="nav-link mr-1" href="#" data-call-id="CALLID">TABNAME</a>
</li>`;

CallLog.html.historic = `
<tr><td colspan="2">
    <div class="alert alert-danger mb-0">
        <div class="container row">
            <div class="col-1"><i class="fas fa-exclamation-triangle h4 mt-1"></i></div>
            <div class="col-10 mt-2 text-center"><b>This is a historic call log that you probably shouldn't be on.</b></div>
            <div class="col-1"><i class="fas fa-exclamation-triangle h4 mt-1"></i></div>
        </div>
    </div>
</td><tr>`;

CallLog.html.noCalls = `
<tr><td colspan="2">
    <div class="yellow">
        <div class="container row">
            <div class="col m-2 text-center"><b>This subject has no active calls.</b></div>
        </div>
    </div>
</td><tr>`;

CallLog.html.notes = `
<td class="col-7 notesRow" colspan="2" style="background-color:#f5f5f5"> 
    <div class="container">
        <div class="row mb-3 mt-2 font-weight-bold"> Notes </div>
        <div class="row panel-container">
            <div class="panel-left">
                <textarea class="notesOld" readonly placeholder="Previous notes will display here"></textarea>
            </div>
            <div class="splitter"></div>
            <div class="panel-right">
                <textarea class="notesNew" placeholder="Enter any notes here"></textarea>
            </div>
        </div>
    </div>
</td>`;

CallLog.html.adhoc = `<button type="button" class="btn btn-primaryrc btn-sm position-absolute adhocButton" data-toggle="modal" data-target="#MODALID">TEXT</button>`;

CallLog.html.adhocModal = `
<div class="modal fade" id="MODALID" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"> MODAL TITLE </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form class="form-horizontal"><fieldset>
                    <div class="form-group">
                        <label class="col h6">Reason For Call</label>
                        <div class="col">
                            <select name="reason" class="form-control">
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col h6">Prefered Call Date & Time</label>  
                        <div class="col">
                            <div class="form-group row">
                                <div class="col">
                                    <input name="callDate" type="text" class="form-control maxWidthOverride">
                                </div>
                                <div class="col">
                                    <input name="callTime" type="text" class="form-control maxWidthOverride">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col h6">Notes</label>
                        <div class="col">
                            <textarea class="form-control" name="notes" placeholder="Elaborate on the issue/question if possible"></textarea>
                        </div>
                    </div>
                </fieldset></form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primaryrc callModalSave">Save Call & Exit</button>
            </div>
        </div>
    </div>
</div>
`;

CallLog.optionalCSS = `
<style>
    .formHeader {
        background-color: #675186;
        color: #FBE5D6;
        font-weight: normal;
        font-size: 2.5rem;
        text-align: center;
    }
    .formSxnHeader {
        background-color: #67B094;
        color: #f7ecec;
        font-weight: normal;
        font-size: 2rem;
        text-align: center;
    }
</style>`;

// Debug function, show all hidden fields
CallLog.fn.callAdminEdit = function() {
    $("*[class='@HIDDEN']").show();
    $("#__SUBMITBUTTONS__-tr").hide();
}

// Debug function, easily save the metadata after directly editing it
CallLog.fn.saveMetadata = function() {
    $.ajax({
        method: 'POST',
        url: CallLog.router,
        data: {
            route: 'metadataSave',
            record: getParameterByName('id'),
            metadata: JSON.stringify(CallLog.metadata)
        },
        error: (jqXHR, textStatus, errorThrown) => console.log(textStatus + " " + errorThrown),
        success: (data) => console.log(data)
    });
}

// Debug function, easily upate data on an older log
CallLog.fn.saveCalldata = function(instance, dataVar, dataVal, isCheckbox) {
    isCheckbox = !!isCheckbox;
    if (isCheckbox)
        dataVal = JSON.stringify(dataVal);
    $.ajax({
        method: 'POST',
        url: CallLog.router,
        data: {
            route: 'calldataSave',
            record: getParameterByName('id'),
            instance: instance,
            dataVar: dataVar,
            dataVal: dataVal,
            isCheckbox: isCheckbox
        },
        error: (jqXHR, textStatus, errorThrown) => console.log(textStatus + " " + errorThrown),
        success: (data) => console.log(data)
    });
}

// Debug function, give yourself a few extra days for that call
CallLog.fn.UpdateCallTypeEndDates = function(call_type, days) {
    $.each(CallLog.metadata, function(callid, data) {
        if (!callid.includes(call_type))
            return;
        CallLog.metadata[callid]['end'] = formatDate((new Date(CallLog.metadata[callid]['start'] + "T00:00").addDays(days)), 'y-MM-dd');
    });
}

// Fetch the last known contact time for a given call id
CallLog.fn.getPreviousCalldatetime = function(callID) {
    if (!CallLog.metadata[callID] || isEmpty(CallLog.metadata[callID].instances))
        return "";
    let data = CallLog.data[CallLog.metadata[callID].instances.slice(-1)[0]];
    if (!data)
        return "";
    return formatDate(new Date(data['call_open_date'] + "T00:00:00"), 'MM-dd-y') + " " + format_time(data['call_open_time']);
}

// Fetch the checkboxes of remaining tasks from previous call given a id
CallLog.fn.getPreviousCallTasks = function(callID) {
    if (!CallLog.metadata[callID] || isEmpty(CallLog.metadata[callID].instances))
        return [];
    let data = CallLog.data[CallLog.metadata[callID].instances.slice(-1)[0]];
    if (!data)
        return [];
    let arr = [];
    for (const [key, value] of Object.entries(data['call_task_remaining'])) {
        if (value == "1")
            arr.push(key);
    }
    return arr;
}

// Fetch all previous call notes and compile them.
CallLog.fn.getPreviousCallNotes = function(callID) {
    if (!CallLog.metadata[callID] || isEmpty(CallLog.metadata[callID].instances))
        return [];
    let notes = [];
    $.each(CallLog.metadata[callID].instances, function(_, instance) {
        let data = CallLog.data[instance];
        if (!data)
            return;
        notes.push({
            'dt': formatDate(new Date(data['call_open_date'] + "T00:00:00"), 'MM-dd-y') + " " + format_time(data['call_open_time']),
            'text': data['call_notes'],
            'user': data['call_open_user_full_name']
        });
    });
    return notes;
}

CallLog.fn.updateCallNotes = function(callID) {
    $('.notesOld').val("")
    $('.notesNew').val($('textarea[name=call_notes]').val());
    $.each(CallLog.fn.getPreviousCallNotes(callID), function() {
        if (this.text == "")
            return;
        $('.notesOld').val(`${this.dt} ${this.user}: ${this.text} \n\n${$('.notesOld').val()}`.trim());
    });
}

CallLog.fn.getWeekNumber = function(d) {
    d = typeof d == 'object' ? d : new Date(d);
    d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7));
    var yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    var weekNo = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    return [d.getUTCFullYear(), weekNo];
}

CallLog.fn.getLeaveMessage = function(callID) {
    let meta = CallLog.metadata[callID]
    if (meta.voiceMails >= meta.maxVoiceMails)
        return 'No';
    if (meta.maxVMperWeek >= meta.voiceMails)
        return 'Yes';
    let thisWeek = CallLog.fn.getWeekNumber(new Date())[1];
    return CallLog.metadata[callID].instances.map(
        x => CallLog.fn.getWeekNumber(CallLog.data[x]['call_open_date']) == thisWeek).filter(x => x).length >= meta.maxVMperWeek ? 'No' : 'Yes';
}

CallLog.fn.selectTab = function() {
    if ($("input[name=call_id]").val() != "")
        return;
    if (getParameterByName('call_id')) {
        $(`.nav-link[data-call-id=${decodeURI(getParameterByName('call_id')).replace(/\|/g,"\\|").replace(/\:/g,"\\:").replace(/\s/g,"\\ ")}]`).click();
    } else {
        $(".nav-link:visible").first().click();
    }
    if (!$(".nav-link.active:visible").length) {
        setTimeout(CallLog.fn.selectTab, 500);
    }
}

CallLog.fn.buildNotesArea = function() {
    $("#call_notes-tr td").hide();
    $("#call_notes-tr").append(CallLog.html.notes);
    $(".panel-left").resizable({
        handleSelector: ".splitter",
        resizeHeight: false,
        create: (event, ui) => $('.ui-icon-gripsmall-diagonal-se').remove()
    });
    $('.notesNew').on('change', () => $('textarea[name=call_notes]').val($('.notesNew').val()));
}

CallLog.fn.isCompletedLog = function() {
    if ($(`select[name=${CallLog.static.instrumentLower}_complete]`).val() == "0")
        return false;

    // Prevent editing
    $(".formtbody").prepend(CallLog.html.historic);
    $("#__SUBMITBUTTONS__-tr").hide();

    // Fill out call details
    let id = CallLog.data[getParameterByName('instance')]['call_id'];
    let data = CallLog.data[getParameterByName('instance')];
    $("#CallLogCurrentCall").text(CallLog.metadata[id]['name']);
    $("td:contains(Current Caller)").next().text(data['call_open_user_full_name']);
    $("#CallLogCurrentTime").text(formatDate(new Date(data['call_open_date'] + "T00:00:00"), 'MM-dd-y') + " " + format_time(data['call_open_time']));
    $("#CallLogPreviousTime").text("Historic");
    CallLog.fn.updateCallNotes(id);
    return true;
}

CallLog.fn.buildTabs = function() {
    $("#questiontable tr[id]").first().before(CallLog.html.wrapper);
    $.each(CallLog.metadata, function(callID, callData) {
        // Hide completed calls, blank call IDs (errors), future calls, and follow-ups that were never completed (auto remove)
        if (this.complete || (callID[0] == "_") || (callID == "") ||
            (callData.start && (callData.start > today)) ||
            (callData.autoRemove && callData.end && (callData.end < today)))
            return;
        $(".card-header-tabs").append(CallLog.html.tab.replace('CALLID', callID).replace('TABNAME', callData.name || "Unknown"));
    });
}

CallLog.fn.buildAdhocMenu = function() {
    $.each(CallLog.adhoc, function(index, adhoc) {
        $("div.card-header").after(CallLog.html.adhoc.replace('TEXT', 'New ' + adhoc.name).replace('MODALID', adhoc.id));
        $(".adhocButton").first().css('transform', 'translate(' + (790 - $(".adhocButton").first().outerWidth()) + 'px,-34px)');
        $(".formtbody tr").first().append(CallLog.html.adhocModal.replace('MODALID', adhoc.id).replace('MODAL TITLE', 'New ' + adhoc.name));
        $.each(adhoc.reasons, (code, value) => $(`#${adhoc.id} select[name=reason]`).append(`<option value="${code}">${value}</option>`));
        $(`#${adhoc.id} input[name=callDate]`).datepicker();
        $(`#${adhoc.id} input[name=callTime]`).flatpickr({
            enableTime: true,
            noCalendar: true,
            dateFormat: "G:i K",
            allowInput: true,
            static: true
        });
        $(`#${adhoc.id} .callModalSave`).on('click', function() {
            let date = $(`#${adhoc.id} input[name=callDate]`).val(); //redcap format function
            date = date ? formatDate(new Date(date), 'y-MM-dd') : "";
            $.ajax({
                method: 'POST',
                url: CallLog.router,
                data: {
                    route: 'adhocLoad',
                    record: getParameterByName('id'),
                    id: adhoc.id,
                    date: date,
                    time: to24hr($(`#${adhoc.id} input[name=callTime]`).val()),
                    reason: $(`#${adhoc.id} select[name=reason]`).val(),
                    notes: $(`#${adhoc.id} textarea[name=notes]`).val(),
                    reporter: CallLog.userNameMap[$("#username-reference").text()]
                },
                error: (jqXHR, textStatus, errorThrown) => console.log(textStatus + " " + errorThrown),
                success: function(data) {
                    // Data is thrown out
                    window.onbeforeunload = function() {};
                    window.location = (window.location + "").replace('index', 'record_home');
                }
            });
            $(`#${adhoc.id}`).modal('hide');
        });
    });
}

CallLog.fn.addGoToCallListButton = function() {
    $("head").append(CallLog.hideSaveTipCSS);
    $("#__SUBMITBUTTONS__-div .btn-group").hide();
    let el = $("#__SUBMITBUTTONS__-div #submit-btn-saverecord");
    el.clone(true).off().attr('onclick', 'CallLog.fn.goToCallList()').prop('id', 'goto-call-list').text('Save & Go To Call List').insertAfter(el);
    $("#goto-call-list").before('<br>');
}

$(document).ready(function() {

    // Check if there is any metadata to use
    if (isEmpty(CallLog.metadata) && isEmpty(CallLog.adhoc)) {
        // Dodge branching logic, hide everything on the call log
        setTimeout(() => $("#call_hdr_details-tr").nextAll('tr').addBack().hide(), 100);
    }

    // Load some Default CSS if none exists 
    if ($('.formHeader').css('text-align') != 'center')
        $('head').append(CallLog.optionalCSS);

    // Hide a few things for style
    $("#formtop-div").hide();
    $("td.context_msg").hide();
    $(`#${CallLog.static.instrumentLower}_complete-sh-tr`).hide();
    $(`#${CallLog.static.instrumentLower}_complete-tr`).hide();

    //Build out the Notes area
    CallLog.fn.buildNotesArea();

    // If we are on a completed version of the Call Log show a warning, update the details and leave
    if (CallLog.fn.isCompletedLog())
        return;

    // Build out the tabs
    CallLog.fn.buildTabs();

    // If no tabs then we have no calls. 
    if ($(".nav-link").length == 0) {
        setTimeout(function() {
            $("#call_hdr_details-tr").nextAll('tr').addBack().hide();
            $(".formtbody").append(CallLog.html.noCalls);
            $("#formSaveTip").remove();
        }, 100); // dodge branching logic
    }

    // Check if their is any data at all. We need the record to exist to continue
    if (Object.keys(CallLog.data).length == 0)
        return

    //Build out ad-hoc buttons 
    CallLog.fn.buildAdhocMenu();

    // If we have calls then build out the call summary table
    CallLog.fn.buildCallSummaryTable();

    // Fill in Call Details on Tab Change
    $("#CallLogCurrentTime").text($("input[name=call_open_date]").val() + " " + format_time($("input[name=call_open_time]").val()));
    $(".nav-link").on('click', function() {
        $(".nav-link.active").removeClass('active');
        $(this).addClass('active');
        let id = $(this).data('call-id');
        let call = CallLog.metadata[id];
        $("#CallLogCurrentCall").text(call.name);
        $("#CallLogLeaveAMessage").text(CallLog.fn.getLeaveMessage(id));
        $("#CallLogPreviousTime").text(call.instances.length == 0 ? 'None' : CallLog.fn.getPreviousCalldatetime(id));
        $("input[name=call_attempt]").val(call.instances.length + 1).blur();
        $("input[name=call_id]").val(id).blur();
        $("select[name=call_template]").val(call['template']).change();
        $("input[name=call_event_name]").val(id.split('|')[1] || "");
        CallLog.fn.updateCallNotes(id)
    });

    // Update Remaining Call Tasks (Call Outcome changes or Tab changes)
    $("input[name^=call_outcome], .nav-link").on('click', function() {
        if ($("input[name$=call_task_remaining]").is(':visible')) {
            let tasks = CallLog.fn.getPreviousCallTasks($("input[name=call_id]").val());
            $.each(tasks, function() {
                $(`input[name$=call_task_remaining][code=${this}]`).click();
            });
        }
    });

    // Show the "Go to Call List" Button if we came from there
    if (getParameterByName('showReturn')) {
        CallLog.fn.addGoToCallListButton();
    }

    // Select the correct tab based on URL or default
    CallLog.fn.selectTab();

    // Call ID missing failsafe.
    setTimeout(function() {
        if (($(".nav-link").length > 0) && ($("input[name=call_id]").val() == "")) {
            Swal.fire({
                icon: 'warning',
                title: 'Issue Configuring Call Log',
                text: "There was an issue determining what call this log is for. Please refresh the page. If this issue persists contact the REDCap administrator.",
            });
            $(".nav-link:visible").first().click();
        }
    }, 3000);

    // Force Call Incomplete when call back is requested
    $("input[name$=call_requested_callback]").on('click', function() {
        $("input[name^=call_outcome][value=1]").prop('checked', false).prop('disabled', $(this).is(":checked"));
        $("input[name^=call_outcome][value=0]").click();
    });

    // Prevent save without call outcome
    $("#call_outcome-tr").find('input,a').on('click', function() {
        if ($("input[name=call_outcome]").val() == "") {
            $("#submit-btn-saverecord").prop('disabled', true).css('pointer-events', 'none');
            $("#goto-call-list").prop('disabled', true).css('pointer-events', 'none');
            $("#submit-btn-dropdown").parent().find('button').prop('disabled', true).css('pointer-events', 'none');
        } else {
            $("#submit-btn-saverecord").prop('disabled', false).css('pointer-events', 'inherit');
            $("#goto-call-list").prop('disabled', false).css('pointer-events', 'inherit');
            $("#submit-btn-dropdown").parent().find('button').prop('disabled', false).css('pointer-events', 'inherit');
        }
    });
    $("#call_outcome-tr input").first().click()

    // Flag as complete always
    $("select[name=call_log_complete]").val('2');
});