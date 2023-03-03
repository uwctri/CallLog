// Override Redcap branching logic
const __dbtf = dbtf;
dbtf = (t, c) => {
    const fields = ['call_left_message', 'call_not_answered', 'call_disconnected', 'call_requested_callback', 'call_outcome'];
    if (ExternalModules.UWMadison.CallLog.disableBranchingLogic && fields.includes(c))
        return false;
    return __dbtf(t, c);
}

(() => {
    // Prevent redcap from creating the tooltip on call log
    // Creates too many issues with features
    window.displayFormSaveBtnTooltip = function () { }

    const module = ExternalModules.UWMadison.CallLog;

    // Debug function, easily save the metadata after directly editing it
    module.saveMetadata = () => {
        module.ajax("metadataSave", {
            record: getParameterByName('id'),
            metadata: JSON.stringify(module.metadata)
        }).then(function (response) {
            console.log(response)
        }).catch(function (err) {
            console.log(err);
        });
    }

    const goToCallList = () => {
        appendHiddenInputToForm('save-and-redirect', $("#external_modules_panel a:contains('Call List')").prop('href'));
        dataEntrySubmit('submit-btn-savecontinue');
        return false;
    }

    // Fetch the last known contact time for a given call id
    const getPreviousCalldatetime = (callID) => {
        if (!module.metadata[callID] || isEmpty(module.metadata[callID].instances)) return "";
        let data = module.data[module.metadata[callID].instances.slice(-1)[0]];
        if (!data) return "";
        return formatDate(new Date(data['call_open_date'] + "T00:00:00"), 'MM-dd-y') + " " + format_time(data['call_open_time']);
    }

    // Fetch the checkboxes of remaining tasks from previous call given a id
    const getPreviousCallTasks = (callID) => {
        let arr = [];
        if (!module.metadata[callID] || isEmpty(module.metadata[callID].instances)) return [];
        let data = module.data[module.metadata[callID].instances.slice(-1)[0]];
        if (!data) return [];
        for (const [key, value] of Object.entries(data['call_task_remaining'])) {
            if (value != "1") continue;
            arr.push(key);
        }
        return arr;
    }

    // Fetch all previous call notes and compile them.
    const getPreviousCallNotes = (callID) => {
        if (!module.metadata[callID] || isEmpty(module.metadata[callID].instances)) return [];
        let notes = [];
        $.each(module.metadata[callID].instances, function (_, instance) {
            let data = module.data[instance];
            if (!data) return;
            notes.push({
                'dt': formatDate(new Date(data['call_open_date'] + "T00:00:00"), 'MM-dd-y') + " " + format_time(data['call_open_time']),
                'text': data['call_notes'],
                'user': data['call_open_user_full_name']
            });
        });
        return notes;
    }

    const updateCallNotes = (callID) => {
        $('.notesOld').val("")
        $('.notesNew').val($('textarea[name=call_notes]').val());
        $.each(getPreviousCallNotes(callID), function () {
            if (this.text == "") return;
            $('.notesOld').val(`${this.dt} ${this.user}: ${this.text} \n\n${$('.notesOld').val()}`.trim());
        });
    }

    const selectTab = () => {
        if ($("input[name=call_id]").val() != "")
            return;
        if (getParameterByName('call_id')) {
            $(`.nav-link[data-call-id=${decodeURI(getParameterByName('call_id')).replace(/\|/g, "\\|").replace(/\:/g, "\\:").replace(/\s/g, "\\ ")}]`).click();
        } else {
            $(".nav-link:visible").first().click();
        }
        if (!$(".nav-link.active:visible").length) {
            setTimeout(selectTab, 500);
        }
    }

    const buildNotesArea = () => {
        $("#call_notes-tr td").hide();
        $("#call_notes-tr").append(module.templates.notesEntry);
        $(".panel-left").resizable({
            handleSelector: ".splitter",
            resizeHeight: false,
            create: (event, ui) => $('.ui-icon-gripsmall-diagonal-se').remove()
        });
        $('.notesNew').on('change', () => $('textarea[name=call_notes]').val($('.notesNew').val()));
    }

    const isCompletedLog = () => {
        if ($(`select[name=${module.static.instrument}_complete]`).val() == "0")
            return false;

        // Prevent editing
        $(".formtbody").prepend(module.templates.historicDisplay);
        $("#__SUBMITBUTTONS__-tr").hide();

        // Fill out call details for the completed call
        let instance = getParameterByName('instance') || 1;
        let id = module.data[instance]['call_id'];
        let data = module.data[instance];
        $("#CallLogCurrentCall").text(module.metadata[id]['name']);
        $("td:contains(Current Caller)").next().text(data['call_open_user_full_name']);
        $("#CallLogCurrentTime").text(formatDate(new Date(data['call_open_date'] + "T00:00:00"), module.format.date) + " " + format_time(data['call_open_time']));
        $("#CallLogPreviousTime").text("Historic");
        updateCallNotes(id);
        return true;
    }

    const buildTabs = () => {
        $("#questiontable tr[id]").first().before(module.templates.callWrapper);
        $.each(module.metadata, function (callID, callData) {
            // Hide completed calls, blank call IDs (errors), future calls, and follow-ups that were never completed (auto remove)
            if (this.complete || (callID[0] == "_") || (callID == "") ||
                (callData.start && (callData.start > today)) ||
                (callData.autoRemove && callData.end && (callData.end < today)))
                return;
            $(".card-header-tabs").append(module.templates.callLogTab.replace('CALLID', callID).replace('TABNAME', callData.name || "Unknown"));
        });
    }

    const buildAdhocMenu = () => {
        $.each(module.adhoc, function (index, adhoc) {
            $("div.card-header").after(module.templates.adhocBtn.replace('TEXT', 'New ' + adhoc.name).replace('MODALID', adhoc.id));
            $(".adhocButton").first().css('transform', 'translate(' + (790 - $(".adhocButton").first().outerWidth()) + 'px,-34px)');
            $(".formtbody tr").first().append(module.templates.adhocModal.replace('MODALID', adhoc.id).replace('MODAL TITLE', 'New ' + adhoc.name));
            $.each(adhoc.reasons, (code, value) => $(`#${adhoc.id} select[name=reason]`).append(`<option value="${code}">${value}</option>`));
            $(`#${adhoc.id} input[name=callDate]`).datepicker();
            $(`#${adhoc.id} .callModalSave`).on('click', function () {
                let date = $(`#${adhoc.id} input[name=callDate]`).val(); //redcap format function
                date = date ? formatDate(new Date(date), 'y-MM-dd') : "";
                module.ajax("newAdhoc", {
                    record: getParameterByName('id'),
                    id: adhoc.id,
                    date: date,
                    time: $(`#${adhoc.id} input[name=callTime]`).val(),
                    reason: $(`#${adhoc.id} select[name=reason]`).val(),
                    notes: $(`#${adhoc.id} textarea[name=notes]`).val(),
                    reporter: module.user
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

    const addGoToCallListButton = () => {
        $("#__SUBMITBUTTONS__-div .btn-group").hide();
        let el = $("#__SUBMITBUTTONS__-div #submit-btn-saverecord");
        el.clone(true).off().prop('id', 'goto-call-list').text('Save & Go To Call List').insertAfter(el);
        el.next().on('click', goToCallList);
        $("#goto-call-list").before('<br>');
    }

    const buildCallLog = () => {
        // Check if there is any metadata to use
        if (isEmpty(module.metadata) && isEmpty(module.adhoc)) {
            module.disableBranchingLogic = true;
            $("#call_hdr_details-tr").nextAll('tr').addBack().hide();
        }

        // Load some Default CSS if none exists 
        if ($('.formHeader').css('text-align') != 'center')
            $(".formSxnHeader, .formHeader").addClass('optionalCSS');

        // Hide a few things for style
        $("#formtop-div").hide();
        $("td.context_msg").hide();
        $(`#${module.static.instrument}_complete-sh-tr`).hide();
        $(`#${module.static.instrument}_complete-tr`).hide();

        //Build out the Notes area
        buildNotesArea();

        // If we are on a completed version of the Call Log show a warning, update the details and leave
        if (isCompletedLog()) return;

        // Build out the tabs
        buildTabs();

        // If no tabs then we have no calls. 
        if ($(".nav-link").length == 0) {
            module.disableBranchingLogic = true;
            $("#call_hdr_details-tr").nextAll('tr').addBack().hide();
            $(".formtbody").append(module.templates.noCallsDisplay);
            $("#formSaveTip").remove();
        }

        // Check if their is any data at all. We need the record to exist to continue
        if (Object.keys(module.data).length == 0) return

        //Build out ad-hoc buttons 
        buildAdhocMenu();

        // Fill in Call Details on Tab Change
        setTimeout(() => { // Wait for action tags to run
            $("#CallLogCurrentTime").text(
                $("input[name=call_open_date]").val() + " " + format_time($("input[name=call_open_time]").val())
            );
        }, 200)
        $(".nav-link").on('click', (event) => {
            const el = event.target;
            $(".nav-link.active").removeClass('active');
            $(el).addClass('active');
            let id = $(el).data('call-id');
            let call = module.metadata[id];
            $("#CallLogCurrentCall").text(call.name);
            $("#CallLogPreviousTime").text(call.instances.length == 0 ? 'None' : getPreviousCalldatetime(id));
            $("input[name=call_attempt]").val(call.instances.length + 1).blur();
            $("input[name=call_id]").val(id).blur();
            $("select[name=call_template]").val(call['template']).change();
            $("input[name=call_event_name]").val(id.split('|')[1] || "");
            updateCallNotes(id)
        });

        // Update Remaining Call Tasks (Call Outcome changes or Tab changes)
        $("input[name^=call_outcome], .nav-link").on('click', () => {
            if (!$("input[name$=call_task_remaining]").is(':visible')) return;
            const id = $("input[name=call_id]").val();
            getPreviousCallTasks(id).forEach((code) => {
                $(`input[name$=call_task_remaining][code=${code}]`).click();
            });
        });

        // Show the "Go to Call List" Button if we came from there
        if (getParameterByName('showReturn'))
            addGoToCallListButton();

        // Select the correct tab based on URL or default (wait for pipes to load)
        setTimeout(selectTab, 100);

        // Call ID missing failsafe.
        setTimeout(() => {
            if (($(".nav-link").length == 0) || ($("input[name=call_id]").val() != "")) return;
            Swal.fire({
                icon: 'warning',
                title: 'Issue Configuring Call Log',
                text: "There was an issue determining what call this log is for. Please refresh the page. If this issue persists contact the REDCap administrator.",
            });
            $(".nav-link:visible").first().click();
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
    }

    buildCallLog();

})();