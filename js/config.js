(() => {
    const module = ExternalModules.UWMadison.CallLog;
    const raw = module.rawConfig || {};
    const meta = module.metaInfo || {};

    const defaultHolidaysMap = meta.defaultHolidayMap || {
        'new_years_day': "New Year's Day (Jan 1)",
        'mlk_day': "Martin Luther King Jr. Day (3rd Mon in Jan)",
        'memorial_day': "Memorial Day (Last Mon in May)",
        'juneteenth': "Juneteenth National Independence Day (Jun 19)",
        'independence_day': "Independence Day (Jul 4)",
        'labor_day': "Labor Day (1st Mon in Sep)",
        'veterans_day': "Veterans Day (Nov 11)",
        'thanksgiving': "Thanksgiving Day (4th Thu in Nov)",
        'day_after_thanksgiving': "Day After Thanksgiving (Fri after Thanksgiving)",
        'christmas_eve': "Christmas Eve (Dec 24)",
        'christmas_day': "Christmas Day (Dec 25)",
        'new_years_eve': "New Year's Eve (Dec 31)"
    };

    const callTemplateOptions = meta.callTemplateOptions || {
        'new': 'New Entry',
        'reminder': 'Reminder',
        'followup': 'Follow Up',
        'mcv': 'Missed / Cancelled Visit',
        'nts': 'Need to Schedule',
        'adhoc': 'Ad-hoc',
        'visit': 'Scheduled Phone Visit'
    };

    const fieldLinkOptions = meta.fieldLinkOptions || {
        'none': 'None (Plain Text)',
        'home': 'Record Home Page',
        'call': 'Call Log Instrument',
        'instrument': 'Specific Instrument'
    };

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function populateSelect(selectEl, items, selectedValue, valKey = 'id', textKey = 'name', emptyLabel = '-- Select --') {
        selectEl.innerHTML = `<option value="">${escapeHtml(emptyLabel)}</option>`;
        if (!Array.isArray(items)) return;
        items.forEach(item => {
            const val = typeof item === 'object' ? item[valKey] : item;
            const text = typeof item === 'object' ? (item[textKey] || item[valKey]) : item;
            const selected = String(val) === String(selectedValue) ? 'selected' : '';
            selectEl.innerHTML += `<option value="${escapeHtml(val)}" ${selected}>${escapeHtml(text)}</option>`;
        });
    }

    function populateOptionsFromObject(selectEl, objectMap, selectedValue) {
        selectEl.innerHTML = '';
        Object.entries(objectMap).forEach(([val, label]) => {
            const selected = String(val) === String(selectedValue) ? 'selected' : '';
            selectEl.innerHTML += `<option value="${escapeHtml(val)}" ${selected}>${escapeHtml(label)}</option>`;
        });
    }

    function setupCustomMultiSelect(containerId, items, selectedValues, placeholder = 'Select items...', valKey = 'id', textKey = 'name') {
        const container = document.getElementById(containerId);
        if (!container) return { selectedValues: [] };

        const box = container.querySelector('.custom-multiselect-box');
        const menu = container.querySelector('.custom-multiselect-menu');

        let selected = Array.isArray(selectedValues) ? [...selectedValues] : [];

        function render() {
            box.innerHTML = '';
            if (selected.length === 0) {
                box.innerHTML = `<span class="text-muted small">${escapeHtml(placeholder)}</span>`;
            } else {
                selected.forEach(val => {
                    const item = items.find(i => String(typeof i === 'object' ? i[valKey] : i) === String(val));
                    const text = item ? (typeof item === 'object' ? (item[textKey] || item[valKey]) : item) : val;
                    const badge = document.createElement('span');
                    badge.className = 'custom-multiselect-badge';
                    badge.innerHTML = `${escapeHtml(text)} <i class="fas fa-times ms-1" data-val="${escapeHtml(val)}"></i>`;
                    badge.querySelector('i').addEventListener('click', (e) => {
                        e.stopPropagation();
                        selected = selected.filter(v => String(v) !== String(val));
                        render();
                    });
                    box.appendChild(badge);
                });
            }

            menu.innerHTML = '';
            items.forEach(item => {
                const val = String(typeof item === 'object' ? item[valKey] : item);
                const text = typeof item === 'object' ? (item[textKey] || item[valKey]) : item;
                const isSelected = selected.map(String).includes(val);

                const option = document.createElement('div');
                option.className = `custom-multiselect-option ${isSelected ? 'selected' : ''}`;
                option.innerHTML = `<i class="fas ${isSelected ? 'fa-check-square text-primary' : 'fa-square text-muted'} me-2"></i> ${escapeHtml(text)}`;
                option.addEventListener('click', () => {
                    if (isSelected) {
                        selected = selected.filter(v => String(v) !== String(val));
                    } else {
                        selected.push(val);
                    }
                    render();
                });
                menu.appendChild(option);
            });

            container.selectedValues = selected;
        }

        box.addEventListener('click', (e) => {
            e.stopPropagation();
            document.querySelectorAll('.custom-multiselect-container').forEach(c => {
                if (c !== container) c.classList.remove('open');
            });
            container.classList.toggle('open');
        });

        render();
        return container;
    }

    document.addEventListener('click', () => {
        document.querySelectorAll('.custom-multiselect-container').forEach(c => c.classList.remove('open'));
    });

    const triggerSaveContainer = setupCustomMultiSelect('cfg_trigger_save_container', meta.instruments || [], raw.trigger_save || [], 'Select forms...');
    const callSummaryContainer = setupCustomMultiSelect('cfg_call_summary_container', meta.instruments || [], raw.call_summary || [], 'Select instruments...');

    const sameDayChk = document.getElementById('cfg_same_day_mcv_nts');
    if (sameDayChk) {
        sameDayChk.checked = (raw.same_day_mcv_nts && raw.same_day_mcv_nts[0] === '1');
    }

    // Call Types
    const callTypesContainer = document.getElementById('callTypesContainer');
    const callIds = raw.call_id || [];
    const callNames = raw.call_name || [];
    const callTemplates = raw.call_template || [];
    const hideAttempts = raw.hide_after_attempts || [];
    const newExpireDays = raw.new_expire_days || [];
    const reminderVars = raw.reminder_variable || [];
    const reminderDaysList = raw.reminder_days || [];
    const reminderEventsList = raw.reminder_include_events || [];
    const followupDates = raw.followup_date || [];
    const followupDaysList = raw.followup_days || [];
    const followupEventsList = raw.followup_include_events || [];
    const mcvIndicators = raw.mcv_indicator || [];
    const mcvDates = raw.mcv_date || [];
    const mcvEventsList = raw.mcv_include_events || [];
    const ntsIndicators = raw.nts_indicator || [];
    const ntsDates = raw.nts_date || [];
    const ntsEventsList = raw.nts_include_events || [];
    const adhocReasons = raw.adhoc_reason || [];
    const visitIndicators = raw.visit_indicator || [];
    const visitEventsList = raw.visit_include_events || [];

    function renderCallTypes() {
        if (!callTypesContainer) return;
        callTypesContainer.innerHTML = '';

        callIds.forEach((idVal, i) => {
            const card = document.createElement('div');
            card.className = 'card border-0 shadow-sm call-type-card';
            const templateVal = callTemplates[i] || 'new';

            card.innerHTML = `
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                    <span class="fw-bold text-primary fs-6 call-type-header-title">${escapeHtml(idVal || 'CALL')} - ${escapeHtml(callNames[i] || 'New Call Type')}</span>
                    <button type="button" class="btn btn-outline-danger btn-sm border-0 btn-delete-call-type">
                        <i class="fas fa-trash-alt"></i> Remove Call Type
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Call ID</label>
                            <input type="text" class="form-control form-control-sm call-prop-id" value="${escapeHtml(idVal)}" placeholder="e.g. CALL_1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Call Name</label>
                            <input type="text" class="form-control form-control-sm call-prop-name" value="${escapeHtml(callNames[i] || '')}" placeholder="e.g. Baseline Follow-up">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Template Type</label>
                            <select class="form-select form-select-sm call-prop-template"></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Hide After Attempts</label>
                            <input type="number" class="form-control form-control-sm call-prop-hide" value="${escapeHtml(hideAttempts[i] || '9999')}" placeholder="9999">
                        </div>
                    </div>
                    <div class="mt-3 border-top pt-3 template-extra-rules"></div>
                </div>
            `;

            callTypesContainer.appendChild(card);

            const selectTemplate = card.querySelector('.call-prop-template');
            populateOptionsFromObject(selectTemplate, callTemplateOptions, templateVal);

            const extraContainer = card.querySelector('.template-extra-rules');
            renderTemplateExtraRules(extraContainer, templateVal, i);

            selectTemplate.addEventListener('change', (e) => {
                renderTemplateExtraRules(extraContainer, e.target.value, i);
            });
        });

        updateChecklistBadges();
    }

    function renderTemplateExtraRules(container, template, index) {
        container.innerHTML = '';
        if (template === 'new') {
            container.innerHTML = `
                <div class="setting-blurb mb-2">New Entry calls are generated once when a record is created. Set days until expiration:</div>
                <div class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Days Until Expire</label>
                        <input type="number" class="form-control form-control-sm call-prop-new-expire" value="${escapeHtml(newExpireDays[index] || '')}" placeholder="e.g. 30">
                    </div>
                </div>
            `;
        } else if (template === 'reminder') {
            container.innerHTML = `
                <div class="setting-blurb mb-2">Reminder calls trigger N days before a scheduled event date field:</div>
                <div class="row g-3 mb-2">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Target Date Field</label>
                        <select class="form-select form-select-sm call-prop-reminder-var"></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Days Before Scheduled Date</label>
                        <input type="number" class="form-control form-control-sm call-prop-reminder-days" value="${escapeHtml(reminderDaysList[index] || '')}" placeholder="e.g. 3">
                    </div>
                </div>
                <div id="reminder_events_${index}" class="custom-multiselect-container">
                    <label class="form-label small fw-bold mb-1">Include Events:</label>
                    <div class="custom-multiselect-box"></div>
                    <div class="custom-multiselect-menu"></div>
                </div>
            `;
            populateSelect(container.querySelector('.call-prop-reminder-var'), meta.fields, reminderVars[index], 'id', 'label', '-- Select Date Field --');
            const selectedEvts = (reminderEventsList[index] ? String(reminderEventsList[index]).split(',').map(s => s.trim()) : []);
            setupCustomMultiSelect(`reminder_events_${index}`, meta.events || [], selectedEvts, 'Select events...', 'id', 'name');
        } else if (template === 'followup') {
            container.innerHTML = `
                <div class="setting-blurb mb-2">Follow Up calls trigger N days after a baseline date field:</div>
                <div class="row g-3 mb-2">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Baseline Date Field</label>
                        <select class="form-select form-select-sm call-prop-followup-date"></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Days After Baseline Date</label>
                        <input type="number" class="form-control form-control-sm call-prop-followup-days" value="${escapeHtml(followupDaysList[index] || '')}" placeholder="e.g. 7">
                    </div>
                </div>
                <div id="followup_events_${index}" class="custom-multiselect-container">
                    <label class="form-label small fw-bold mb-1">Include Events:</label>
                    <div class="custom-multiselect-box"></div>
                    <div class="custom-multiselect-menu"></div>
                </div>
            `;
            populateSelect(container.querySelector('.call-prop-followup-date'), meta.fields, followupDates[index], 'id', 'label', '-- Select Date Field --');
            const selectedEvts = (followupEventsList[index] ? String(followupEventsList[index]).split(',').map(s => s.trim()) : []);
            setupCustomMultiSelect(`followup_events_${index}`, meta.events || [], selectedEvts, 'Select events...', 'id', 'name');
        } else if (template === 'mcv') {
            container.innerHTML = `
                <div class="setting-blurb mb-2">Missed / Cancelled Visit calls trigger when an appointment indicator is checked:</div>
                <div class="row g-3 mb-2">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Indicator Field</label>
                        <select class="form-select form-select-sm call-prop-mcv-ind"></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Appointment Date Field</label>
                        <select class="form-select form-select-sm call-prop-mcv-date"></select>
                    </div>
                </div>
                <div id="mcv_events_${index}" class="custom-multiselect-container">
                    <label class="form-label small fw-bold mb-1">Include Events:</label>
                    <div class="custom-multiselect-box"></div>
                    <div class="custom-multiselect-menu"></div>
                </div>
            `;
            populateSelect(container.querySelector('.call-prop-mcv-ind'), meta.fields, mcvIndicators[index], 'id', 'label', '-- Select Indicator Field --');
            populateSelect(container.querySelector('.call-prop-mcv-date'), meta.fields, mcvDates[index], 'id', 'label', '-- Select Date Field --');
            const selectedEvts = (mcvEventsList[index] ? String(mcvEventsList[index]).split(',').map(s => s.trim()) : []);
            setupCustomMultiSelect(`mcv_events_${index}`, meta.events || [], selectedEvts, 'Select events...', 'id', 'name');
        } else if (template === 'nts') {
            container.innerHTML = `
                <div class="setting-blurb mb-2">Need to Schedule calls generate when an event requires scheduling:</div>
                <div class="row g-3 mb-2">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Indicator Field</label>
                        <select class="form-select form-select-sm call-prop-nts-ind"></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Target Date Field</label>
                        <select class="form-select form-select-sm call-prop-nts-date"></select>
                    </div>
                </div>
                <div id="nts_events_${index}" class="custom-multiselect-container">
                    <label class="form-label small fw-bold mb-1">Include Events:</label>
                    <div class="custom-multiselect-box"></div>
                    <div class="custom-multiselect-menu"></div>
                </div>
            `;
            populateSelect(container.querySelector('.call-prop-nts-ind'), meta.fields, ntsIndicators[index], 'id', 'label', '-- Select Indicator Field --');
            populateSelect(container.querySelector('.call-prop-nts-date'), meta.fields, ntsDates[index], 'id', 'label', '-- Select Date Field --');
            const selectedEvts = (ntsEventsList[index] ? String(ntsEventsList[index]).split(',').map(s => s.trim()) : []);
            setupCustomMultiSelect(`nts_events_${index}`, meta.events || [], selectedEvts, 'Select events...', 'id', 'name');
        } else if (template === 'adhoc') {
            container.innerHTML = `
                <div class="setting-blurb mb-2">Ad-hoc calls are initiated manually on participant forms:</div>
                <div class="row g-2 align-items-center">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Adhoc Reason Code Map</label>
                        <input type="text" class="form-control form-control-sm call-prop-adhoc-reason" value="${escapeHtml(adhocReasons[index] || '')}" placeholder="1, General | 2, Followup">
                    </div>
                </div>
            `;
        } else if (template === 'visit') {
            container.innerHTML = `
                <div class="setting-blurb mb-2">Scheduled Phone Visit calls trigger for upcoming windowed visits:</div>
                <div class="row g-2 align-items-center mb-2">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Visit Indicator Field</label>
                        <select class="form-select form-select-sm call-prop-visit-ind"></select>
                    </div>
                </div>
                <div id="visit_events_${index}" class="custom-multiselect-container">
                    <label class="form-label small fw-bold mb-1">Include Events:</label>
                    <div class="custom-multiselect-box"></div>
                    <div class="custom-multiselect-menu"></div>
                </div>
            `;
            populateSelect(container.querySelector('.call-prop-visit-ind'), meta.fields, visitIndicators[index], 'id', 'label', '-- Select Indicator Field --');
            const selectedEvts = (visitEventsList[index] ? String(visitEventsList[index]).split(',').map(s => s.trim()) : []);
            setupCustomMultiSelect(`visit_events_${index}`, meta.events || [], selectedEvts, 'Select events...', 'id', 'name');
        }
    }

    renderCallTypes();

    document.getElementById('btnAddCallType').addEventListener('click', () => {
        callIds.push('CALL_' + (callIds.length + 1));
        callNames.push('New Call Type ' + (callIds.length + 1));
        callTemplates.push('new');
        hideAttempts.push('9999');
        renderCallTypes();
    });

    callTypesContainer.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-delete-call-type');
        if (btn) {
            const card = btn.closest('.call-type-card');
            const cards = Array.from(callTypesContainer.querySelectorAll('.call-type-card'));
            const idx = cards.indexOf(card);
            if (idx !== -1) {
                callIds.splice(idx, 1);
                callNames.splice(idx, 1);
                callTemplates.splice(idx, 1);
                hideAttempts.splice(idx, 1);
                renderCallTypes();
            }
        }
    });

    callTypesContainer.addEventListener('input', (e) => {
        if (e.target.classList.contains('call-prop-id') || e.target.classList.contains('call-prop-name')) {
            const card = e.target.closest('.call-type-card');
            const idVal = card.querySelector('.call-prop-id').value.trim();
            const nameVal = card.querySelector('.call-prop-name').value.trim();
            card.querySelector('.call-type-header-title').textContent = `${idVal || 'CALL'} - ${nameVal || 'New Call Type'}`;
            refreshTabCallIdDropdowns();
        }
    });

    // Call Tabs
    const callTabsContainer = document.getElementById('callTabsContainer');
    const tabNames = raw.tab_name || [];
    const tabCalls = raw.tab_calls_included || [];
    const tabFields = raw.tab_field || [];
    const tabFieldNames = raw.tab_field_name || [];
    const tabFieldDefaults = raw.tab_field_default || [];
    const tabFieldLinks = raw.tab_field_link || [];
    const tabFieldLinkInsts = raw.tab_field_link_instrument || [];

    function getAvailableCallIds() {
        const ids = [];
        document.querySelectorAll('#callTypesContainer .call-prop-id').forEach(el => {
            if (el.value.trim()) ids.push(el.value.trim());
        });
        return ids;
    }

    function createTabFieldRow(fieldVal = '', nameVal = '', defaultVal = '', linkVal = 'none', linkInstVal = '') {
        const fRow = document.createElement('div');
        fRow.className = 'tab-field-row mb-2';
        fRow.innerHTML = `
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-dark mb-1">REDCap Field</label>
                    <select class="form-select form-select-sm tab-field-var"></select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-dark mb-1">Display Label</label>
                    <input type="text" class="form-control form-control-sm tab-field-name" value="${escapeHtml(nameVal)}" placeholder="Custom Label">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-dark mb-1">Default Value</label>
                    <input type="text" class="form-control form-control-sm tab-field-default" value="${escapeHtml(defaultVal)}" placeholder="Default">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-dark mb-1">Record Link</label>
                    <select class="form-select form-select-sm tab-field-link"></select>
                </div>
                <div class="col-md-2 tab-field-link-inst-col" style="visibility: ${linkVal === 'instrument' ? 'visible' : 'hidden'};">
                    <label class="form-label small fw-semibold text-dark mb-1">Target Instrument</label>
                    <select class="form-select form-select-sm tab-field-link-inst"></select>
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm border-0 btn-delete-tab-field px-2" title="Remove Field" style="height: 38px;">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
        `;

        populateSelect(fRow.querySelector('.tab-field-var'), meta.fields, fieldVal, 'id', 'label', '-- Select Field --');
        populateOptionsFromObject(fRow.querySelector('.tab-field-link'), fieldLinkOptions, linkVal);
        populateSelect(fRow.querySelector('.tab-field-link-inst'), meta.instruments, linkInstVal, 'id', 'name', '-- Select Instrument --');

        fRow.querySelector('.tab-field-link').addEventListener('change', (e) => {
            const instCol = fRow.querySelector('.tab-field-link-inst-col');
            instCol.style.visibility = (e.target.value === 'instrument') ? 'visible' : 'hidden';
        });

        fRow.querySelector('.btn-delete-tab-field').addEventListener('click', () => {
            fRow.remove();
        });

        return fRow;
    }

    function updateTabExtraInfoBox(card) {
        const infoBox = card.querySelector('.tab-extra-info-box');
        const listEl = card.querySelector('.tab-extra-info-list');
        const multiContainer = card.querySelector('.custom-multiselect-container');
        if (!infoBox || !listEl || !multiContainer) return;

        const selectedIds = multiContainer.selectedValues || [];
        if (selectedIds.length === 0) {
            infoBox.style.display = 'none';
            listEl.innerHTML = '';
            return;
        }

        infoBox.style.display = 'block';
        listEl.innerHTML = '';

        const templatesFound = new Set();
        document.querySelectorAll('#callTypesContainer .call-type-card').forEach(cCard => {
            const idVal = cCard.querySelector('.call-prop-id')?.value.trim();
            const tmplVal = cCard.querySelector('.call-prop-template')?.value;
            if (idVal && selectedIds.includes(idVal) && tmplVal) {
                templatesFound.add(tmplVal);
            }
        });

        const items = [];
        items.push('Standard Columns: Record ID, Call Date/Time, Call Notes, Action Controls');
        if (templatesFound.has('reminder') || templatesFound.has('followup')) {
            items.push(`<strong>${callTemplateOptions.reminder || 'Reminder'} / ${callTemplateOptions.followup || 'Follow Up'} Windows:</strong> Window Start & End Dates, Callback Requestor info & Stopwatch badge`);
        }
        if (templatesFound.has('mcv')) {
            items.push(`<strong>${callTemplateOptions.mcv || 'Missed / Cancelled Visit'} / MCV:</strong> Appointment Date & Time, Missed Visit Indicator status`);
        }
        if (templatesFound.has('nts')) {
            items.push(`<strong>${callTemplateOptions.nts || 'Need to Schedule'} / NTS:</strong> Scheduled Appointment Date, Days-Before Window tracking`);
        }
        if (templatesFound.has('adhoc')) {
            items.push(`<strong>${callTemplateOptions.adhoc || 'Ad-hoc'} Calls:</strong> Target Adhoc Available Date, Adhoc Reason selection`);
        }
        if (templatesFound.has('new')) {
            items.push(`<strong>${callTemplateOptions.new || 'New Entry'} Calls:</strong> Entry Expiration Status & Days remaining`);
        }
        if (templatesFound.has('visit')) {
            items.push(`<strong>${callTemplateOptions.visit || 'Scheduled Phone Visit'} Calls:</strong> Visit Window Indicator status`);
        }

        items.forEach(it => {
            const li = document.createElement('li');
            li.className = 'mb-1';
            li.innerHTML = it;
            listEl.appendChild(li);
        });
    }

    function refreshTabCallIdDropdowns() {
        const available = getAvailableCallIds();
        document.querySelectorAll('#callTabsContainer .call-tab-card').forEach(card => {
            const multiContainer = card.querySelector('.custom-multiselect-container');
            if (multiContainer) {
                const curSelected = multiContainer.selectedValues || [];
                setupCustomMultiSelect(multiContainer.id, available, curSelected, 'Select Call IDs...');
                updateTabExtraInfoBox(card);
            }
        });
    }

    function renderCallTabs() {
        if (!callTabsContainer) return;
        callTabsContainer.innerHTML = '';

        const availableCallIds = getAvailableCallIds();

        tabNames.forEach((nameVal, i) => {
            const card = document.createElement('div');
            card.className = 'card border-0 shadow-sm call-tab-card';
            const selectedCalls = (tabCalls[i] ? String(tabCalls[i]).split(',').map(s => s.trim()) : []);

            card.innerHTML = `
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-secondary me-2 tab-order-badge">Tab #${i + 1}</span>
                        <span class="fw-bold text-success tab-title-display">${escapeHtml(nameVal || 'New Tab')}</span>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm border-0 btn-delete-tab">
                        <i class="fas fa-trash-alt"></i> Remove Tab
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tab Name</label>
                            <input type="text" class="form-control form-control-sm tab-prop-name" value="${escapeHtml(nameVal)}" placeholder="e.g. Active Calls">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Included Call IDs</label>
                            <div id="tab_calls_multi_${i}" class="custom-multiselect-container">
                                <div class="custom-multiselect-box"></div>
                                <div class="custom-multiselect-menu"></div>
                            </div>
                        </div>
                    </div>
                    <div class="p-3 bg-primary-subtle border border-primary-subtle rounded-3 mb-3 tab-extra-info-box" style="display:none;">
                        <div class="fw-bold small text-primary mb-1">
                            <i class="fas fa-info-circle me-1"></i> Auto-Included Columns for Selected Call Types:
                        </div>
                        <ul class="mb-0 small text-secondary ps-3 tab-extra-info-list"></ul>
                    </div>
                    <div class="border-top pt-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label small fw-bold text-dark mb-0">
                                <i class="fas fa-table me-1 text-primary"></i> Custom Display Fields for Tab
                            </label>
                            <button type="button" class="btn btn-outline-primary btn-sm btn-add-tab-field">
                                <i class="fas fa-plus me-1"></i> Add Custom Field to Tab
                            </button>
                        </div>
                        <div class="tab-fields-list mb-2"></div>
                    </div>
                </div>
            `;

            callTabsContainer.appendChild(card);

            setupCustomMultiSelect(`tab_calls_multi_${i}`, availableCallIds, selectedCalls, 'Select Call IDs...');

            const fList = (tabFields[i] && Array.isArray(tabFields[i])) ? tabFields[i] : [];
            const nList = (tabFieldNames[i] && Array.isArray(tabFieldNames[i])) ? tabFieldNames[i] : [];
            const dList = (tabFieldDefaults[i] && Array.isArray(tabFieldDefaults[i])) ? tabFieldDefaults[i] : [];
            const lList = (tabFieldLinks[i] && Array.isArray(tabFieldLinks[i])) ? tabFieldLinks[i] : [];
            const iList = (tabFieldLinkInsts[i] && Array.isArray(tabFieldLinkInsts[i])) ? tabFieldLinkInsts[i] : [];

            const fieldsListEl = card.querySelector('.tab-fields-list');
            fList.forEach((fVal, j) => {
                if (fVal) {
                    const fRow = createTabFieldRow(fVal, nList[j] || '', dList[j] || '', lList[j] || 'none', iList[j] || '');
                    fieldsListEl.appendChild(fRow);
                }
            });

            card.querySelector('.btn-add-tab-field').addEventListener('click', () => {
                const newRow = createTabFieldRow('', '', '', 'none', '');
                fieldsListEl.appendChild(newRow);
            });

            updateTabExtraInfoBox(card);
        });

        updateChecklistBadges();
    }

    renderCallTabs();

    document.getElementById('btnAddTab').addEventListener('click', () => {
        tabNames.push('Tab ' + (tabNames.length + 1));
        tabCalls.push('');
        renderCallTabs();
    });

    callTabsContainer.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-delete-tab');
        if (btn) {
            const card = btn.closest('.call-tab-card');
            const cards = Array.from(callTabsContainer.querySelectorAll('.call-tab-card'));
            const idx = cards.indexOf(card);
            if (idx !== -1) {
                tabNames.splice(idx, 1);
                tabCalls.splice(idx, 1);
                renderCallTabs();
            }
        }
    });

    // Standard & Custom Holidays
    const standardHolidaysContainer = document.getElementById('standardHolidaysContainer');
    const customHolidaysContainer = document.getElementById('customHolidaysContainer');

    const enabledHolidays = (raw.enabled_holidays && raw.enabled_holidays.length) ? raw.enabled_holidays : Object.keys(defaultHolidaysMap);
    const customHolidayDates = raw.custom_holidays_date || [];
    const customHolidayNames = raw.custom_holidays_name || [];

    function renderStandardHolidays() {
        if (!standardHolidaysContainer) return;
        standardHolidaysContainer.innerHTML = '';
        const enabledSet = new Set(enabledHolidays);

        Object.entries(defaultHolidaysMap).forEach(([key, label]) => {
            const col = document.createElement('div');
            col.className = 'col-md-6 col-lg-4';
            const isChecked = enabledSet.has(key) ? 'checked' : '';
            col.innerHTML = `
                <div class="form-check form-switch p-2 bg-light rounded border border-light-subtle d-flex align-items-center">
                    <input class="form-check-input holiday-chk ms-0 me-2" type="checkbox" id="holiday_chk_${key}" value="${key}" ${isChecked}>
                    <label class="form-check-label small fw-semibold text-dark mb-0" for="holiday_chk_${key}">
                        ${escapeHtml(label)}
                    </label>
                </div>
            `;
            standardHolidaysContainer.appendChild(col);
        });
    }

    function createCustomHolidayRow(dateVal = '', nameVal = '') {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center custom-holiday-row p-2 bg-light rounded border border-light-subtle';
        row.innerHTML = `
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-dark mb-1">Date (MM-DD)</label>
                <input type="text" class="form-control form-control-sm custom-holiday-date" value="${escapeHtml(dateVal)}" placeholder="e.g. 12-26">
            </div>
            <div class="col-md-8">
                <label class="form-label small fw-semibold text-dark mb-1">Holiday / Closure Name</label>
                <input type="text" class="form-control form-control-sm custom-holiday-name" value="${escapeHtml(nameVal)}" placeholder="e.g. Winter Recess / Staff Day">
            </div>
            <div class="col-md-1 text-end pt-3">
                <button type="button" class="btn btn-outline-danger btn-sm border-0 btn-delete-custom-holiday px-2" title="Remove Closure" style="height: 38px;">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        `;
        row.querySelector('.btn-delete-custom-holiday').addEventListener('click', () => row.remove());
        return row;
    }

    function renderCustomHolidays() {
        if (!customHolidaysContainer) return;
        customHolidaysContainer.innerHTML = '';
        customHolidayDates.forEach((dVal, i) => {
            if (dVal) {
                customHolidaysContainer.appendChild(createCustomHolidayRow(dVal, customHolidayNames[i] || ''));
            }
        });
    }

    renderStandardHolidays();
    renderCustomHolidays();

    document.getElementById('btnAddCustomHoliday')?.addEventListener('click', () => {
        if (customHolidaysContainer) customHolidaysContainer.appendChild(createCustomHolidayRow('', ''));
    });

    // Row Expansion Fields
    const expandsFieldsContainer = document.getElementById('expandsFieldsContainer');
    const expandsFieldList = (raw.tab_expands_field && raw.tab_expands_field[0]) ? raw.tab_expands_field[0] : (raw.tab_expands_field || []);
    const expandsNameList = (raw.tab_expands_field_name && raw.tab_expands_field_name[0]) ? raw.tab_expands_field_name[0] : (raw.tab_expands_field_name || []);
    const expandsDefaultList = (raw.tab_expands_field_default && raw.tab_expands_field_default[0]) ? raw.tab_expands_field_default[0] : (raw.tab_expands_field_default || []);

    function renderExpandsFields() {
        if (!expandsFieldsContainer) return;
        expandsFieldsContainer.innerHTML = '';
        expandsFieldList.forEach((fieldVal, i) => {
            const row = document.createElement('div');
            row.className = 'd-flex align-items-end gap-3 p-3 bg-light rounded border expands-field-row';
            row.innerHTML = `
                <div class="flex-grow-1" style="flex: 1 1 35%;">
                    <label class="form-label small fw-bold mb-1">Project Field</label>
                    <select class="form-select form-select-sm expands-prop-field"></select>
                </div>
                <div class="flex-grow-1" style="flex: 1 1 35%;">
                    <label class="form-label small fw-bold mb-1">Display Label</label>
                    <input type="text" class="form-control form-control-sm expands-prop-name" value="${escapeHtml(expandsNameList[i] || '')}" placeholder="e.g. Alternate Phone">
                </div>
                <div class="flex-grow-1" style="flex: 1 1 25%;">
                    <label class="form-label small fw-bold mb-1">Default Value</label>
                    <input type="text" class="form-control form-control-sm expands-prop-default" value="${escapeHtml(expandsDefaultList[i] || '')}" placeholder="e.g. N/A">
                </div>
                <div class="pb-1">
                    <button type="button" class="btn btn-outline-danger btn-sm border-0 btn-delete-expands" title="Remove Field">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `;
            expandsFieldsContainer.appendChild(row);
            populateSelect(row.querySelector('.expands-prop-field'), meta.fields, fieldVal, 'id', 'label', '-- Select Field --');
        });
    }

    renderExpandsFields();

    document.getElementById('btnAddExpandsField')?.addEventListener('click', () => {
        expandsFieldList.push('');
        expandsNameList.push('');
        expandsDefaultList.push('');
        renderExpandsFields();
    });

    expandsFieldsContainer?.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-delete-expands');
        if (btn) btn.closest('.expands-field-row')?.remove();
    });

    // Withdrawal Rules
    const withdrawRulesContainer = document.getElementById('withdrawRulesContainer');
    const withdrawEvents = raw.withdraw_event || [];
    const withdrawVars = raw.withdraw_var || [];

    function renderWithdrawRules() {
        if (!withdrawRulesContainer) return;
        withdrawRulesContainer.innerHTML = '';
        withdrawVars.forEach((vVal, i) => {
            const row = document.createElement('div');
            row.className = 'd-flex align-items-end gap-3 p-3 bg-light rounded border withdraw-rule-row';
            row.innerHTML = `
                <div class="flex-grow-1" style="flex: 1 1 45%;">
                    <label class="form-label small fw-bold mb-1">REDCap Event</label>
                    <select class="form-select form-select-sm withdraw-prop-event"></select>
                </div>
                <div class="flex-grow-1" style="flex: 1 1 45%;">
                    <label class="form-label small fw-bold mb-1">Withdrawal Indicator Field</label>
                    <select class="form-select form-select-sm withdraw-prop-var"></select>
                </div>
                <div class="pb-1">
                    <button type="button" class="btn btn-outline-danger btn-sm border-0 btn-delete-withdraw" title="Remove Condition">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `;
            withdrawRulesContainer.appendChild(row);
            populateSelect(row.querySelector('.withdraw-prop-event'), meta.events, withdrawEvents[i], 'id', 'name', '-- All Events / Project Default --');
            populateSelect(row.querySelector('.withdraw-prop-var'), meta.fields, vVal, 'id', 'label', '-- Select Field --');
        });
        updateChecklistBadges();
    }

    renderWithdrawRules();

    document.getElementById('btnAddWithdrawRule')?.addEventListener('click', () => {
        withdrawEvents.push('');
        withdrawVars.push('');
        renderWithdrawRules();
    });

    withdrawRulesContainer?.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-delete-withdraw');
        if (btn) {
            btn.closest('.withdraw-rule-row')?.remove();
            updateChecklistBadges();
        }
    });

    function updateChecklistBadges() {
        const statCalls = document.getElementById('statCallTypesCount');
        const statTabs = document.getElementById('statTabsCount');
        const statTotal = document.getElementById('statTotalCallsCount');
        const pillCalls = document.getElementById('pillCallTypesCount');
        const pillTabs = document.getElementById('pillTabsCount');

        const callCount = document.querySelectorAll('#callTypesContainer .call-type-card').length;
        const tabCount = document.querySelectorAll('#callTabsContainer .call-tab-card').length;

        if (statCalls) statCalls.textContent = callCount;
        if (statTabs) statTabs.textContent = tabCount;
        if (statTotal) statTotal.textContent = meta.totalCalls || 0;
        if (pillCalls) pillCalls.textContent = callCount;
        if (pillTabs) pillTabs.textContent = tabCount;

        const isDeployed = (meta.instrumentsDeployed && meta.instrumentsDeployed.length > 0);
        const hasTrigger = (triggerSaveContainer.selectedValues && triggerSaveContainer.selectedValues.length > 0);
        const hasCalls = callCount > 0;
        const hasTabs = tabCount > 0;
        const hasWithdraw = document.querySelectorAll('#withdrawRulesContainer .withdraw-rule-row').length > 0;

        function updateItem(id, pass, customTextPass = 'Configured', customTextFail = 'Pending') {
            const el = document.getElementById(id);
            if (!el) return;
            const icon = el.querySelector('.status-icon');
            const badge = el.querySelector('.status-badge');
            if (pass) {
                icon.className = 'fas fa-check-circle me-3 fs-5 text-success status-icon';
                badge.className = 'badge bg-success status-badge';
                badge.textContent = customTextPass;
            } else {
                icon.className = 'fas fa-check-circle me-3 fs-5 text-muted status-icon';
                badge.className = 'badge bg-secondary status-badge';
                badge.textContent = customTextFail;
            }
        }

        updateItem('chk-item-deploy', isDeployed, 'Deployed', 'Pending');
        updateItem('chk-item-trigger', hasTrigger, 'Configured', 'Pending');
        updateItem('chk-item-calls', hasCalls, 'Configured', 'Pending');
        updateItem('chk-item-tabs', hasTabs, 'Configured', 'Pending');
        updateItem('chk-item-withdraw', hasWithdraw, 'Active', 'Optional');
    }

    // Deploy Instruments Button
    document.getElementById('btnDeployInstruments')?.addEventListener('click', function () {
        const btn = this;
        const events = meta.events || [];
        let targetEventId = null;

        if (events.length > 1) {
            targetEventId = document.getElementById('deployTargetEventSelect')?.value;
            if (!targetEventId) {
                Swal.fire({ icon: 'warning', title: 'Event Required', text: 'Please select an event to assign the Call Log instruments.' });
                return;
            }
        } else if (events.length === 1) {
            targetEventId = events[0].id;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Deploying...';

        module.ajax("deployInstruments", { event_id: targetEventId }).then(function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download me-1"></i> Deploy Call Log Instruments (call.csv)';
            if (res && res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Instruments Deployed',
                    text: res.message || 'Call Log instruments were deployed successfully.',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Deployment Failed', text: res.message || 'Could not deploy instruments.' });
            }
        }).catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download me-1"></i> Deploy Call Log Instruments (call.csv)';
            Swal.fire({ icon: 'error', title: 'Error', text: err.message || err });
        });
    });

    // Save Configuration Button
    document.getElementById('btnSaveConfig')?.addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

        const updatedCallIds = [];
        const updatedCallNames = [];
        const updatedCallTemplates = [];
        const updatedHideAttempts = [];
        const updatedNewExpire = [];
        const updatedReminderVar = [];
        const updatedReminderDays = [];
        const updatedReminderEvents = [];
        const updatedFollowupDate = [];
        const updatedFollowupDays = [];
        const updatedFollowupEvents = [];
        const updatedMcvIndicator = [];
        const updatedMcvDate = [];
        const updatedMcvEvents = [];
        const updatedNtsIndicator = [];
        const updatedNtsDate = [];
        const updatedNtsEvents = [];
        const updatedAdhocReason = [];
        const updatedVisitIndicator = [];
        const updatedVisitEvents = [];

        document.querySelectorAll('#callTypesContainer .call-type-card').forEach((card, i) => {
            const idVal = card.querySelector('.call-prop-id').value.trim();
            if (idVal) {
                updatedCallIds.push(idVal);
                updatedCallNames.push(card.querySelector('.call-prop-name').value.trim());
                const template = card.querySelector('.call-prop-template').value;
                updatedCallTemplates.push(template);
                updatedHideAttempts.push(card.querySelector('.call-prop-hide').value.trim());

                updatedNewExpire.push(template === 'new' ? (card.querySelector('.call-prop-new-expire')?.value.trim() || '') : '');
                updatedReminderVar.push(template === 'reminder' ? (card.querySelector('.call-prop-reminder-var')?.value || '') : '');
                updatedReminderDays.push(template === 'reminder' ? (card.querySelector('.call-prop-reminder-days')?.value.trim() || '') : '');

                const remMulti = card.querySelector(`#reminder_events_${i}`);
                updatedReminderEvents.push(template === 'reminder' && remMulti ? (remMulti.selectedValues || []).join(', ') : '');

                updatedFollowupDate.push(template === 'followup' ? (card.querySelector('.call-prop-followup-date')?.value || '') : '');
                updatedFollowupDays.push(template === 'followup' ? (card.querySelector('.call-prop-followup-days')?.value.trim() || '') : '');
                const folMulti = card.querySelector(`#followup_events_${i}`);
                updatedFollowupEvents.push(template === 'followup' && folMulti ? (folMulti.selectedValues || []).join(', ') : '');

                updatedMcvIndicator.push(template === 'mcv' ? (card.querySelector('.call-prop-mcv-ind')?.value || '') : '');
                updatedMcvDate.push(template === 'mcv' ? (card.querySelector('.call-prop-mcv-date')?.value || '') : '');
                const mcvMulti = card.querySelector(`#mcv_events_${i}`);
                updatedMcvEvents.push(template === 'mcv' && mcvMulti ? (mcvMulti.selectedValues || []).join(', ') : '');

                updatedNtsIndicator.push(template === 'nts' ? (card.querySelector('.call-prop-nts-ind')?.value || '') : '');
                updatedNtsDate.push(template === 'nts' ? (card.querySelector('.call-prop-nts-date')?.value || '') : '');
                const ntsMulti = card.querySelector(`#nts_events_${i}`);
                updatedNtsEvents.push(template === 'nts' && ntsMulti ? (ntsMulti.selectedValues || []).join(', ') : '');

                updatedAdhocReason.push(template === 'adhoc' ? (card.querySelector('.call-prop-adhoc-reason')?.value.trim() || '') : '');

                updatedVisitIndicator.push(template === 'visit' ? (card.querySelector('.call-prop-visit-ind')?.value || '') : '');
                const visitMulti = card.querySelector(`#visit_events_${i}`);
                updatedVisitEvents.push(template === 'visit' && visitMulti ? (visitMulti.selectedValues || []).join(', ') : '');
            }
        });

        const updatedTabNames = [];
        const updatedTabCalls = [];
        const updatedTabOrders = [];
        const updatedTabFields = [];
        const updatedTabFieldNames = [];
        const updatedTabFieldDefaults = [];
        const updatedTabFieldLinks = [];
        const updatedTabFieldLinkInsts = [];

        document.querySelectorAll('#callTabsContainer .call-tab-card').forEach((card, idx) => {
            const nameVal = card.querySelector('.tab-prop-name').value.trim();
            if (nameVal) {
                updatedTabNames.push(nameVal);
                const multiContainer = card.querySelector('.custom-multiselect-container');
                updatedTabCalls.push(multiContainer ? (multiContainer.selectedValues || []).join(', ') : '');
                updatedTabOrders.push(idx);

                const tabF = [];
                const tabN = [];
                const tabD = [];
                const tabL = [];
                const tabI = [];

                card.querySelectorAll('.tab-field-row').forEach(fRow => {
                    const fieldVal = fRow.querySelector('.tab-field-var')?.value || '';
                    if (fieldVal) {
                        tabF.push(fieldVal);
                        tabN.push(fRow.querySelector('.tab-field-name')?.value.trim() || '');
                        tabD.push(fRow.querySelector('.tab-field-default')?.value.trim() || '');
                        const linkVal = fRow.querySelector('.tab-field-link')?.value || 'none';
                        tabL.push(linkVal);
                        tabI.push(linkVal === 'instrument' ? (fRow.querySelector('.tab-field-link-inst')?.value || '') : '');
                    }
                });

                updatedTabFields.push(tabF);
                updatedTabFieldNames.push(tabN);
                updatedTabFieldDefaults.push(tabD);
                updatedTabFieldLinks.push(tabL);
                updatedTabFieldLinkInsts.push(tabI);
            }
        });

        const updatedExpandsFields = [];
        const updatedExpandsNames = [];
        const updatedExpandsDefaults = [];

        document.querySelectorAll('#expandsFieldsContainer .expands-field-row').forEach(row => {
            const fieldVal = row.querySelector('.expands-prop-field').value;
            if (fieldVal) {
                updatedExpandsFields.push(fieldVal);
                updatedExpandsNames.push(row.querySelector('.expands-prop-name').value.trim());
                updatedExpandsDefaults.push(row.querySelector('.expands-prop-default').value.trim());
            }
        });

        const updatedWithdrawEvents = [];
        const updatedWithdrawVars = [];

        document.querySelectorAll('#withdrawRulesContainer .withdraw-rule-row').forEach(row => {
            const eventEl = row.querySelector('.withdraw-prop-event');
            const varEl = row.querySelector('.withdraw-prop-var');
            const varVal = varEl ? varEl.value : '';
            if (varVal) {
                updatedWithdrawVars.push(varVal);
                updatedWithdrawEvents.push(eventEl ? eventEl.value : '');
            }
        });

        const updatedEnabledHolidays = [];
        document.querySelectorAll('#standardHolidaysContainer .holiday-chk:checked').forEach(chk => {
            updatedEnabledHolidays.push(chk.value);
        });

        const updatedCustomHolidayDates = [];
        const updatedCustomHolidayNames = [];
        document.querySelectorAll('#customHolidaysContainer .custom-holiday-row').forEach(row => {
            const dateVal = row.querySelector('.custom-holiday-date')?.value.trim() || '';
            if (dateVal) {
                updatedCustomHolidayDates.push(dateVal);
                updatedCustomHolidayNames.push(row.querySelector('.custom-holiday-name')?.value.trim() || '');
            }
        });

        const payload = {
            trigger_save: triggerSaveContainer.selectedValues || [],
            same_day_mcv_nts: [document.getElementById('cfg_same_day_mcv_nts').checked ? '1' : '0'],
            enabled_holidays: updatedEnabledHolidays,
            custom_holidays_date: updatedCustomHolidayDates,
            custom_holidays_name: updatedCustomHolidayNames,
            call_summary: callSummaryContainer.selectedValues || [],
            withdraw_event: updatedWithdrawEvents,
            withdraw_var: updatedWithdrawVars,
            call_id: updatedCallIds,
            call_name: updatedCallNames,
            call_template: updatedCallTemplates,
            hide_after_attempts: updatedHideAttempts,
            new_expire_days: updatedNewExpire,
            reminder_variable: updatedReminderVar,
            reminder_days: updatedReminderDays,
            reminder_include_events: updatedReminderEvents,
            followup_date: updatedFollowupDate,
            followup_days: updatedFollowupDays,
            followup_include_events: updatedFollowupEvents,
            mcv_indicator: updatedMcvIndicator,
            mcv_date: updatedMcvDate,
            mcv_include_events: updatedMcvEvents,
            nts_indicator: updatedNtsIndicator,
            nts_date: updatedNtsDate,
            nts_include_events: updatedNtsEvents,
            adhoc_reason: updatedAdhocReason,
            visit_indicator: updatedVisitIndicator,
            visit_include_events: updatedVisitEvents,
            tab_name: updatedTabNames,
            tab_calls_included: updatedTabCalls,
            tab_order: updatedTabOrders,
            tab_field: updatedTabFields,
            tab_field_name: updatedTabFieldNames,
            tab_field_default: updatedTabFieldDefaults,
            tab_field_link: updatedTabFieldLinks,
            tab_field_link_instrument: updatedTabFieldLinkInsts,
            tab_expands_field: updatedExpandsFields,
            tab_expands_field_name: updatedExpandsNames,
            tab_expands_field_default: updatedExpandsDefaults
        };

        module.ajax("saveConfig", { settings: payload }).then(function (response) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-1"></i> Save Configuration';
            if (response && response.saved) {
                Swal.fire({
                    icon: 'success',
                    title: 'Configuration Saved',
                    text: 'Call Log settings were updated successfully.',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Save Failed', text: 'Could not update settings.' });
            }
        }).catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-1"></i> Save Configuration';
            Swal.fire({ icon: 'error', title: 'Error', text: err.message || err });
        });
    });
})();
