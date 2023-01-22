CallLog.fn = CallLog.fn || {};

// Prevent redcap from creating the tooltip on call log
// Creates too many issues with features
window.displayFormSaveBtnTooltip = function () { }

CallLog.fn.to24hr = function (t) {
    let isPM = t.includes('P');
    t = t.toLowerCase().replaceAll(/[amp ]/g, '');
    if (!isPM) return t;
    let [h, m] = t.split(':');
    return h == 12 ? t : `${parseInt(h) + 12}:${m}`;
}

// Debug function, easily save the metadata after directly editing it
CallLog.fn.saveMetadata = function () {
    CallLog.em.ajax("metadataSave", {
        record: getParameterByName('id'),
        metadata: JSON.stringify(CallLog.metadata)
    }).then(function (response) {
        console.log(response)
    }).catch(function (err) {
        console.log(err);
    });
}

CallLog.fn.goToCallList = function () {
    appendHiddenInputToForm('save-and-redirect', $("#external_modules_panel a:contains('Call List')").prop('href'));
    dataEntrySubmit('submit-btn-savecontinue');
    return false;
}

// Fetch the last known contact time for a given call id
CallLog.fn.getPreviousCalldatetime = function (callID) {
    if (!CallLog.metadata[callID] || isEmpty(CallLog.metadata[callID].instances))
        return "";
    let data = CallLog.data[CallLog.metadata[callID].instances.slice(-1)[0]];
    if (!data)
        return "";
    return formatDate(new Date(data['call_open_date'] + "T00:00:00"), 'MM-dd-y') + " " + format_time(data['call_open_time']);
}

// Fetch the checkboxes of remaining tasks from previous call given a id
CallLog.fn.getPreviousCallTasks = function (callID) {
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
CallLog.fn.getPreviousCallNotes = function (callID) {
    if (!CallLog.metadata[callID] || isEmpty(CallLog.metadata[callID].instances))
        return [];
    let notes = [];
    $.each(CallLog.metadata[callID].instances, function (_, instance) {
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

CallLog.fn.updateCallNotes = function (callID) {
    $('.notesOld').val("")
    $('.notesNew').val($('textarea[name=call_notes]').val());
    $.each(CallLog.fn.getPreviousCallNotes(callID), function () {
        if (this.text == "") return;
        $('.notesOld').val(`${this.dt} ${this.user}: ${this.text} \n\n${$('.notesOld').val()}`.trim());
    });
}

CallLog.fn.selectTab = function () {
    if ($("input[name=call_id]").val() != "")
        return;
    if (getParameterByName('call_id')) {
        $(`.nav-link[data-call-id=${decodeURI(getParameterByName('call_id')).replace(/\|/g, "\\|").replace(/\:/g, "\\:").replace(/\s/g, "\\ ")}]`).click();
    } else {
        $(".nav-link:visible").first().click();
    }
    if (!$(".nav-link.active:visible").length) {
        setTimeout(CallLog.fn.selectTab, 500);
    }
}

CallLog.fn.buildNotesArea = function () {
    $("#call_notes-tr td").hide();
    $("#call_notes-tr").append(CallLog.templates.notesEntry);
    $(".panel-left").resizable({
        handleSelector: ".splitter",
        resizeHeight: false,
        create: (event, ui) => $('.ui-icon-gripsmall-diagonal-se').remove()
    });
    $('.notesNew').on('change', () => $('textarea[name=call_notes]').val($('.notesNew').val()));
}

CallLog.fn.isCompletedLog = function () {
    if ($(`select[name=${CallLog.static.instrument}_complete]`).val() == "0")
        return false;

    // Prevent editing
    $(".formtbody").prepend(CallLog.templates.historicDisplay);
    $("#__SUBMITBUTTONS__-tr").hide();

    // Fill out call details
    let instance = getParameterByName('instance') || 1;
    let id = CallLog.data[instance]['call_id'];
    let data = CallLog.data[instance];
    $("#CallLogCurrentCall").text(CallLog.metadata[id]['name']);
    $("td:contains(Current Caller)").next().text(data['call_open_user_full_name']);
    $("#CallLogCurrentTime").text(formatDate(new Date(data['call_open_date'] + "T00:00:00"), 'MM-dd-y') + " " + format_time(data['call_open_time']));
    $("#CallLogPreviousTime").text("Historic");
    CallLog.fn.updateCallNotes(id);
    return true;
}

CallLog.fn.buildTabs = function () {
    $("#questiontable tr[id]").first().before(CallLog.templates.callWrapper);
    $.each(CallLog.metadata, function (callID, callData) {
        // Hide completed calls, blank call IDs (errors), future calls, and follow-ups that were never completed (auto remove)
        if (this.complete || (callID[0] == "_") || (callID == "") ||
            (callData.start && (callData.start > today)) ||
            (callData.autoRemove && callData.end && (callData.end < today)))
            return;
        $(".card-header-tabs").append(CallLog.templates.callLogTab.replace('CALLID', callID).replace('TABNAME', callData.name || "Unknown"));
    });
}

CallLog.fn.buildAdhocMenu = function () {
    $.each(CallLog.adhoc, function (index, adhoc) {
        $("div.card-header").after(CallLog.templates.adhocBtn.replace('TEXT', 'New ' + adhoc.name).replace('MODALID', adhoc.id));
        $(".adhocButton").first().css('transform', 'translate(' + (790 - $(".adhocButton").first().outerWidth()) + 'px,-34px)');
        $(".formtbody tr").first().append(CallLog.templates.adhocModal.replace('MODALID', adhoc.id).replace('MODAL TITLE', 'New ' + adhoc.name));
        $.each(adhoc.reasons, (code, value) => $(`#${adhoc.id} select[name=reason]`).append(`<option value="${code}">${value}</option>`));
        $(`#${adhoc.id} input[name=callDate]`).datepicker();
        $(`#${adhoc.id} .callModalSave`).on('click', function () {
            let date = $(`#${adhoc.id} input[name=callDate]`).val(); //redcap format function
            date = date ? formatDate(new Date(date), 'y-MM-dd') : "";
            CallLog.em.ajax("newAdhoc", {
                record: getParameterByName('id'),
                id: adhoc.id,
                date: date,
                time: CallLog.fn.to24hr($(`#${adhoc.id} input[name=callTime]`).val()),
                reason: $(`#${adhoc.id} select[name=reason]`).val(),
                notes: $(`#${adhoc.id} textarea[name=notes]`).val(),
                reporter: CallLog.user
            }).then(function (response) {
                console.log(response);
                window.onbeforeunload = function () { };
                window.location = (window.location + "").replace('index', 'record_home');
            }).catch(function (err) {
                console.log(err);
            });
            $(`#${adhoc.id}`).modal('hide');
        });
    });
}

CallLog.fn.addGoToCallListButton = function () {
    $("#__SUBMITBUTTONS__-div .btn-group").hide();
    let el = $("#__SUBMITBUTTONS__-div #submit-btn-saverecord");
    el.clone(true).off().attr('onclick', 'CallLog.fn.goToCallList()').prop('id', 'goto-call-list').text('Save & Go To Call List').insertAfter(el);
    $("#goto-call-list").before('<br>');
}

$(document).ready(function () {

    // Check if there is any metadata to use
    if (isEmpty(CallLog.metadata) && isEmpty(CallLog.adhoc)) {
        // Dodge branching logic, hide everything on the call log
        setTimeout(() => $("#call_hdr_details-tr").nextAll('tr').addBack().hide(), 100);
    }

    // Load some Default CSS if none exists 
    if ($('.formHeader').css('text-align') != 'center') {
        $(".formSxnHeader, .formHeader").addClass('optionalCSS');
    }

    // Hide a few things for style
    $("#formtop-div").hide();
    $("td.context_msg").hide();
    $(`#${CallLog.static.instrument}_complete-sh-tr`).hide();
    $(`#${CallLog.static.instrument}_complete-tr`).hide();

    //Build out the Notes area
    CallLog.fn.buildNotesArea();

    // If we are on a completed version of the Call Log show a warning, update the details and leave
    if (CallLog.fn.isCompletedLog())
        return;

    // Build out the tabs
    CallLog.fn.buildTabs();

    // If no tabs then we have no calls. 
    if ($(".nav-link").length == 0) {
        setTimeout(function () {
            $("#call_hdr_details-tr").nextAll('tr').addBack().hide();
            $(".formtbody").append(CallLog.templates.noCallsDisplay);
            $("#formSaveTip").remove();
        }, 100); // dodge branching logic
    }

    // Check if their is any data at all. We need the record to exist to continue
    if (Object.keys(CallLog.data).length == 0)
        return

    //Build out ad-hoc buttons 
    CallLog.fn.buildAdhocMenu();

    // Fill in Call Details on Tab Change
    $("#CallLogCurrentTime").text($("input[name=call_open_date]").val() + " " + format_time($("input[name=call_open_time]").val()));
    $(".nav-link").on('click', function () {
        $(".nav-link.active").removeClass('active');
        $(this).addClass('active');
        let id = $(this).data('call-id');
        let call = CallLog.metadata[id];
        $("#CallLogCurrentCall").text(call.name);
        $("#CallLogPreviousTime").text(call.instances.length == 0 ? 'None' : CallLog.fn.getPreviousCalldatetime(id));
        $("input[name=call_attempt]").val(call.instances.length + 1).blur();
        $("input[name=call_id]").val(id).blur();
        $("select[name=call_template]").val(call['template']).change();
        $("input[name=call_event_name]").val(id.split('|')[1] || "");
        CallLog.fn.updateCallNotes(id)
    });

    // Update Remaining Call Tasks (Call Outcome changes or Tab changes)
    $("input[name^=call_outcome], .nav-link").on('click', function () {
        if ($("input[name$=call_task_remaining]").is(':visible')) {
            let tasks = CallLog.fn.getPreviousCallTasks($("input[name=call_id]").val());
            $.each(tasks, function () {
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
    setTimeout(function () {
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
    $("input[name$=call_requested_callback]").on('click', function () {
        $("input[name^=call_outcome][value=1]").prop('checked', false).prop('disabled', $(this).is(":checked"));
        $("input[name^=call_outcome][value=0]").click();
    });

    // Prevent save without call outcome
    $("#call_outcome-tr").find('input,a').on('click', function () {
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