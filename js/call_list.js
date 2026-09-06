(() => {
    const module = ExternalModules.UWMadison.CallLog;
    const pageRefresh = 60 * 1000;
    const COOKIE_NAME = `call_log_dashboard_${pid}`;

    const formatDateTime = (val, forceTime = false, forceDateOnly = false) => {
        if (module.utils && module.utils.formatDateTime) {
            return module.utils.formatDateTime(val, forceTime, forceDateOnly);
        }
        return val !== undefined && val !== null ? String(val) : '';
    };

    let childRows = {};
    let colConfig = {};

    function getDashboardCookie() {
        let nameEQ = COOKIE_NAME + "=";
        let ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i].trim();
            if (c.indexOf(nameEQ) === 0) {
                try {
                    return JSON.parse(decodeURIComponent(c.substring(nameEQ.length)));
                } catch (e) {
                    return null;
                }
            }
        }
        return null;
    }

    function setDashboardCookie(state) {
        try {
            let json = JSON.stringify(state);
            let date = new Date();
            date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000)); // 30 days
            document.cookie = `${COOKIE_NAME}=${encodeURIComponent(json)}; expires=${date.toUTCString()}; path=/; SameSite=Lax`;
        } catch (e) {
            console.warn("Failed to set dashboard cookie:", e);
        }
    }

    function registerComponent() {
        
        Alpine.data('callListDashboard', () => ({
            activeTab: '',
            hideCalls: true,
            activeCallerFilter: '',
            availableCallers: [],
            displayedData: {},
            dataLoaded: false,
            persistTimeout: null,

            init() {
                const tabs = (module.tabs && module.tabs.config) ? module.tabs.config : [];
                const firstTabId = tabs.length ? tabs[0].tab_id : '';
                const savedState = getDashboardCookie() || {};
                
                let targetTab = savedState.tab;
                if (!targetTab) {
                    try {
                        let ls = JSON.parse(localStorage.getItem(`ExternalModules.UWMadison.CallLog.${pid}`) || '{}');
                        targetTab = ls.tab;
                    } catch (e) {}
                }

                if (targetTab && tabs.some(t => t.tab_id === targetTab)) {
                    this.activeTab = targetTab;
                } else {
                    this.activeTab = firstTabId;
                }

                if (savedState.hideCalls !== undefined) {
                    this.hideCalls = Boolean(savedState.hideCalls);
                }
                if (savedState.callerFilter !== undefined) {
                    this.activeCallerFilter = savedState.callerFilter;
                }

                this.setupDataTables();
                this.refreshTableData();

                setInterval(() => this.refreshTableData(), pageRefresh);

                this.$watch('activeCallerFilter', () => {
                    $('.callTable').each((_, el) => {
                        if ($.fn.DataTable.isDataTable(el)) {
                            $(el).DataTable().draw(false);
                        }
                    });
                    this.debouncePersist();
                });
            },

            selectTab(tabId) {
                this.activeTab = tabId;
                this.persistState();
                this.$nextTick(() => {
                    let $pane = $(`#${tabId}`);
                    if ($pane.length) {
                        let $table = $pane.find('.callTable');
                        if ($table.length && $.fn.DataTable.isDataTable($table[0])) {
                            let dt = $table.DataTable();
                            dt.columns.adjust().draw(false);
                        }
                    }
                    this.toggleCallBackCol();
                });
            },

            toggleHiddenCalls() {
                this.hideCalls = !this.hideCalls;
                this.toggleCallBackCol();
                $('.callTable').each((_, el) => {
                    if ($.fn.DataTable.isDataTable(el)) {
                        $(el).DataTable().draw(false);
                    }
                });
                this.persistState();
            },

            persistState() {
                let state = getDashboardCookie() || {};
                state.tab = this.activeTab;
                state.hideCalls = this.hideCalls;
                state.callerFilter = this.activeCallerFilter;
                if (!state.tabs) state.tabs = {};

                $('.callTable').each((_idx, el) => {
                    let tab_id = $(el).closest('.tab-pane').prop('id');
                    if (!tab_id) return;
                    if ($.fn.DataTable.isDataTable(el)) {
                        let dt = $(el).DataTable();
                        state.tabs[tab_id] = {
                            page: dt.page(),
                            len: dt.page.len(),
                            order: dt.order(),
                            search: dt.search() || ''
                        };
                    }
                });

                setDashboardCookie(state);
                try {
                    localStorage.setItem(`ExternalModules.UWMadison.CallLog.${pid}`, JSON.stringify({ tab: this.activeTab }));
                } catch (e) {}
            },

            debouncePersist() {
                if (this.persistTimeout) clearTimeout(this.persistTimeout);
                this.persistTimeout = setTimeout(() => {
                    this.persistState();
                }, 200);
            },

            toggleCallBackCol() {
                // Maintained for compatibility
            },

            createColConfig(tabIdOrIndex) {
                let defaultTab = { fields: [] };
                if (module.tabs && module.tabs.config) {
                    if (typeof tabIdOrIndex === 'string') {
                        defaultTab = module.tabs.config.find(t => t.tab_id === tabIdOrIndex) || module.tabs.config[0] || { fields: [] };
                    } else if (typeof tabIdOrIndex === 'number') {
                        defaultTab = module.tabs.config[tabIdOrIndex] || { fields: [] };
                    }
                }
                let allFields = defaultTab.fields || [];
                let fields = allFields.filter(f => !f.expanded);

                let config = [
                    {
                        title: '',
                        data: '_record_id',
                        className: 'leftListIcon',
                        bSortable: false,
                        render: (val, type, row) => {
                            if (type !== "display") return val;
                            let icons = [];
                            if (row['_isCallStarted']) {
                                const user = module.userNameMap ? (module.userNameMap[row['_callStartedBy']] || row['_callStartedBy']) : row['_callStartedBy'];
                                icons.push(module.renderers.renderCallStartedIcon(user));
                            }
                            if (row['_onMultipleTabs']) {
                                icons.push(module.renderers.renderMultiTabIcon(row['_otherTabs']));
                            }
                            if (row['_callbackRequestor']) {
                                icons.push(module.renderers.renderCallbackMsgIcon());
                            } else if (row['_callNotes'] || row['_hasNotes']) {
                                icons.push(module.renderers.renderNotesIcon());
                            }
                            return `<div class="d-inline-flex align-items-center gap-1">${icons.join('')}</div>`;
                        }
                    }
                ];

                let displayNameField = (module.tabs && module.tabs.displayNameField) ? module.tabs.displayNameField : '';

                fields.forEach((fieldConfig, fieldIndex) => {
                    let colName = fieldConfig.field;
                    if (fieldIndex > 0 && displayNameField && colName === displayNameField) {
                        return;
                    }

                    let fieldDisplayName = fieldIndex === 0 ? 'Record ID' : (fieldConfig.displayName || colName);

                    let col = {
                        title: fieldDisplayName,
                        data: colName,
                        defaultContent: fieldConfig.default || '',
                        render: (val, type, row) => {
                            if (type !== "display") return val !== undefined && val !== null ? val : (fieldConfig.default || '');

                            if (val === undefined || val === null || val === '') {
                                val = fieldConfig.default || '';
                            }

                            if (fieldConfig.map && typeof fieldConfig.map === 'object' && fieldConfig.map[val] !== undefined) {
                                val = fieldConfig.map[val];
                            }

                            if (fieldConfig.isFormStatus) {
                                let formName = colName.replace('_complete', '');
                                val = module.renderers.renderFormStatus(pid, row['_record_id'], formName, val || '0');
                            }

                            if (fieldConfig.isDate || (val && typeof val === 'string' && /^\d{4}[-/]\d{1,2}[-/]\d{1,2}/.test(val.trim()))) {
                                val = formatDateTime(val, fieldConfig.hasTime);
                            }

                            if (fieldConfig.link && fieldConfig.link !== 'none') {
                                let linkType = fieldConfig.link;
                                let link = '#';

                                if (linkType === 'home') {
                                    link = `../DataEntry/record_home.php?pid=${pid}&id=${encodeURIComponent(row['_record_id'])}`;
                                } else if (linkType === 'call') {
                                    let callId = row['_call_id'] || '';
                                    let inst = row['_instance'] || 1;
                                    link = `../DataEntry/index.php?pid=${pid}&id=${encodeURIComponent(row['_record_id'])}&page=${module.static.instrument}&instance=${inst}&call_id=${encodeURIComponent(callId)}&showReturn=1`;
                                } else if (linkType === 'instrument') {
                                    let targetForm = fieldConfig.linkedInstrument || module.static.instrument;
                                    let targetEvent = fieldConfig.linkedEvent || row['_event_id'];
                                    link = `../DataEntry/index.php?pid=${pid}&id=${encodeURIComponent(row['_record_id'])}&page=${targetForm}&event_id=${targetEvent}`;
                                }

                                return `<a class="rowLink fw-semibold text-decoration-none" data-record="${row['_record_id']}" data-callid="${row['_call_id'] || ''}" href="${link}">${val}</a>`;
                            }

                            return val;
                        }
                    };

                    if (fieldIndex === 0) col.className = 'firstDataCol';
                    config.push(col);

                    if (fieldIndex === 0) {
                        config.push({
                            title: 'Attempts',
                            data: 'call_attempt',
                            className: 'text-center',
                            defaultContent: '0',
                            render: (val, type) => {
                                if (type !== 'display') return val !== undefined && val !== null ? val : 0;
                                let num = parseInt(val, 10) || 0;
                                return `<span class="badge ${num > 0 ? 'bg-secondary' : 'bg-light text-muted border'} rounded-pill px-2">${num}</span>`;
                            }
                        });

                        config.push({
                            title: 'Name',
                            data: '_participantName',
                            defaultContent: '',
                            render: (val, type) => {
                                if (type !== 'display') return val || '';
                                return val ? `<span class="fw-medium text-dark">${val}</span>` : '<span class="text-muted small fst-italic">None</span>';
                            }
                        });
                    }
                });

                if (!config.some(c => c.title === 'Attempts')) {
                    config.push({
                        title: 'Attempts',
                        data: 'call_attempt',
                        className: 'text-center',
                        defaultContent: '0',
                        render: (val, type) => {
                            if (type !== 'display') return val !== undefined && val !== null ? val : 0;
                            let num = parseInt(val, 10) || 0;
                            return `<span class="badge ${num > 0 ? 'bg-secondary' : 'bg-light text-muted border'} rounded-pill px-2">${num}</span>`;
                        }
                    });
                }

                if (!config.some(c => c.title === 'Name')) {
                    config.push({
                        title: 'Name',
                        data: '_participantName',
                        defaultContent: '',
                        render: (val, type) => {
                            if (type !== 'display') return val || '';
                            return val ? `<span class="fw-medium text-dark">${val}</span>` : '<span class="text-muted small fst-italic">None</span>';
                        }
                    });
                }

                if (defaultTab.showVisit) {
                    config.push({
                        title: 'Visit',
                        data: '_visitName',
                        defaultContent: '',
                        render: (val, type) => {
                            if (type !== 'display') return val || '';
                            return val ? `<span class="fw-medium text-dark">${val}</span>` : '<span class="text-muted small">Not Specified</span>';
                        }
                    });
                }

                let isNewCallType = defaultTab.showNewExpiration || (defaultTab.includedTemplates && defaultTab.includedTemplates.includes('new'));

                if (isNewCallType) {
                    config.push({
                        title: 'Generated',
                        data: '_call_date',
                        className: 'callDateCol',
                        defaultContent: '',
                        render: (val, type, row) => {
                            if (type !== "display") return val || row['_callGenerated'] || '';
                            let dateVal = val || row['_callGenerated'];
                            let formattedDate = formatDateTime(dateVal);
                            let html = formattedDate || '';
                            if (row['_callbackRequestor']) {
                                let cbWho = row['_callbackRequestor'] === '1' ? 'Participant' : (row['_callbackRequestor'] === '2' ? 'Staff' : row['_callbackRequestor']);
                                html += ` <span class="badge bg-danger-subtle text-danger border ms-1" title="Requested by ${cbWho}"><i class="fas fa-bell me-1"></i>Callback (${cbWho})</span>`;
                            }
                            return html;
                        }
                    });
                }

                if (defaultTab.showNewExpiration) {
                    config.push({
                        title: 'Expiration Date',
                        data: '_expireDate',
                        defaultContent: 'No Expiration',
                        render: (val, type, row) => {
                            if (type !== 'display') return val || '';
                            if (!val) return '<span class="text-muted small">No Expiration</span>';
                            let formattedDate = formatDateTime(val, false, true);
                            let days = row['_daysRemaining'];
                            if (days !== null && days !== undefined) {
                                if (days < 0) return `${formattedDate} <span class="badge bg-danger-subtle text-danger border ms-1">Expired</span>`;
                                if (days === 0) return `${formattedDate} <span class="badge bg-warning-subtle text-warning-emphasis border ms-1">Expires today</span>`;
                                if (days === 1) return `${formattedDate} <span class="badge bg-info-subtle text-info-emphasis border ms-1">1 day remaining</span>`;
                                return `${formattedDate} <span class="badge bg-secondary-subtle text-secondary border ms-1">${days} days remaining</span>`;
                            }
                            return formattedDate;
                        }
                    });
                }

                if (defaultTab.showFollowupWindows) {
                    config.push({
                        title: 'Start Calling',
                        data: '_windowLower',
                        defaultContent: 'Not Specified',
                        render: (val, type) => {
                            if (type !== 'display') return val || '';
                            return val ? formatDateTime(val, false, true) : '<span class="text-muted small">Not Specified</span>';
                        }
                    });
                    config.push({
                        title: 'Complete by',
                        data: '_windowUpper',
                        defaultContent: 'Not Specified',
                        render: (val, type) => {
                            if (type !== 'display') return val || '';
                            return val ? formatDateTime(val, false, true) : '<span class="text-muted small">Not Specified</span>';
                        }
                    });
                }

                if (defaultTab.showReminderAppt) {
                    config.push({
                        title: 'Appointment Date/Time',
                        data: '_appt_dt',
                        defaultContent: 'Not Specified',
                        render: (val, type) => {
                            if (type !== 'display') return val || '';
                            return val ? formatDateTime(val) : '<span class="text-muted small">Not Specified</span>';
                        }
                    });
                }

                if (defaultTab.showMissedDateTime) {
                    config.push({
                        title: 'Missed Date',
                        data: '_appt_dt',
                        defaultContent: 'Not Specified',
                        render: (val, type) => {
                            if (type !== 'display') return val || '';
                            return val ? formatDateTime(val) : '<span class="text-muted small">Not Specified</span>';
                        }
                    });
                }

                if (defaultTab.showAdhocDates) {
                    config.push({
                        title: 'Reason',
                        data: '_adhocReason',
                        defaultContent: ''
                    });
                    config.push({
                        title: 'Call on',
                        data: '_adhocContactOn',
                        defaultContent: '',
                        render: (val, type) => {
                            if (type !== 'display') return val || '';
                            return val ? formatDateTime(val) : '';
                        }
                    });
                }

                if (!isNewCallType) {
                    config.push({
                        title: 'Call Date/Time',
                        data: '_call_date',
                        className: 'callDateCol',
                        defaultContent: '',
                        render: (val, type, row) => {
                            if (type !== "display") return val || '';
                            let formattedDate = formatDateTime(val);
                            let html = formattedDate || '';
                            if (row['_callbackRequestor']) {
                                let cbWho = row['_callbackRequestor'] === '1' ? 'Participant' : (row['_callbackRequestor'] === '2' ? 'Staff' : row['_callbackRequestor']);
                                html += ` <span class="badge bg-danger-subtle text-danger border ms-1" title="Requested by ${cbWho}"><i class="fas fa-bell me-1"></i>Callback (${cbWho})</span>`;
                            }
                            return html;
                        }
                    });
                }

                config.push({
                    title: 'Call Notes',
                    data: '_callNotes',
                    visible: false,
                    className: 'callNotesCol',
                    defaultContent: ''
                });

                return config;
            },

            buildChildRowHtml(rowData, tab_id) {
                let tabConfig = (module.tabs && module.tabs.config) ? module.tabs.config.find(t => t.tab_id === tab_id) : null;
                let expands = tabConfig ? (tabConfig.expands || (tabConfig.fields || []).filter(f => f.expanded)) : [];

                let expandsHtml = '';
                if (expands && expands.length) {
                    expandsHtml = expands.map(f => {
                        let val = rowData[f.field] || f.default || '';
                        if (f.map && typeof f.map === 'object' && f.map[val] !== undefined) {
                            val = f.map[val];
                        }
                        if (f.isDate || (val && typeof val === 'string' && /^\d{4}[-/]\d{1,2}[-/]\d{1,2}/.test(val.trim()))) {
                            val = formatDateTime(val, f.hasTime);
                        }
                        return `<div class="mb-1"><span class="fw-semibold text-secondary small">${f.displayName}:</span> <span class="small text-dark">${val || '<span class="text-muted fst-italic">None</span>'}</span></div>`;
                    }).join('');
                }

                let notesRaw = rowData['_callNotes'] || '';
                let notesList = notesRaw.split('|||').map(x => x.split('||')).filter(x => x.length >= 4);

                let notesHtml = '';
                if (notesList.length) {
                    notesHtml = notesList.map(n => `
                        <div class="py-1.5 border-bottom d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <span class="fw-semibold text-dark small">${formatDateTime(n[0])}</span> <span class="text-secondary small">(${n[1]})</span>
                                <div class="small text-primary">${n[2] !== '&nbsp;' ? n[2] : ''}</div>
                            </div>
                            <div class="text-end small text-muted">${n[3] !== 'none' ? n[3] : 'No notes recorded'}</div>
                        </div>
                    `).join('');
                } else {
                    notesHtml = '<div class="text-muted small fst-italic">No call attempts or notes logged yet.</div>';
                }

                let callId = rowData['_call_id'] || '';
                let record = rowData['_record_id'] || '';

                let actionButtonsHtml = '';
                if (rowData['_isCallStarted']) {
                    let callerText = rowData['_callStartedBy'] ? ` (${rowData['_callStartedBy']})` : '';
                    actionButtonsHtml += `<button type="button" class="btn btn-sm btn-danger endCallButton drawer-action-btn" data-record="${record}" data-callid="${callId}" title="End ongoing call"><i class="fas fa-phone-slash"></i> End Call${callerText}</button>`;
                } else {
                    actionButtonsHtml += `<button type="button" class="btn btn-sm btn-success startCallButton drawer-action-btn" data-record="${record}" data-callid="${callId}" title="Flag call as started"><i class="fas fa-phone-alt"></i> Start Call</button>`;
                }

                if (rowData['_call_outcome'] !== '1') {
                    actionButtonsHtml += `<button type="button" class="btn btn-sm btn-outline-secondary noCallsButton drawer-action-btn ms-1" data-record="${record}" data-callid="${callId}" title="Mark no calls today"><i class="fas fa-calendar-times"></i> No Calls Today</button>`;
                }

                let callGenHtml = '';
                if (rowData['_callGenerated']) {
                    callGenHtml = `<span class="drawer-call-generated" title="Date and time this call was queued into the Call Log"><i class="far fa-clock"></i> <span>Call Generated:</span> <span class="call-gen-val">${formatDateTime(rowData['_callGenerated'])}</span></span>`;
                }

                let drawerHeaderHtml = `
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            ${actionButtonsHtml}
                        </div>
                        ${callGenHtml}
                    </div>
                `;

                if (expandsHtml) {
                    return `
                        <div class="call-drawer-content px-4 py-3">
                            ${drawerHeaderHtml}
                            <div class="row g-4">
                                <div class="col-md-4 border-end">
                                    <h6 class="fw-bold mb-2 text-dark"><i class="fas fa-id-card me-1 text-primary"></i> Participant Details</h6>
                                    <div>${expandsHtml}</div>
                                </div>
                                <div class="col-md-8">
                                    <h6 class="fw-bold mb-2 text-dark"><i class="fas fa-history me-1 text-primary"></i> Call History & Notes</h6>
                                    <div>${notesHtml}</div>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    return `
                        <div class="call-drawer-content px-4 py-3">
                            ${drawerHeaderHtml}
                            <h6 class="fw-bold mb-2 text-dark"><i class="fas fa-history me-1 text-primary"></i> Participant Call History & Notes</h6>
                            <div>${notesHtml}</div>
                        </div>
                    `;
                }
            },

            setupDataTables() {
                const self = this;

                $.fn.dataTable.ext.search.push(
                    (_settings, _searchData, _index, rowData) => {
                        if (self.hideCalls && (
                            (rowData['_atMaxAttempts'] && !rowData['_callbackToday']) || rowData['_callbackNotToday'] || rowData['_noCallsToday'] || rowData['_futureAdhoc']
                        )) {
                            return false;
                        }
                        if (self.activeCallerFilter) {
                            let caller = rowData['call_open_user_full_name'] || rowData['_callStartedBy'] || rowData['call_open_user'] || '';
                            if (caller.trim() !== self.activeCallerFilter) {
                                return false;
                            }
                        }
                        return true;
                    }
                );

                $(document).on('input propertychange paste search', '.customSearch', (e) => {
                    let $pane = $(e.target).closest('.tab-pane');
                    let $table = $pane.find('.callTable');
                    if ($table.length && $.fn.DataTable.isDataTable($table[0])) {
                        let dt = $table.DataTable();
                        let query = $(e.target).val() || '';

                        if (query.split(' ')[0] === 'regex') {
                            dt.search(query.replace('regex ', ''), true, false).draw(false);
                        } else if (query[0] === '!') {
                            dt.search('^(?!.*' + query.slice(1) + ')', true, false).draw(false);
                        } else {
                            dt.search(query, false, true).draw(false);
                        }
                        self.debouncePersist();
                    }
                });

                $(document).on('click', '.startCallButton', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    let record = $(this).data('record');
                    let callId = $(this).data('callid');
                    module.ajax("setCallStarted", {
                        record: record,
                        id: callId
                    }).then(() => {
                        self.refreshTableData();
                    }).catch(err => {
                        console.error("Failed to start call:", err);
                    });
                });

                $(document).on('click', '.noCallsButton', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    let record = $(this).data('record');
                    let callId = $(this).data('callid');
                    module.ajax("setNoCallsToday", {
                        record: record,
                        id: callId
                    }).then(() => {
                        self.refreshTableData();
                    }).catch(err => {
                        console.error("Failed to set no calls today:", err);
                    });
                });

                $(document).on('click', '.endCallButton', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    let record = $(this).data('record');
                    let callId = $(this).data('callid');
                    module.ajax("setCallEnded", {
                        record: record,
                        id: callId
                    }).then(() => {
                        self.refreshTableData();
                    }).catch(err => {
                        console.error("Failed to end call:", err);
                    });
                });

                $(document).on('click', '.rowLink', function(e) {
                    let record = $(this).data('record');
                    let callId = $(this).data('callid');
                    if (record && callId) {
                        module.ajax("setCallStarted", {
                            record: record,
                            id: callId
                        }).catch(err => {
                            console.error("Failed to mark call started on rowLink click:", err);
                        });
                    }
                });

                $(document).on('click', '.callTable tbody tr.dataTablesRow td', function(e) {
                    if ($(e.target).closest('a, button, input, select').length) return;
                    let $tr = $(this).closest('tr');
                    let table = $tr.closest('.callTable').DataTable();
                    let row = table.row($tr);
                    if (!row || !row.data()) return;

                    if (row.child.isShown()) {
                        row.child.hide();
                        $tr.removeClass('shown');
                    } else {
                        let rowData = row.data();
                        let tab_id = $tr.closest('.tab-pane').prop('id');
                        let childHtml = self.buildChildRowHtml(rowData, tab_id);
                        row.child(childHtml, 'dataTableChild').show();
                        $tr.addClass('shown');
                    }
                });


                let savedState = getDashboardCookie() || {};

                $('.callTable').each((index, el) => {
                    let tab_id = $(el).closest('.tab-pane').prop('id');
                    childRows[tab_id] = "";
                    colConfig[tab_id] = self.createColConfig(tab_id);

                    let tabState = (savedState.tabs && savedState.tabs[tab_id]) ? savedState.tabs[tab_id] : null;
                    let savedLen = (tabState && tabState.len) ? tabState.len : 100;
                    let colCount = colConfig[tab_id].length;
                    let savedOrder = [[1, 'asc']];
                    if (tabState && Array.isArray(tabState.order) && tabState.order.length) {
                        if (tabState.order.every(o => Array.isArray(o) && o[0] < colCount)) {
                            savedOrder = tabState.order;
                        }
                    }

                    let dt = $(el).DataTable({
                        pageLength: savedLen,
                        iDisplayLength: savedLen,
                        order: savedOrder,
                        language: {
                            emptyTable: `
                                <div class="py-4 text-center my-2">
                                    <div class="placeholder-icon-circle bg-light text-success mx-auto">
                                        <i class="fas fa-clipboard-check fa-2x"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">No Calls to Display</h6>
                                    <p class="text-muted small mb-0">There are currently no calls queued for this tab.</p>
                                </div>
                            `,
                            zeroRecords: `
                                <div class="py-4 text-center my-2">
                                    <div class="placeholder-icon-circle bg-light text-muted mx-auto">
                                        <i class="fas fa-search fa-2x opacity-50"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">No Calls Match Current Filters</h6>
                                </div>
                            `
                        },
                        columns: colConfig[tab_id],
                        createdRow: (row) => $(row).addClass('dataTablesRow'),
                        sDom: 't<"dataTables_footer d-flex flex-wrap align-items-center justify-content-between px-3 py-2"ip>'
                    });

                    if (tabState && tabState.search) {
                        let $pane = $(el).closest('.tab-pane');
                        $pane.find('.customSearch').val(tabState.search);
                        dt.search(tabState.search);
                    }

                    dt.on('order.dt page.dt', () => {
                        if (self.dataLoaded) {
                            self.debouncePersist();
                        }
                    });

                    let $wrapper = $(el).closest('.dataTables_wrapper');
                    let $info = $wrapper.find('.dataTables_info');

                    let $infoContainer = $('<div class="dataTables_info_wrapper d-flex align-items-center flex-wrap gap-2"></div>');
                    $info.before($infoContainer);
                    $infoContainer.append($info);

                    let $lenControl = $(`
                        <div class="d-inline-flex align-items-center gap-1 ms-2 ps-2 border-start call-len-box">
                            <label class="small text-muted fw-semibold mb-0" for="call_len_${tab_id}">Show:</label>
                            <input type="number" id="call_len_${tab_id}" class="form-control form-control-sm custom-page-len-input" value="${savedLen === -1 ? 'All' : savedLen}" min="1" step="10" style="width: 75px; height: 28px; text-align: center; font-size: 0.85rem;" title="Enter number of calls to display">
                            <span class="small text-muted">calls</span>
                        </div>
                    `);
                    $infoContainer.append($lenControl);

                    const applyLen = function(inputEl) {
                        let rawVal = $(inputEl).val().trim();
                        if (rawVal.toLowerCase() === 'all' || rawVal === '-1') {
                            dt.page.len(-1).draw(false);
                        } else {
                            let num = parseInt(rawVal, 10);
                            if (!isNaN(num) && num > 0) {
                                dt.page.len(num).draw(false);
                            } else {
                                $(inputEl).val(100);
                                dt.page.len(100).draw(false);
                            }
                        }
                        self.debouncePersist();
                    };

                    $lenControl.find('.custom-page-len-input').on('change', function() {
                        applyLen(this);
                    }).on('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            applyLen(this);
                            $(this).trigger('blur');
                        }
                    });
                });
            },

            refreshTableData() {
                const self = this;
                module.ajax("getData", {}).then((response) => {
                    if (!response) return;

                    let rawData = response.data !== undefined ? response.data : response;
                    if (rawData && rawData.data && !Array.isArray(rawData.data)) {
                        rawData = rawData.data;
                    }
                    this.displayedData = rawData || {};

                    let callers = new Set();
                    Object.values(this.displayedData).forEach(tabRows => {
                        if (Array.isArray(tabRows)) {
                            tabRows.forEach(row => {
                                let caller = row['call_open_user_full_name'] || row['_callStartedBy'] || row['call_open_user'] || '';
                                if (caller && caller.trim()) callers.add(caller.trim());
                            });
                        }
                    });

                    this.availableCallers = Array.from(callers).sort();

                    let isInitialLoad = !this.dataLoaded;

                    $('.callTable').each((index, el) => {
                        let tab_id = $(el).closest('.tab-pane').prop('id');
                        let dt = $(el).DataTable();
                        let rows = this.displayedData[tab_id] || [];

                        let currentPage = dt.page();
                        let openRowKeys = new Set();
                        if (!isInitialLoad) {
                            dt.rows().every(function() {
                                if (this.child.isShown()) {
                                    let d = this.data();
                                    if (d) {
                                        let k = (d['_record_id'] || '') + '___' + (d['_call_id'] || '');
                                        openRowKeys.add(k);
                                    }
                                }
                            });
                        }

                        dt.clear();
                        dt.rows.add(rows);

                        if (isInitialLoad) {
                            let savedState = getDashboardCookie() || {};
                            let tabState = (savedState.tabs && savedState.tabs[tab_id]) ? savedState.tabs[tab_id] : null;
                            if (tabState && typeof tabState.page === 'number' && tabState.page > 0) {
                                let pageLen = dt.page.len();
                                if (pageLen > 0) {
                                    let maxPages = Math.ceil(rows.length / pageLen);
                                    if (tabState.page < maxPages) {
                                        dt.page(tabState.page);
                                    }
                                }
                            }
                        } else {
                            let pageLen = dt.page.len();
                            if (pageLen > 0) {
                                let maxPages = Math.ceil(rows.length / pageLen);
                                if (currentPage >= maxPages && maxPages > 0) {
                                    currentPage = maxPages - 1;
                                }
                                dt.page(currentPage);
                            }
                        }

                        dt.draw(false);

                        if (openRowKeys.size > 0) {
                            dt.rows().every(function() {
                                let d = this.data();
                                if (d) {
                                    let k = (d['_record_id'] || '') + '___' + (d['_call_id'] || '');
                                    if (openRowKeys.has(k)) {
                                        let childHtml = self.buildChildRowHtml(d, tab_id);
                                        this.child(childHtml, 'dataTableChild').show();
                                        $(this.node()).addClass('shown');
                                    }
                                }
                            });
                        }
                    });

                    this.dataLoaded = true;

                    this.$nextTick(() => {
                        $('.callTable').each((index, el) => {
                            if ($.fn.DataTable.isDataTable(el)) {
                                $(el).DataTable().columns.adjust();
                            }
                        });
                    });

                    this.toggleCallBackCol();
                }).catch((err) => {
                    console.error("Error fetching call list data:", err);
                    this.dataLoaded = true;
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