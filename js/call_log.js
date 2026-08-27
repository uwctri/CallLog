(() => {
    const module = ExternalModules.UWMadison.CallLog;
    const getParam = (name) => (module.utils && module.utils.getParam) ? module.utils.getParam(name) : (typeof window.getParameterByName === 'function' ? window.getParameterByName(name) : null);

    if (typeof window.displayFormSaveBtnTooltip === 'function') {
        window.displayFormSaveBtnTooltip = function () { };
    }

    if (typeof window.dbtf === 'function' && !window.__callLogDbtfOverridden) {
        window.__callLogDbtfOverridden = true;
        const origDbtf = window.dbtf;
        window.dbtf = function (t, c) {
            const fields = ['call_left_message', 'call_not_answered', 'call_disconnected', 'call_requested_callback', 'call_outcome'];
            if (module.disableBranchingLogic && fields.includes(c)) {
                return false;
            }
            return origDbtf.apply(this, arguments);
        };
    }

    module.saveMetadata = () => {
        module.ajax("metadataSave", {
            record: getParam('id'),
            metadata: JSON.stringify(module.metadata)
        }).then(function (response) {
            console.log(response);
        }).catch(function (err) {
            console.error(err);
        });
    };

    const goToCallList = () => {
        const link = $("#external_modules_panel a:contains('Call List')").prop('href');
        if (typeof appendHiddenInputToForm === 'function' && typeof dataEntrySubmit === 'function') {
            appendHiddenInputToForm('save-and-redirect', link);
            dataEntrySubmit('submit-btn-savecontinue');
        } else {
            window.location.href = link;
        }
        return false;
    };

    const getPreviousCalldatetime = (callID) => {
        if (!module.metadata[callID] || !module.metadata[callID].instances || !module.metadata[callID].instances.length) return "";
        let lastInst = module.metadata[callID].instances.slice(-1)[0];
        let data = module.data[lastInst];
        if (!data) return "";
        return (data['call_open_date'] || '') + " " + (data['call_open_time'] || '');
    };

    const getPreviousCallTasks = (callID) => {
        let arr = [];
        if (!module.metadata[callID] || !module.metadata[callID].instances || !module.metadata[callID].instances.length) return [];
        let lastInst = module.metadata[callID].instances.slice(-1)[0];
        let data = module.data[lastInst];
        if (!data || !data['call_task_remaining']) return [];
        for (const [key, value] of Object.entries(data['call_task_remaining'])) {
            if (value === "1") arr.push(key);
        }
        return arr;
    };

    const getPreviousCallNotes = (callID) => {
        if (!module.metadata[callID] || !module.metadata[callID].instances) return [];
        let notes = [];
        $.each(module.metadata[callID].instances, function (_, instance) {
            let data = module.data[instance];
            if (!data) return;
            notes.push({
                'dt': (data['call_open_date'] || '') + " " + (data['call_open_time'] || ''),
                'text': data['call_notes'] || '',
                'user': data['call_open_user_full_name'] || ''
            });
        });
        return notes;
    };

    const updateCallNotes = (callID) => {
        $('.notesOld').val("");
        $('.notesNew').val($('textarea[name=call_notes]').val() || '');
        $.each(getPreviousCallNotes(callID), function () {
            if (!this.text) return;
            $('.notesOld').val(`${this.dt} ${this.user}: ${this.text}\n\n${$('.notesOld').val()}`.trim());
        });
    };

    const selectTab = () => {
        if ($("input[name=call_id]").val() !== "") return;
        if (getParam('call_id')) {
            const rawId = decodeURIComponent(getParam('call_id'));
            $(`.callTab`).filter((_, el) => $(el).data('call-id') === rawId).click();
        } else {
            $(".callTab:visible").first().click();
        }
        if (!$(".callTab.active:visible").length) {
            setTimeout(selectTab, 500);
        }
    };

    const buildNotesArea = () => {
        $("#call_notes-tr td").hide();
        if (module.renderers && module.renderers.renderNotesEntry) {
            $("#call_notes-tr").append(module.renderers.renderNotesEntry());
        }
        if (typeof $.fn.resizable === 'function') {
            $(".panel-left").resizable({
                handleSelector: ".splitter",
                resizeHeight: false,
                create: () => $('.ui-icon-gripsmall-diagonal-se').remove()
            });
        }
        $('.notesNew').on('change input', () => $('textarea[name=call_notes]').val($('.notesNew').val()));
    };

    const isCompletedLog = () => {
        if ($(`select[name=${module.static.instrument}_complete]`).val() === "0") return false;

        if (module.renderers && module.renderers.renderHistoricDisplay) {
            $(".formtbody").prepend(module.renderers.renderHistoricDisplay());
        }
        $("#__SUBMITBUTTONS__-tr").hide();

        let instance = getParam('instance') || 1;
        let data = (module.data && module.data[instance]) ? module.data[instance] : {};
        let id = data['call_id'];
        let meta = (id && module.metadata) ? module.metadata[id] : null;

        if (meta) $("#CallLogCurrentCall").text(meta['name'] || '');
        $("td:contains(Current Caller)").next().text(data['call_open_user_full_name'] || '');
        $("#CallLogCurrentTime").text((data['call_open_date'] || '') + " " + (data['call_open_time'] || ''));
        $("#CallLogPreviousTime").text("Historic");
        if (id) updateCallNotes(id);
        return true;
    };

    const buildTabs = () => {
        if (!module.renderers || !module.renderers.renderCallWrapper || !module.renderers.renderCallLogTab) return;
        $("#questiontable tr[id]").first().before(module.renderers.renderCallWrapper());
        const today = new Date().toISOString().split('T')[0];

        $.each(module.metadata || {}, function (callID, callData) {
            if (callData.complete || (callID[0] === "_") || (callID === "") ||
                (callData.start && (callData.start > today)) ||
                (callData.autoRemove && callData.end && (callData.end < today))) {
                return;
            }
            const tabHtml = module.renderers.renderCallLogTab(callID, callData.name || "Unknown");
            $(".card-header-tabs").append(tabHtml);
        });
    };

    const buildAdhocMenu = () => {
        if (!module.adhoc || !module.renderers || !module.renderers.renderAdhocBtn) return;
        $.each(module.adhoc, function (index, adhoc) {
            $("div.card-header").after(
                module.renderers.renderAdhocBtn(adhoc.id, 'New ' + adhoc.name)
            );
            $(".adhocButton").first().css('transform', 'translate(' + (790 - $(".adhocButton").first().outerWidth()) + 'px,-34px)');
            $(".formtbody tr").first().append(
                module.renderers.renderAdhocModal(adhoc.id, 'New ' + adhoc.name)
            );

            $.each(adhoc.reasons || {}, (code, value) => {
                $(`#${adhoc.id} select[name=reason]`).append(`<option value="${code}">${value}</option>`);
            });

            if (typeof $.fn.datepicker === 'function') {
                $(`#${adhoc.id} input[name=callDate]`).datepicker();
            }

            $(`#${adhoc.id} .callModalSave`).on('click', function () {
                let date = $(`#${adhoc.id} input[name=callDate]`).val();
                module.ajax("newAdhoc", {
                    record: getParam('id'),
                    id: adhoc.id,
                    date: date,
                    time: $(`#${adhoc.id} input[name=callTime]`).val(),
                    reason: $(`#${adhoc.id} select[name=reason]`).val(),
                    notes: ($(`#${adhoc.id} textarea[name=notes]`).val() || '').replace(/"/g, "'"),
                    reporter: module.user
                }).then(function () {
                    window.onbeforeunload = function () { };
                    window.location = (window.location + "").replace('index', 'record_home');
                }).catch(function (err) {
                    console.error(err);
                });
                $(`#${adhoc.id}`).modal('hide');
            });
        });
    };

    const addGoToCallListButton = () => {
        $("#__SUBMITBUTTONS__-div .btn-group").hide();
        let el = $("#__SUBMITBUTTONS__-div #submit-btn-saverecord");
        if (el.length) {
            el.clone(true).off().prop('id', 'goto-call-list').text('Save & Go To Call List').insertAfter(el);
            $("#goto-call-list").on('click', goToCallList).before('<br>');
        }
    };

    const buildCallLog = () => {
        if (!module.metadata || (Object.keys(module.metadata).length === 0 && (!module.adhoc || Object.keys(module.adhoc).length === 0))) {
            module.disableBranchingLogic = true;
            $("#call_hdr_details-tr").nextAll('tr').addBack().hide();
        }

        if ($('.formHeader').css('text-align') !== 'center') {
            $(".formSxnHeader, .formHeader").addClass('optionalCSS');
        }

        $("#formtop-div").hide();
        $("td.context_msg").hide();
        $(`#${module.static.instrument}_complete-sh-tr`).hide();
        $(`#${module.static.instrument}_complete-tr`).hide();

        buildNotesArea();

        if (isCompletedLog()) return;

        buildTabs();

        if ($(".callTab").length === 0) {
            module.disableBranchingLogic = true;
            $("#call_hdr_details-tr").nextAll('tr').addBack().hide();
            if (module.renderers && module.renderers.renderNoCallsDisplay) {
                $(".formtbody").append(module.renderers.renderNoCallsDisplay());
            }
            $("#formSaveTip").remove();
        }

        if (!module.data || Object.keys(module.data).length === 0) return;

        buildAdhocMenu();

        setTimeout(() => {
            $("#CallLogCurrentTime").text(
                ($("input[name=call_open_date]").val() || '') + " " + ($("input[name=call_open_time]").val() || '')
            );
        }, 200);

        $(".callTab").on('click', (event) => {
            const el = event.currentTarget;
            $(".callTab.active").removeClass('active');
            $(el).addClass('active');
            let id = $(el).data('call-id');
            let call = module.metadata[id] || {};
            $("#CallLogCurrentCall").text(call.name || '');
            $("#CallLogPreviousTime").text((!call.instances || call.instances.length === 0) ? 'None' : getPreviousCalldatetime(id));
            $("input[name=call_attempt]").val((call.instances ? call.instances.length : 0) + 1).blur();
            $("input[name=call_id]").val(id).blur();
            $("select[name=call_template]").val(call['template'] || '').change();
            $("input[name=call_event_name]").val(id.split('|')[1] || "");
            updateCallNotes(id);
        });

        $("input[name^=call_outcome], .callTab").on('click', () => {
            if (!$("input[name$=call_task_remaining]").is(':visible')) return;
            const id = $("input[name=call_id]").val();
            getPreviousCallTasks(id).forEach((code) => {
                $(`input[name$=call_task_remaining][code=${code}]`).click();
            });
        });

        if (getParam('showReturn')) {
            addGoToCallListButton();
        }

        setTimeout(selectTab, 100);

        $("input[name$=call_requested_callback]").on('click', function () {
            $("input[name^=call_outcome][value=1]").prop('checked', false).prop('disabled', $(this).is(":checked"));
            $("input[name^=call_outcome][value=0]").click();
        });

        $("#call_outcome-tr").find('input,a').on('click', function () {
            const hasVal = $("input[name=call_outcome]").val() !== "";
            $("#submit-btn-saverecord, #goto-call-list").prop('disabled', !hasVal).css('pointer-events', hasVal ? 'inherit' : 'none');
            $("#submit-btn-dropdown").parent().find('button').prop('disabled', !hasVal).css('pointer-events', hasVal ? 'inherit' : 'none');
        });
        $("#call_outcome-tr input").first().click();

        $("select[name=call_log_complete]").val('2');
    };

    buildCallLog();
})();