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

    // Format a date+time pair using the project's configured dateTimeFormat (via utils),
    // falling back to a plain concatenation if utils is unavailable.
    const formatCallDatetime = (date, time) => {
        const raw = [date, time].filter(Boolean).join(' ').trim();
        if (!raw) return '';
        if (module.utils && module.utils.formatDateTime) {
            return module.utils.formatDateTime(raw, !!time);
        }
        return raw;
    };

    const getPreviousCalldatetime = (callID) => {
        const instances = getCallInstanceIds(callID);
        if (!instances.length) return "";
        let lastInst = instances.slice(-1)[0];
        let data = module.data[lastInst];
        if (!data) return "";
        return formatCallDatetime(data['call_open_date'] || '', data['call_open_time'] || '');
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

    const getCallInstanceIds = (callID) => {
        const metadataCall = module.metadata && module.metadata[callID];
        const instances = metadataCall && Array.isArray(metadataCall.instances)
            ? metadataCall.instances.slice()
            : [];
        const knownInstances = new Set(instances.map(String));

        $.each(module.data || {}, function (instance, data) {
            if (data && String(data['call_id'] || '') === String(callID) && !knownInstances.has(String(instance))) {
                instances.push(instance);
            }
        });
        return instances;
    };

    const getPreviousCallNotes = (callID) => {
        let notes = [];
        const instances = getCallInstanceIds(callID);

        $.each(instances, function (_, instance) {
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
        const prevNotes = getPreviousCallNotes(callID);
        let oldNotesText = "";
        $.each(prevNotes, function () {
            if (!this.text) return;
            const header = `${this.dt} ${this.user}`.trim();
            const entry = header ? `[${header}]\n${this.text}` : this.text;
            oldNotesText = oldNotesText ? `${oldNotesText}\n${entry}` : entry;
        });
        $('.notesOld').val(oldNotesText);
    };

    const selectTab = () => {
        const currentVal = $("input[name=call_id]").val();
        if (currentVal !== "") {
            const $match = $(`.callTab`).filter((_, el) => String($(el).data('call-id')) === String(currentVal));
            if ($match.length) {
                $match.first().click();
                return;
            }
        }
        if (getParam('call_id')) {
            const rawId = decodeURIComponent(getParam('call_id'));
            const $paramTab = $(`.callTab`).filter((_, el) => String($(el).data('call-id')) === String(rawId));
            if ($paramTab.length) {
                $paramTab.first().click();
                return;
            }
        }
        $(".callTab:visible").first().click();
        if (!$(".callTab.active:visible").length && $(".callTab:visible").length) {
            setTimeout(selectTab, 300);
        }
    };

    const buildNotesArea = () => {
        $("#call_notes-tr").hide();
        if (!$("#call_notes_custom-tr").length && module.renderers && module.renderers.renderNotesEntry) {
            $("#call_notes-tr").after(module.renderers.renderNotesEntry());
        }
        $('.notesNew').off('input change').on('input change', function () {
            $('textarea[name=call_notes]').val($(this).val());
        });
    };

    const getCallName = (callID, callData) => {
        if (callData && callData.name && callData.name !== 'Unknown' && String(callData.name).trim() !== '') {
            return String(callData.name).trim();
        }
        const cleanId = String(callID || '');
        const baseId = cleanId.split('|')[0].split('||')[0];
        if (module.callNames && module.callNames[baseId]) {
            return module.callNames[baseId];
        }
        if (module.callNames && module.callNames[cleanId]) {
            return module.callNames[cleanId];
        }
        if (callData && callData.template) {
            const templateNames = {
                'new': 'New Entry',
                'reminder': 'Reminder',
                'followup': 'Follow-up',
                'mcv': 'Missed/Cancelled Visit',
                'nts': 'Need to Schedule',
                'adhoc': 'Adhoc Call',
                'visit': 'Phone Visit'
            };
            if (templateNames[callData.template]) return templateNames[callData.template];
        }
        if (cleanId.includes('||')) {
            return 'Adhoc Call';
        }
        return (baseId && baseId !== 'call_new') ? baseId : 'Call';
    };

    const getOrdinal = (n) => {
        const s = ["th", "st", "nd", "rd"];
        const v = n % 100;
        return n + (s[(v - 20) % 10] || s[v] || s[0]);
    };

    /**
     * Populates the hidden form fields call_template, call_event_name/call_event,
     * call_id, and call_attempt based on the selected call tab and its metadata.
     *
     * @param {string} id       - The call ID from data-call-id (e.g. "call_1", "call_2|event_1_arm_1", "adhoc||xyz")
     * @param {object} call     - The call metadata object from module.metadata[id]
     * @param {number} attempt  - The attempt number (instances.length + 1)
     */
    const populateCallFormFields = (id, call, attempt) => {
        const callObj = call || {};
        const cleanId = String(id || '');
        const baseId = cleanId.split('|')[0].split('||')[0];

        // --- Resolve template ---
        let template = callObj.template || '';
        if (!template && module.callTemplates) {
            template = module.callTemplates[baseId] || module.callTemplates[cleanId] || '';
        }
        if (!template) {
            template = cleanId.includes('||') ? 'adhoc' : 'new';
        }

        // --- Resolve event name ---
        // Priority: pipe-encoded event in id → call.event_id/call.event → currentEventName
        let eventName = '';
        const pipeParts = cleanId.split('|');
        if (pipeParts.length > 1 && !cleanId.includes('||')) {
            // e.g. "call_remind|event_1_arm_1"
            eventName = pipeParts[1] || '';
        }
        if (!eventName) {
            const rawEvent = callObj.event_id || callObj.event || '';
            if (rawEvent) {
                // If numeric, look up unique event name from passed map
                if (/^\d+$/.test(String(rawEvent)) && module.uniqueEventNames) {
                    eventName = module.uniqueEventNames[rawEvent] || String(rawEvent);
                } else {
                    eventName = String(rawEvent);
                }
            }
        }
        if (!eventName) {
            eventName = module.currentEventName || '';
        }

        // --- Set fields ---
        $("input[name=call_id]").val(cleanId).trigger('blur');
        $("input[name=call_attempt]").val(attempt || 1).trigger('blur');

        const $tmpl = $("[name=call_template]");
        if ($tmpl.is('select')) {
            $tmpl.val(template).trigger('change').trigger('blur');
        } else {
            $tmpl.val(template).trigger('blur');
        }

        $("[name=call_event], [name=call_event_name]").val(eventName).trigger('change').trigger('blur');
    };

    const isScriptEmpty = (html) => {
        if (!html) return true;
        const stripped = String(html)
            .replace(/<img[^>]*>/gi, 'IMG')
            .replace(/<[^>]+>/g, '')
            .replace(/&nbsp;/gi, ' ')
            .trim();
        return stripped === '';
    };

    const updateCallScript = (callId) => {
        const $hdr = $("#call_hdr_script-tr");
        const $row = $("#call_script-tr");
        const $target = $row.find('[data-mlm-field="call_script"]').length
            ? $row.find('[data-mlm-field="call_script"]')
            : $row.find('td');

        if (!callId) {
            $hdr.hide();
            $row.hide();
            $target.empty();
            return;
        }

        const cleanId = String(callId || '');
        const baseId = cleanId.split('|')[0].split('||')[0];
        const callData = (module.metadata && module.metadata[callId]) || {};

        let rawScript = '';
        if (module.callScripts && module.callScripts[callId] !== undefined && module.callScripts[callId] !== '') {
            rawScript = module.callScripts[callId];
        } else if (module.callScripts && module.callScripts[baseId] !== undefined && module.callScripts[baseId] !== '') {
            rawScript = module.callScripts[baseId];
        } else if (callData && callData.script) {
            rawScript = callData.script;
        } else if (module.adhoc && module.adhoc[baseId] && module.adhoc[baseId].script) {
            rawScript = module.adhoc[baseId].script;
        }

        if (isScriptEmpty(rawScript)) {
            $hdr.hide();
            $row.hide();
            $target.empty();
            return;
        }

        const participantName = module.participantName || '';
        const callName = getCallName(callId, callData);
        const attempt = (callData.instances ? callData.instances.length : 0) + 1;
        const attemptOrdinal = getOrdinal(attempt);
        const duration = (module.callDurations && (module.callDurations[baseId] || module.callDurations[callId])) || 30;

        let apptDate = '';
        let apptTime = '';
        if (callData.appt) {
            const parts = callData.appt.split(' ');
            apptDate = parts[0] || '';
            apptTime = parts[1] || '';
        } else if (callData.contactOn) {
            apptDate = callData.contactOn;
        }

        const reason = callData.reason || '';

        let processedScript = String(rawScript)
            .replace(/\{\{\s*participant_name\s*\}\}/gi, participantName)
            .replace(/\{\{\s*call_name\s*\}\}/gi, callName)
            .replace(/\{\{\s*call_date\s*\}\}/gi, apptDate)
            .replace(/\{\{\s*call_time\s*\}\}/gi, apptTime)
            .replace(/\{\{\s*expected_duration\s*\}\}/gi, duration)
            .replace(/\{\{\s*attempt_num\s*\}\}/gi, attemptOrdinal)
            .replace(/\{\{\s*reason\s*\}\}/gi, reason)
            .replace(/\{\{\s*name\s*\}\}/gi, participantName || callName);

        $hdr.show();
        $row.show();
        $target.html(processedScript);
    };

    module.updateCallScript = updateCallScript;

    const isHistoricLog = () => {
        let instance = getParam('instance') || 1;
        let data = (module.data && module.data[instance]) ? module.data[instance] : {};
        const outcome = Array.isArray(data['call_outcome']) ? data['call_outcome'][1] : data['call_outcome'];
        if (String(outcome || '') !== '1') return false;

        if (!$("#historic-display-tr").length && module.renderers && module.renderers.renderHistoricDisplay) {
            $(".formtbody").prepend(module.renderers.renderHistoricDisplay());
        }

        let id = data['call_id'];
        let meta = (id && module.metadata) ? module.metadata[id] : null;

        if (id) {
            $("#CallLogCurrentCall").text(getCallName(id, meta));
            updateCallNotes(id);
            updateCallScript(id);
        } else {
            updateCallScript('');
        }
        $("td:contains(Current Caller)").next().text(data['call_open_user_full_name'] || '');
        $("#CallLogCurrentTime").text(formatCallDatetime(data['call_open_date'] || '', data['call_open_time'] || ''));
        $("#CallLogPreviousTime").text("Historic");
        return false;
    };

    const buildTabs = () => {
        if (!module.renderers || !module.renderers.renderCallWrapper || !module.renderers.renderCallLogTab) return;
        if (!$("#call_log_wrapper-tr").length) {
            const $targetRow = $("#questiontable tr[id]").first().length
                ? $("#questiontable tr[id]").first()
                : $(".formtbody tr[id]").first();
            $targetRow.before(module.renderers.renderCallWrapper());
        }
        const today = new Date().toISOString().split('T')[0];
        const currentCallId = $("input[name=call_id]").val();
        const paramCallId = getParam('call_id') ? decodeURIComponent(getParam('call_id')) : null;

        $(".card-header-tabs").empty();
        $.each(module.metadata || {}, function (callID, callData) {
            const isCurrentCall = (callID === currentCallId || (paramCallId && callID === paramCallId));
            if (!isCurrentCall) {
                if (callData.complete || (callID[0] === "_") || (callID === "") ||
                    (callData.start && (callData.start > today)) ||
                    (callData.autoRemove && callData.end && (callData.end < today))) {
                    return;
                }
            }
            const tabName = getCallName(callID, callData);
            const tabHtml = module.renderers.renderCallLogTab(callID, tabName, !!callData.complete);
            $(".card-header-tabs").append(tabHtml);
        });
    };

    const buildAdhocMenu = () => {
        $(".call-adhoc-actions").empty();
        $("#adhocModal").remove();

        if (!module.adhoc || !module.renderers || !module.renderers.renderAdhocBtn || !module.renderers.renderAdhocModal) {
            return;
        }

        const adhocKeys = Object.keys(module.adhoc);
        if (adhocKeys.length === 0) {
            return;
        }

        const hasMultiple = adhocKeys.length > 1;

        // Render single button: "New Adhoc Call"
        $(".call-adhoc-actions").append(
            module.renderers.renderAdhocBtn('New Adhoc Call')
        );

        // Render modal
        $("body").append(
            module.renderers.renderAdhocModal(hasMultiple, 'New Adhoc Call')
        );

        const $modal = $("#adhocModal");
        const $typeSelect = $modal.find('select[name=callType]');
        const $reasonSelect = $modal.find('select[name=reason]');

        // Populate call types
        $typeSelect.empty();
        $.each(module.adhoc, function (id, cfg) {
            const optName = cfg.name || id;
            $typeSelect.append(`<option value="${id}">${optName}</option>`);
        });

        // Function to update reason dropdown based on selected call type
        const updateReasons = () => {
            const selectedId = $typeSelect.val() || adhocKeys[0];
            const cfg = module.adhoc[selectedId] || {};
            $reasonSelect.empty();
            $.each(cfg.reasons || {}, (code, val) => {
                $reasonSelect.append(`<option value="${code}">${val}</option>`);
            });
        };

        $typeSelect.off('change').on('change', updateReasons);

        // Function to initialize/reset modal fields
        const initModalFields = () => {
            const now = new Date();
            const dateStr = now.toISOString().split('T')[0];
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const timeStr = `${hours}:${minutes}`;

            $modal.find('input[name=callDate]').val(dateStr);
            $modal.find('input[name=callTime]').val(timeStr);
            $modal.find('textarea[name=notes]').val('');
            if (adhocKeys.length > 0) {
                $typeSelect.val(adhocKeys[0]);
                updateReasons();
            }
        };

        // Initialize fields now
        initModalFields();

        // Also re-init on modal open
        $(".adhocButton").off('click.adhocInit').on('click.adhocInit', function () {
            initModalFields();
            if (typeof $.fn.modal === 'function') {
                $modal.modal('show');
            }
        });

        if (typeof $.fn.datepicker === 'function') {
            $modal.find('input[name=callDate]').datepicker();
        }

        // Save handler
        $modal.find('.callModalSave').off('click').on('click', function () {
            const selectedTypeId = $typeSelect.val() || adhocKeys[0];
            if (!selectedTypeId) return;

            const dateVal = $modal.find('input[name=callDate]').val();
            const timeVal = $modal.find('input[name=callTime]').val();
            const reasonVal = $reasonSelect.val();
            const notesVal = ($modal.find('textarea[name=notes]').val() || '').replace(/"/g, "'");

            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

            module.ajax("newAdhoc", {
                record: getParam('id'),
                id: selectedTypeId,
                date: dateVal,
                time: timeVal,
                reason: reasonVal,
                notes: notesVal,
                reporter: module.user
            }).then(function () {
                window.onbeforeunload = function () { };
                window.location = (window.location + "").replace('index', 'record_home');
            }).catch(function (err) {
                console.error(err);
                $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Call');
            });

            $modal.modal('hide');
        });
    };

    const addGoToCallListButton = () => {
        $("#__SUBMITBUTTONS__-div .btn-group").hide();
        let el = $("#__SUBMITBUTTONS__-div #submit-btn-saverecord");
        if (el.length && !$("#goto-call-list").length) {
            el.clone(true).off().prop('id', 'goto-call-list').text('Save & Go To Call List').insertAfter(el);
            $("#goto-call-list").on('click', goToCallList).before('<br>');
        }
    };

    const buildCallLog = () => {
        if (!module.metadata || (Object.keys(module.metadata).length === 0 && (!module.adhoc || Object.keys(module.adhoc).length === 0))) {
            module.disableBranchingLogic = true;
            $("#call_hdr_details-tr").nextAll('tr').addBack().hide();
        }

        if ($('.call-header, .callHeader, .formHeader').css('text-align') !== 'center') {
            $(".call-section-header, .callSxnHeader, .call-header, .callHeader, .formSxnHeader, .formHeader").addClass('optionalCSS');
        }

        $("#formtop-div").hide();
        $("td.context_msg").hide();
        $("#record_id-tr").hide();
        $("#formSectionNavigator").hide();
        $(`#${module.static.instrument}_complete-sh-tr`).hide();
        $(`#${module.static.instrument}_complete-tr`).hide();
        $("#call_hdr_script-tr, #call_script-tr").hide();

        buildNotesArea();

        isHistoricLog();

        buildTabs();
        buildAdhocMenu();

        if ($(".callTab").length === 0) {
            module.disableBranchingLogic = true;
            $("#call_hdr_details-tr").nextAll('tr').addBack().hide();
            const adhocCount = module.adhoc ? Object.keys(module.adhoc).length : 0;
            if (!adhocCount) {
                $("#call_log_wrapper-tr").hide();
            }
            if (module.renderers && module.renderers.renderNoCallsDisplay) {
                $(".formtbody").append(module.renderers.renderNoCallsDisplay());
            }
            $("#formSaveTip").remove();
            updateCallScript('');
        }

        // Bind tab click handler and schedule initial tab selection regardless of
        // whether there is existing call data (fixes first-call-ever scenario).
        setTimeout(() => {
            $("#CallLogCurrentTime").text(
                ($("input[name=call_open_date]").val() || '') + " " + ($("input[name=call_open_time]").val() || '')
            );
        }, 200);

        $(".callTab").off('click').on('click', (event) => {
            const el = event.currentTarget;
            $(".callTab.active").removeClass('active');
            $(el).addClass('active');
            let id = $(el).data('call-id');
            let call = module.metadata[id] || {};
            let instances = getCallInstanceIds(id);
            $("#CallLogCurrentCall").text(getCallName(id, call));
            $("#CallLogPreviousTime").text(instances.length === 0 ? 'None' : getPreviousCalldatetime(id));
            populateCallFormFields(id, call, instances.length + 1);
            updateCallNotes(id);
            updateCallScript(id);
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

        const updateSubmitButtonState = () => {
            const outcomeVal = $("input[name=call_outcome]").val();
            const hasVal = (outcomeVal !== "" && outcomeVal !== undefined);
            $("#submit-btn-saverecord, #goto-call-list").prop('disabled', !hasVal).css('pointer-events', hasVal ? 'inherit' : 'none');
            $("#submit-btn-dropdown").parent().find('button').prop('disabled', !hasVal).css('pointer-events', hasVal ? 'inherit' : 'none');
        };

        // Safety net: re-apply call form fields immediately before form save in case
        // the active tab's fields somehow got cleared by REDCap branching logic.
        const ensureFieldsOnSave = () => {
            const $activeTab = $(".callTab.active:visible");
            if (!$activeTab.length) return;
            const id = $activeTab.data('call-id');
            if (!id) return;
            const call = module.metadata[id] || {};
            const instances = getCallInstanceIds(id);
            populateCallFormFields(id, call, instances.length + 1);
        };
        $("#submit-btn-saverecord, #goto-call-list, form#form").on('click submit', ensureFieldsOnSave);

        $("#call_outcome-tr").find('input, a').on('click change', updateSubmitButtonState);
        updateSubmitButtonState();

    };

    $(function () {
        buildCallLog();
    });
})();