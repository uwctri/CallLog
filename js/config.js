(() => {
    const module = ExternalModules.UWMadison.CallLog;
    const { toArray, getVal } = module.utils || {};
    const starterCallScripts = {
        new: `<p>Hello <strong>{{participant_name}}</strong>, my name is <strong>[user-fullname]</strong> calling with the research study team.</p>
<p>I am reaching out regarding your recent enrollment in our study. We are excited to have you participate! This introductory call takes about <strong>{{expected_duration}} minutes</strong>.</p>
<p>During this call, I will:</p>
<ol>
  <li>Verify your contact details and preferred method of communication.</li>
  <li>Review the study timeline and upcoming milestones.</li>
  <li>Answer any initial questions you might have about participating.</li>
</ol>
<p>Is now a good time to speak for a few minutes?</p>`,

        reminder: `<p>Hello <strong>{{participant_name}}</strong>, this is <strong>[user-fullname]</strong> calling from the research team to remind you of your upcoming appointment scheduled for <strong>{{call_date}}</strong> at <strong>{{call_time}}</strong>.</p>
<p><strong>Please remember:</strong></p>
<ul>
  <li>Arrive approximately 10 minutes early.</li>
  <li>Bring a photo ID and your current medication list.</li>
  <li>If you have any questions or need to reschedule, please let us know.</li>
</ul>
<p>Can we confirm that you are still able to make this appointment on <strong>{{call_date}}</strong>?</p>`,

        followup: `<p>Hello <strong>{{participant_name}}</strong>, this is <strong>[user-fullname]</strong> checking in on behalf of the research study team.</p>
<p>This is our scheduled follow-up call regarding your progress and recent study milestone. This brief check-in will take approximately <strong>{{expected_duration}} minutes</strong>.</p>
<p>We would like to:</p>
<ol>
  <li>Check in on your general health and how you have been feeling since your last visit.</li>
  <li>Review any changes in medications or study protocol compliance.</li>
  <li>Verify that any home health surveys or diary entries are up to date.</li>
</ol>
<p>Do you have a few moments to review these items with me today?</p>`,

        mcv: `<p>Hello <strong>{{participant_name}}</strong>, this is <strong>[user-fullname]</strong> with the research study team.</p>
<p>We missed you at your scheduled appointment on <strong>{{call_date}}</strong>. We wanted to check in to make sure everything is okay!</p>
<p>Your participation is vital to our study, and we would love to get you rescheduled at a time that works best for you.</p>
<p>Please give us a call back at your earliest convenience or let me know what day and time works best for your visit. Thank you!</p>`,

        nts: `<p>Hello <strong>{{participant_name}}</strong>, this is <strong>[user-fullname]</strong> calling from the research study team.</p>
<p>According to our study records, you are now due to schedule your next milestone visit (<strong>[event-name]</strong>).</p>
<p>The upcoming visit will take approximately <strong>{{expected_duration}} minutes</strong>. We have openings available across the next two weeks.</p>
<p>What day of the week or time of day generally works best for your schedule?</p>`,

        adhoc: `<p>Hello <strong>{{participant_name}}</strong>, this is <strong>[user-fullname]</strong> calling from the research study team.</p>
<p>I am reaching out today regarding: <strong>{{reason}}</strong>.</p>
<p>This call should take about <strong>{{expected_duration}} minutes</strong>. Is now a convenient time for you to speak, or would another time today or later this week work better?</p>`,

        visit: `<p>Hello <strong>{{participant_name}}</strong>, this is <strong>[user-fullname]</strong> calling for your scheduled phone visit today.</p>
<p>Today we will be conducting your study evaluation over the phone. This encounter is expected to take approximately <strong>{{expected_duration}} minutes</strong>.</p>
<p><strong>Before we begin:</strong></p>
<ol>
  <li>Can you confirm your full name and date of birth?</li>
  <li>Are you in a quiet and private space where you are comfortable speaking?</li>
</ol>
<p>Let's begin with today's questions...</p>`
    };

    function registerComponent() {
        Alpine.data('callLogConfig', () => ({
            raw: module.rawConfig || {},
            meta: module.metaInfo || {},

            activeTab: 'status',
            saving: false,
            deploying: false,
            enablingRepeatable: false,

            openDropdowns: {},

            sameDayMcvNts: false,
            showRecordHomeButton: true,
            showCallLogInstrument: false,
            showMetadataInstrument: false,
            datetimeFormat: 'm/d/Y g:i A',
            displayNameField: '',
            triggerSave: [],
            callSummary: [],

            formatPreview(fmt) {
                let now = new Date();
                let norm = (module.utils && module.utils.normalizePhpDateFormat)
                    ? module.utils.normalizePhpDateFormat(fmt || 'm/d/Y g:i A')
                    : (fmt || 'm/d/Y g:i A');
                return (module.utils && module.utils.formatPhpDate)
                    ? module.utils.formatPhpDate(now, norm)
                    : '';
            },

            callTypes: [],
            callTabs: [],
            expandsFields: [],
            withdrawRules: [],
            enabledHolidays: [],
            customHolidays: [],

            init() {
                this.loadFromRaw();
                this.$watch('activeTab', (tab) => {
                    if (tab === 'calls') {
                        this.$nextTick(() => {
                            this.initAllScriptEditors();
                        });
                    }
                });
                if (this.activeTab === 'calls') {
                    this.$nextTick(() => {
                        this.initAllScriptEditors();
                    });
                }
            },

            get defaultHolidaysMap() {
                return this.meta.defaultHolidayMap || {};
            },

            get dateFields() {
                if (this.meta.dateFields && Array.isArray(this.meta.dateFields)) {
                    return this.meta.dateFields;
                }
                return (this.meta.fields || []).filter(field => field.isDate);
            },

            getFieldLabel(id) {
                if (!id) return '';
                const f = (this.meta.fields || []).find(field => field.id === id);
                return f ? (f.label || `${f.id} (${f.name || f.id})`) : id;
            },

            getInstrumentLabel(id) {
                if (!id) return '';
                const inst = (this.meta.instruments || []).find(i => String(i.id) === String(id));
                return inst ? `${inst.name || inst.label || inst.id} (${inst.id})` : id;
            },

            getEventLabel(id) {
                if (!id) return '';
                const evt = (this.meta.events || []).find(e => String(e.id) === String(id));
                return evt ? `${evt.name} (${evt.unique})` : id;
            },

            toggleDropdown(key) {
                this.openDropdowns[key] = !this.openDropdowns[key];
            },

            isDropdownOpen(key) {
                return !!this.openDropdowns[key];
            },

            closeDropdown(key) {
                this.openDropdowns[key] = false;
            },

            fieldSelect(targetObj, propKey, placeholder = '-- Select Field --', isDateOnly = false) {
                const self = this;
                const isDateField = isDateOnly || 
                    (typeof placeholder === 'string' && placeholder.toLowerCase().includes('date')) || 
                    ['reminderVariable', 'followupDate', 'mcvDate', 'ntsDate'].includes(propKey);

                return {
                    open: false,
                    filter: '',
                    placeholder: placeholder,
                    isDateOnly: isDateField,
                    get val() { return targetObj[propKey] || ''; },
                    set val(v) { targetObj[propKey] = v; },
                    select(id) {
                        targetObj[propKey] = id;
                        this.open = false;
                    },
                    get filteredFields() {
                        const f = this.filter ? this.filter.toLowerCase() : '';
                        const pool = this.isDateOnly
                            ? self.dateFields
                            : (self.meta.fields || []);
                        if (!f) return pool;
                        return pool.filter(item =>
                            (item.id && item.id.toLowerCase().includes(f)) ||
                            (item.label && item.label.toLowerCase().includes(f)) ||
                            (item.name && item.name.toLowerCase().includes(f))
                        );
                    }
                };
            },

            eventSelect(targetArr) {
                const self = this;
                return {
                    open: false,
                    get singleEvent() {
                        return self.meta.events && self.meta.events.length === 1;
                    },
                    toggle(id) {
                        self.toggleMultiselectItem(targetArr, id);
                    },
                    isSelected(id) {
                        return (targetArr || []).includes(String(id));
                    }
                };
            },

            multiSelect(targetArr) {
                const self = this;
                return {
                    open: false,
                    toggle(id) {
                        self.toggleMultiselectItem(targetArr, id);
                    },
                    isSelected(id) {
                        return (targetArr || []).includes(String(id));
                    }
                };
            },

            loadFromRaw() {
                const r = this.raw || {};
                const singleEventId = (this.meta.events && this.meta.events.length === 1) ? String(this.meta.events[0].id) : null;

                this.sameDayMcvNts = Boolean(r.same_day_mcv_nts && (r.same_day_mcv_nts[0] === '1' || r.same_day_mcv_nts === '1' || r.same_day_mcv_nts === true));
                this.showRecordHomeButton = (r.show_record_home_button === undefined || r.show_record_home_button === null)
                    ? true
                    : Boolean(r.show_record_home_button && (r.show_record_home_button[0] === '1' || r.show_record_home_button === '1' || r.show_record_home_button === true));
                this.showCallLogInstrument = Boolean(r.show_call_log_instrument && (r.show_call_log_instrument[0] === '1' || r.show_call_log_instrument === '1' || r.show_call_log_instrument === true));
                this.showMetadataInstrument = Boolean(r.show_metadata_instrument && (r.show_metadata_instrument[0] === '1' || r.show_metadata_instrument === '1' || r.show_metadata_instrument === true));
                this.datetimeFormat = (r.datetime_format && r.datetime_format[0]) ? r.datetime_format[0] : 'm/d/Y g:i A';
                this.displayNameField = (r.display_name_field && r.display_name_field[0]) ? r.display_name_field[0] : (r.display_name_field || '');
                this.triggerSave = toArray(r.trigger_save);
                this.callSummary = toArray(r.call_summary);

                const ids = toArray(r.call_id);
                const names = toArray(r.call_name);
                const templates = toArray(r.call_template);
                const hides = toArray(r.hide_after_attempts);
                const durations = r.call_expected_duration || [];

                const newExp = r.new_expire_days || [];
                const remVar = r.reminder_variable || [];
                const remDays = r.reminder_days || [];
                const remEvts = r.reminder_include_events || [];
                const folDate = r.followup_date || [];
                const folDays = r.followup_days || [];
                const folEvts = r.followup_include_events || [];
                const mcvInd = r.mcv_indicator || [];
                const mcvDate = r.mcv_date || [];
                const mcvEvts = r.mcv_include_events || [];
                const ntsInd = r.nts_indicator || [];
                const ntsDate = r.nts_date || [];
                const ntsEvts = r.nts_include_events || [];
                const adhocRea = r.adhoc_reason || [];
                const visInd = r.visit_indicator || [];
                const visEvts = r.visit_include_events || [];

                const scripts = r.call_script || [];

                this.callTypes = ids.map((id, i) => {
                    let rEvts = toArray(remEvts[i]);
                    let fEvts = toArray(folEvts[i]);
                    let mEvts = toArray(mcvEvts[i]);
                    let nEvts = toArray(ntsEvts[i]);
                    let vEvts = toArray(visEvts[i]);

                    if (singleEventId) {
                        rEvts = [singleEventId];
                        fEvts = [singleEventId];
                        mEvts = [singleEventId];
                        nEvts = [singleEventId];
                        vEvts = [singleEventId];
                    }

                    const tmpl = templates[i] || 'new';
                    return {
                        _uid: 'ct_' + (i + 1) + '_' + Math.random().toString(36).substring(2, 7),
                        id: id || '',
                        name: names[i] || '',
                        template: tmpl,
                        script: getVal(scripts, i, ''),
                        guideOpen: false,
                        fieldPickerOpen: false,
                        fieldPickerFilter: '',
                        hideAfterAttempt: hides[i] || '',
                        expectedDuration: getVal(durations, i, 30),
                        newExpireDays: getVal(newExp, i, ''),
                        reminderVariable: getVal(remVar, i, ''),
                        reminderDays: getVal(remDays, i, ''),
                        reminderEvents: rEvts,
                        followupDate: getVal(folDate, i, ''),
                        followupDays: getVal(folDays, i, ''),
                        followupEvents: fEvts,
                        mcvIndicator: getVal(mcvInd, i, ''),
                        mcvDate: getVal(mcvDate, i, ''),
                        mcvEvents: mEvts,
                        ntsIndicator: getVal(ntsInd, i, ''),
                        ntsDate: getVal(ntsDate, i, ''),
                        ntsEvents: nEvts,
                        adhocReason: getVal(adhocRea, i, ''),
                        visitIndicator: getVal(visInd, i, ''),
                        visitEvents: vEvts
                    };
                });

                const tNames = toArray(r.tab_name);
                const tCalls = r.tab_calls_included || [];
                const tFields = r.tab_field || [];
                const tFNames = r.tab_field_name || [];
                const tFDefs = r.tab_field_default || [];
                const tFLinks = r.tab_field_link || [];
                const tFInsts = r.tab_field_link_instrument || [];

                this.callTabs = tNames.map((name, i) => {
                    const rawInc = tCalls[i];
                    let incList = [];
                    if (Array.isArray(rawInc)) {
                        rawInc.forEach(item => {
                            if (typeof item === 'string') {
                                item.split(',').forEach(part => {
                                    const trimmed = part.trim();
                                    if (trimmed && !incList.includes(trimmed)) incList.push(trimmed);
                                });
                            } else if (item) {
                                const s = String(item).trim();
                                if (s && !incList.includes(s)) incList.push(s);
                            }
                        });
                    } else if (typeof rawInc === 'string') {
                        incList = rawInc.split(',').map(s => s.trim()).filter(Boolean);
                    }
                    const fieldsList = (tFields[i] && Array.isArray(tFields[i])) ? tFields[i] : [];
                    const namesList = (tFNames[i] && Array.isArray(tFNames[i])) ? tFNames[i] : [];
                    const defsList = (tFDefs[i] && Array.isArray(tFDefs[i])) ? tFDefs[i] : [];
                    const linksList = (tFLinks[i] && Array.isArray(tFLinks[i])) ? tFLinks[i] : [];
                    const instsList = (tFInsts[i] && Array.isArray(tFInsts[i])) ? tFInsts[i] : [];

                    const fields = fieldsList.map((f, j) => ({
                        field: getVal(fieldsList, j, ''),
                        name: getVal(namesList, j, ''),
                        default: getVal(defsList, j, ''),
                        link: getVal(linksList, j, 'none'),
                        linkedInstrument: getVal(instsList, j, '')
                    })).filter(f => f.field);

                    return {
                        name: name || '',
                        callsIncluded: incList,
                        fields: fields
                    };
                });

                const unwrapExpands = (val) => {
                    if (!val) return [];
                    if (Array.isArray(val)) {
                        if (val.length >= 1 && Array.isArray(val[0])) {
                            return val[0];
                        }
                        return val;
                    }
                    if (typeof val === 'string') {
                        return val.split(',').map(s => s.trim());
                    }
                    return [val];
                };

                const eFieldsArr = unwrapExpands(r.tab_expands_field).map(f => String(f ?? '').trim());
                const eNamesArr = unwrapExpands(r.tab_expands_field_name).map(n => String(n ?? '').trim());
                const eDefsArr = unwrapExpands(r.tab_expands_field_default).map(d => String(d ?? '').trim());

                this.expandsFields = eFieldsArr.map((f, i) => ({
                    field: f || '',
                    name: (eNamesArr[i] !== undefined && eNamesArr[i] !== null) ? eNamesArr[i] : '',
                    default: (eDefsArr[i] !== undefined && eDefsArr[i] !== null) ? eDefsArr[i] : ''
                })).filter(e => e.field);

                const wEvents = r.withdraw_event || [];
                const wVars = r.withdraw_var || [];
                const wVarsArr = toArray(wVars);
                this.withdrawRules = wVarsArr.map((v, i) => ({
                    event: singleEventId ? singleEventId : getVal(wEvents, i, ''),
                    var: v || ''
                })).filter(w => w.var);

                this.enabledHolidays = (r.enabled_holidays && r.enabled_holidays.length) ? toArray(r.enabled_holidays) : Object.keys(this.defaultHolidaysMap);
                const cDates = toArray(r.custom_holidays_date);
                const cNames = toArray(r.custom_holidays_name);
                this.customHolidays = cDates.map((d, i) => ({
                    date: d || '',
                    name: getVal(cNames, i, '')
                })).filter(c => c.date);
            },

            get totalGeneratedCalls() {
                return this.meta.totalCalls || 0;
            },

            get completedCalls() {
                return this.meta.completedCalls || 0;
            },

            get totalCallAttempts() {
                return this.meta.totalAttempts || 0;
            },

            get uniqueCallers() {
                return this.meta.uniqueCallers || 0;
            },

            get setupChecklist() {
                const hasDeployment = !!(this.meta.instrumentsDeployed);
                const eventConfig = this.meta.instrumentEventConfig || {};
                const hasEventRepeat = !!(eventConfig.valid);
                const hasTrigger = (this.triggerSave.length > 0);
                const hasCalls = (this.callTypes.length > 0 && this.callTypes.some(c => c.id && c.name));
                const hasTabs = (this.callTabs.length > 0 && this.callTabs.some(t => t.name));
                const hasWithdraw = (this.withdrawRules.length > 0 && this.withdrawRules.some(w => w.var));

                return {
                    deployment: hasDeployment,
                    eventRepeat: hasEventRepeat,
                    eventConfig: eventConfig,
                    trigger: hasTrigger,
                    calls: hasCalls,
                    tabs: hasTabs,
                    withdraw: hasWithdraw,
                    allComplete: hasDeployment && hasEventRepeat && hasTrigger && hasCalls && hasTabs
                };
            },

            getAvailableCallIds() {
                return this.callTypes.map(c => c.id.trim()).filter(Boolean);
            },

            getTabExtraInfo(includedIds) {
                if (!includedIds || includedIds.length === 0) return [];
                const templatesFound = new Set();
                includedIds.forEach(id => {
                    const found = this.callTypes.find(c => c.id.trim() === id);
                    if (found && found.template) templatesFound.add(found.template);
                });

                const opts = this.meta.callTemplateOptions || {};
                const items = [];
                const isOnlyNew = templatesFound.has('new') && templatesFound.size === 1;
                const dateColName = isOnlyNew ? 'Generated' : 'Call Date/Time';
                items.push(`Standard Columns: Record ID, Attempts, Name, ${dateColName} (Call Notes & details in expanded rows)`);
                if (templatesFound.has('reminder') || templatesFound.has('followup')) {
                    items.push(`<strong>${opts.reminder || 'Reminder'} / ${opts.followup || 'Follow Up'} Windows:</strong> Window Start & End Dates, Callback Requestor info & Stopwatch badge`);
                }
                if (templatesFound.has('mcv')) {
                    items.push(`<strong>${opts.mcv || 'Missed / Cancelled Visit'} / MCV:</strong> Appointment Date & Time, Missed Visit Indicator status`);
                }
                if (templatesFound.has('nts')) {
                    items.push(`<strong>${opts.nts || 'Need to Schedule'} / NTS:</strong> Scheduled Appointment Date, Days-Before Window tracking`);
                }
                if (templatesFound.has('adhoc')) {
                    items.push(`<strong>${opts.adhoc || 'Adhoc'} Calls:</strong> Target Adhoc Available Date, Adhoc Reason selection`);
                }
                if (templatesFound.has('new')) {
                    items.push(`<strong>${opts.new || 'New Entry'} Calls:</strong> Generated Date, Entry Expiration Status & Days remaining`);
                }
                if (templatesFound.has('visit')) {
                    items.push(`<strong>${opts.visit || 'Scheduled Phone Visit'} Calls:</strong> Visit Window Indicator status`);
                }
                return items;
            },

            addCallType() {
                const singleEventId = (this.meta.events && this.meta.events.length === 1) ? String(this.meta.events[0].id) : null;
                const defaultEvts = singleEventId ? [singleEventId] : [];

                const nextNum = this.callTypes.length + 1;
                const newCallType = {
                    _uid: 'ct_' + nextNum + '_' + Math.random().toString(36).substring(2, 7),
                    id: 'call_' + nextNum,
                    name: 'New Call Type ' + nextNum,
                    template: 'new',
                    script: this.getStarterScript('new'),
                    guideOpen: false,
                    fieldPickerOpen: false,
                    fieldPickerFilter: '',
                    hideAfterAttempt: '',
                    expectedDuration: 30,
                    newExpireDays: '',
                    reminderVariable: '',
                    reminderDays: '',
                    reminderEvents: [...defaultEvts],
                    followupDate: '',
                    followupDays: '',
                    followupEvents: [...defaultEvts],
                    mcvIndicator: '',
                    mcvDate: '',
                    mcvEvents: [...defaultEvts],
                    ntsIndicator: '',
                    ntsDate: '',
                    ntsEvents: [...defaultEvts],
                    adhocReason: '',
                    visitIndicator: '',
                    visitEvents: [...defaultEvts]
                };
                this.callTypes.push(newCallType);
                this.$nextTick(() => {
                    this.initScriptEditor(newCallType);
                });
            },

            removeCallType(index) {
                const ct = this.callTypes[index];
                if (ct) {
                    this.destroyScriptEditor(ct);
                }
                this.callTypes.splice(index, 1);
            },

            getStarterScript(template) {
                return starterCallScripts[template] || starterCallScripts['new'] || '';
            },

            initScriptEditor(callType) {
                if (!callType || !callType._uid) return;
                const editorId = 'call_script_' + callType._uid;

                const doInit = () => {
                    if (typeof window.tinymce === 'undefined') return;
                    if (window.tinymce.get(editorId)) {
                        return; // already initialized
                    }
                    const el = document.getElementById(editorId);
                    if (!el) return;

                    const webroot = window.app_path_webroot || '';
                    window.tinymce.init({
                        selector: '#' + editorId,
                        height: 250,
                        license_key: 'gpl',
                        branding: false,
                        statusbar: true,
                        elementpath: false,
                        menubar: false,
                        plugins: 'autolink lists link image searchreplace code fullscreen table directionality hr',
                        toolbar1: 'fontfamily blocks fontsize bold italic underline strikethrough forecolor backcolor',
                        toolbar2: 'align bullist numlist outdent indent table pre hr link fullscreen searchreplace removeformat undo redo code',
                        content_css: webroot + "Resources/webpack/css/bootstrap.min.css," + webroot + "Resources/webpack/css/fontawesome/css/all.min.css," + webroot + "Resources/css/style.css",
                        relative_urls: false,
                        convert_urls: false,
                        extended_valid_elements: 'i[class]',
                        setup: function(editor) {
                            const sync = () => {
                                callType.script = editor.getContent();
                            };
                            editor.on('change keyup NodeChange SetContent', sync);
                            editor.on('blur', () => {
                                sync();
                                if (typeof window.tinymce.triggerSave === 'function') {
                                    window.tinymce.triggerSave();
                                }
                            });
                        }
                    });
                };

                if (typeof window.tinymce === 'undefined') {
                    const webroot = window.app_path_webroot || '';
                    if (typeof window.loadJS === 'function' && webroot) {
                        window.loadJS(webroot + "Resources/webpack/css/tinymce/tinymce.min.js");
                        let tries = 0;
                        const checkInterval = setInterval(() => {
                            tries++;
                            if (typeof window.tinymce !== 'undefined') {
                                clearInterval(checkInterval);
                                doInit();
                            } else if (tries > 50) {
                                clearInterval(checkInterval);
                            }
                        }, 100);
                    }
                } else {
                    doInit();
                }
            },

            destroyScriptEditor(callType) {
                if (window.tinymce && callType && callType._uid) {
                    const ed = window.tinymce.get('call_script_' + callType._uid);
                    if (ed) {
                        try {
                            ed.remove();
                        } catch (e) {}
                    }
                }
            },

            initAllScriptEditors() {
                if (!Array.isArray(this.callTypes)) return;
                this.callTypes.forEach(ct => {
                    this.initScriptEditor(ct);
                });
            },

            insertScriptTag(callType, tag) {
                const editorId = 'call_script_' + callType._uid;
                const editor = (window.tinymce && window.tinymce.get) ? window.tinymce.get(editorId) : null;
                if (editor && !editor.isHidden()) {
                    editor.insertContent(tag);
                    editor.focus();
                    callType.script = editor.getContent();
                } else {
                    const el = document.getElementById(editorId);
                    if (!el) {
                        callType.script = (callType.script || '') + tag;
                        return;
                    }
                    const start = el.selectionStart ?? el.value.length;
                    const end = el.selectionEnd ?? el.value.length;
                    const current = el.value || '';
                    const updated = current.substring(0, start) + tag + current.substring(end);
                    callType.script = updated;
                    el.value = updated;
                    this.$nextTick(() => {
                        el.focus();
                        el.setSelectionRange(start + tag.length, start + tag.length);
                    });
                }
            },

            setScriptContent(callType, html) {
                callType.script = html;
                const editorId = 'call_script_' + callType._uid;
                const editor = (window.tinymce && window.tinymce.get) ? window.tinymce.get(editorId) : null;
                if (editor) {
                    editor.setContent(html);
                }
                const el = document.getElementById(editorId);
                if (el) {
                    el.value = html;
                }
            },

            confirmLoadStarterScript(callType) {
                const starter = this.getStarterScript(callType.template);
                const current = (callType.script || '').trim();
                if (!current || current === '<p></p>' || current === '<p><br></p>') {
                    this.setScriptContent(callType, starter);
                    return;
                }
                const templateName = this.meta.callTemplateOptions?.[callType.template] || callType.template;
                const title = 'Replace Call Script?';
                const text = `This will overwrite your current call script with the starter template for ${templateName}.`;
                
                const executeReplace = () => this.setScriptContent(callType, starter);

                if (module.swal && typeof module.swal.confirm === 'function') {
                    module.swal.confirm(title, text, 'Replace Script', true).then(confirmed => {
                        if (confirmed) executeReplace();
                    });
                } else if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: title,
                        text: text,
                        showCancelButton: true,
                        confirmButtonText: 'Replace Script',
                        confirmButtonColor: '#dc2626'
                    }).then(res => {
                        if (res.isConfirmed) executeReplace();
                    });
                } else if (confirm(text)) {
                    executeReplace();
                }
            },

            addCallTab() {
                this.callTabs.push({
                    name: 'Tab ' + (this.callTabs.length + 1),
                    callsIncluded: [],
                    fields: []
                });
            },

            removeCallTab(index) {
                this.callTabs.splice(index, 1);
            },

            draggedTabIdx: null,

            dragTabStart(e, idx) {
                this.draggedTabIdx = idx;
                if (e.dataTransfer) {
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', String(idx));
                }
            },

            dropTab(e, targetIdx) {
                if (this.draggedTabIdx === null || this.draggedTabIdx === targetIdx) return;
                const item = this.callTabs.splice(this.draggedTabIdx, 1)[0];
                this.callTabs.splice(targetIdx, 0, item);
                this.draggedTabIdx = null;
            },

            dragTabEnd() {
                this.draggedTabIdx = null;
            },

            addTabField(tabIndex) {
                this.callTabs[tabIndex].fields.push({
                    field: '',
                    name: '',
                    default: '',
                    link: 'none',
                    linkedInstrument: ''
                });
            },

            removeTabField(tabIndex, fieldIndex) {
                this.callTabs[tabIndex].fields.splice(fieldIndex, 1);
            },

            addExpandsField() {
                this.expandsFields.push({ field: '', name: '', default: '' });
            },

            removeExpandsField(index) {
                this.expandsFields.splice(index, 1);
            },

            addWithdrawRule() {
                const singleEventId = (this.meta.events && this.meta.events.length === 1) ? String(this.meta.events[0].id) : '';
                this.withdrawRules.push({ event: singleEventId, var: '' });
            },

            removeWithdrawRule(index) {
                this.withdrawRules.splice(index, 1);
            },

            addCustomHoliday() {
                this.customHolidays.push({ date: '', name: '' });
            },

            removeCustomHoliday(index) {
                this.customHolidays.splice(index, 1);
            },

            toggleHoliday(key) {
                const sKey = String(key);
                const idx = this.enabledHolidays.indexOf(sKey);
                if (idx === -1) {
                    this.enabledHolidays.push(sKey);
                } else {
                    this.enabledHolidays.splice(idx, 1);
                }
            },

            toggleMultiselectItem(arrayRef, val) {
                const sVal = String(val);
                const idx = arrayRef.indexOf(sVal);
                if (idx === -1) {
                    arrayRef.push(sVal);
                } else {
                    arrayRef.splice(idx, 1);
                }
            },

            deployInstruments() {
                const events = this.meta.events || [];
                const targetEventId = events.length === 1 ? events[0].id : (document.getElementById('deployTargetEventSelect')?.value || null);
                if (events.length > 1 && !targetEventId) {
                    (module.swal ? module.swal.warning : Swal.fire)({
                        title: 'Event Required',
                        text: 'Please select a target event before deploying instruments.'
                    });
                    return;
                }

                this.deploying = true;
                module.ajax("deployInstruments", { event_id: targetEventId }).then((res) => {
                    this.deploying = false;
                    if (res && res.success) {
                        (module.swal ? module.swal.success : Swal.fire)({
                            title: 'Instruments Deployed',
                            text: res.message || 'Call Log instruments were deployed successfully.',
                            confirmButtonText: '<i class="fas fa-check me-1.5"></i> Continue'
                        }).then(() => location.reload());
                    } else {
                        (module.swal ? module.swal.error : Swal.fire)({
                            title: 'Deployment Failed',
                            text: res.message || 'Could not deploy Call Log instruments.'
                        });
                    }
                }).catch(err => {
                    this.deploying = false;
                    (module.swal ? module.swal.error : Swal.fire)({
                        title: 'Deployment Error',
                        text: err.message || String(err)
                    });
                });
            },

            enableRepeatable(targetEventId = null) {
                this.enablingRepeatable = true;
                const eventId = targetEventId || (this.meta.instrumentEventConfig && this.meta.instrumentEventConfig.assignedEventId) || null;

                module.ajax("enableRepeatable", { event_id: eventId }).then((res) => {
                    this.enablingRepeatable = false;
                    if (res && res.success) {
                        (module.swal ? module.swal.success : Swal.fire)({
                            title: 'Repeatable Enabled',
                            text: res.message || 'Call Log instrument has been successfully configured as repeatable.',
                            confirmButtonText: '<i class="fas fa-check me-1.5"></i> Continue'
                        }).then(() => location.reload());
                    } else {
                        (module.swal ? module.swal.error : Swal.fire)({
                            title: 'Setup Failed',
                            text: res.message || 'Error updating repeatable instrument status.'
                        });
                    }
                }).catch(err => {
                    this.enablingRepeatable = false;
                    (module.swal ? module.swal.error : Swal.fire)({
                        title: 'Configuration Error',
                        text: err.message || String(err)
                    });
                });
            },

            saveConfig() {
                this.saving = true;

                if (window.tinymce && typeof window.tinymce.triggerSave === 'function') {
                    try {
                        window.tinymce.triggerSave();
                    } catch (e) {}
                }

                const payload = {
                    show_record_home_button: [this.showRecordHomeButton ? '1' : '0'],
                    show_call_log_instrument: [this.showCallLogInstrument ? '1' : '0'],
                    show_metadata_instrument: [this.showMetadataInstrument ? '1' : '0'],
                    datetime_format: [this.datetimeFormat ? this.datetimeFormat.trim() : 'm/d/Y g:i A'],
                    display_name_field: [this.displayNameField ? this.displayNameField.trim() : ''],
                    trigger_save: this.triggerSave,
                    same_day_mcv_nts: [this.sameDayMcvNts ? '1' : '0'],
                    enabled_holidays: this.enabledHolidays,
                    custom_holidays_date: this.customHolidays.map(h => h.date.trim()).filter(Boolean),
                    custom_holidays_name: this.customHolidays.map(h => h.name.trim()).filter(Boolean),
                    call_summary: this.callSummary,
                    withdraw_event: this.withdrawRules.map(w => w.event),
                    withdraw_var: this.withdrawRules.map(w => w.var).filter(Boolean),
                    call_id: this.callTypes.map(c => c.id.trim()),
                    call_name: this.callTypes.map(c => c.name.trim()),
                    call_template: this.callTypes.map(c => c.template),
                    call_script: this.callTypes.map(c => c.script || ''),
                    call_expected_duration: this.callTypes.map(c => c.expectedDuration || 30),
                    hide_after_attempts: this.callTypes.map(c => c.hideAfterAttempt),
                    new_expire_days: this.callTypes.map(c => c.newExpireDays),
                    reminder_variable: this.callTypes.map(c => c.reminderVariable),
                    reminder_days: this.callTypes.map(c => c.reminderDays),
                    reminder_include_events: this.callTypes.map(c => (c.reminderEvents || []).join(', ')),
                    followup_date: this.callTypes.map(c => c.followupDate),
                    followup_days: this.callTypes.map(c => c.followupDays),
                    followup_include_events: this.callTypes.map(c => (c.followupEvents || []).join(', ')),
                    mcv_indicator: this.callTypes.map(c => c.mcvIndicator),
                    mcv_date: this.callTypes.map(c => c.mcvDate),
                    mcv_include_events: this.callTypes.map(c => (c.mcvEvents || []).join(', ')),
                    nts_indicator: this.callTypes.map(c => c.ntsIndicator),
                    nts_date: this.callTypes.map(c => c.ntsDate),
                    nts_include_events: this.callTypes.map(c => (c.ntsEvents || []).join(', ')),
                    adhoc_reason: this.callTypes.map(c => c.adhocReason),
                    visit_indicator: this.callTypes.map(c => c.visitIndicator),
                    visit_include_events: this.callTypes.map(c => (c.visitEvents || []).join(', ')),
                    tab_name: this.callTabs.map(t => t.name.trim()),
                    tab_calls_included: this.callTabs.map(t => (t.callsIncluded || []).join(', ')),
                    tab_order: this.callTabs.map((_, i) => i),
                    tab_field: this.callTabs.map(t => (t.fields || []).map(f => f.field).filter(Boolean)),
                    tab_field_name: this.callTabs.map(t => (t.fields || []).map(f => f.name.trim())),
                    tab_field_default: this.callTabs.map(t => (t.fields || []).map(f => f.default.trim())),
                    tab_field_link: this.callTabs.map(t => (t.fields || []).map(f => f.link)),
                    ...(() => {
                        const validExpands = (this.expandsFields || []).filter(e => e && e.field && String(e.field).trim());
                        return {
                            tab_expands_field: validExpands.map(e => String(e.field).trim()),
                            tab_expands_field_name: validExpands.map(e => String(e.name || '').trim()),
                            tab_expands_field_default: validExpands.map(e => String(e.default || '').trim())
                        };
                    })()
                };

                module.ajax("saveConfig", { settings: payload }).then((res) => {
                    this.saving = false;
                    if (res && res.saved) {
                        (module.swal ? module.swal.fire : Swal.fire)({
                            icon: 'success',
                            title: 'Configuration Saved',
                            text: 'Call Log settings were updated successfully.',
                            timer: 3000,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        (module.swal ? module.swal.error : Swal.fire)({
                            title: 'Save Failed',
                            text: 'Could not update Call Log settings. Please check the logs and try again.'
                        });
                    }
                }).catch(err => {
                    this.saving = false;
                    (module.swal ? module.swal.error : Swal.fire)({
                        title: 'Error Saving Settings',
                        text: err.message || String(err)
                    });
                });
            }
        }));
    }

    if (window.Alpine) {
        registerComponent();
    } else {
        document.addEventListener('alpine:init', registerComponent);
    }
})();
