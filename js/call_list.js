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

    const isRowHidden = (row) => {
        if (!row) return false;
        let isCompleted = Boolean(row['_isCompleted'] || row['_status'] === 'complete');
        if (isCompleted) return false;
        let isExpired = Boolean(row['_isExpired'] || row['_status'] === 'expired');
        if (isExpired) return false;

        let cbDate = row['_callbackDate'] || row['call_callback_date'];
        let cbTime = row['_callbackTime'] || row['call_callback_time'];
        let hasCallback = Boolean(
            row['_callbackRequestor'] ||
            (row['call_requested_callback'] && (row['call_requested_callback'][1] === '1' || row['call_requested_callback'] === '1')) ||
            row['_callbackNotToday'] ||
            row['_callbackToday']
        );

        let isCbFuture = Boolean(row['_callbackNotToday']);
        let isCbToday = Boolean(row['_callbackToday']);

        if (hasCallback && cbDate) {
            let now = new Date();
            let yyyy = now.getFullYear();
            let mm = String(now.getMonth() + 1).padStart(2, '0');
            let dd = String(now.getDate()).padStart(2, '0');
            let hh = String(now.getHours()).padStart(2, '0');
            let min = String(now.getMinutes()).padStart(2, '0');
            let nowDateTime = `${yyyy}-${mm}-${dd} ${hh}:${min}`;
            let todayDate = `${yyyy}-${mm}-${dd}`;

            let cleanDate = cbDate;
            if (cleanDate.includes('/')) {
                let parts = cleanDate.split('/');
                if (parts.length === 3) {
                    cleanDate = `${parts[2]}-${parts[0].padStart(2, '0')}-${parts[1].padStart(2, '0')}`;
                }
            }

            if (cbTime) {
                let cleanTime = cbTime.length > 5 ? cbTime.substring(0, 5) : cbTime;
                isCbFuture = (`${cleanDate} ${cleanTime}` > nowDateTime);
                isCbToday = !isCbFuture;
            } else {
                isCbFuture = (cleanDate > todayDate);
                isCbToday = !isCbFuture;
            }
        }

        let isFutureAdhoc = Boolean(row['_futureAdhoc']);
        if (row['_adhocContactOn'] && row['_adhocContactOn'].length > 10) {
            let now = new Date();
            let yyyy = now.getFullYear();
            let mm = String(now.getMonth() + 1).padStart(2, '0');
            let dd = String(now.getDate()).padStart(2, '0');
            let hh = String(now.getHours()).padStart(2, '0');
            let min = String(now.getMinutes()).padStart(2, '0');
            let nowDateTime = `${yyyy}-${mm}-${dd} ${hh}:${min}`;
            let cleanContact = row['_adhocContactOn'].length > 16 ? row['_adhocContactOn'].substring(0, 16) : row['_adhocContactOn'];
            isFutureAdhoc = (cleanContact > nowDateTime);
        }

        return Boolean((row['_atMaxAttempts'] && !isCbToday) || isCbFuture || row['_noCallsToday'] || isFutureAdhoc);
    };
    module.isRowHidden = isRowHidden;

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
            activeCallerFilter: '',
            showTypes: {
                active: true,
                hidden: false,
                expired: false,
                completed: false
            },
            availableCallers: [],
            displayedData: {},
            dataLoaded: false,
            lastDataPullTime: null,
            lastDataPullText: '',
            isRefreshing: false,
            persistTimeout: null,
            unlockedTabs: {},

            isTabUnlocked(tab_id) {
                return Boolean(this.unlockedTabs && this.unlockedTabs[tab_id]);
            },

            init() {
                const tabs = (module.tabs && module.tabs.config) ? module.tabs.config : [];
                let initialUnlocked = {};
                tabs.forEach(t => {
                    initialUnlocked[t.tab_id] = false;
                });
                this.unlockedTabs = initialUnlocked;

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

                if (savedState.callerFilter !== undefined) {
                    this.activeCallerFilter = savedState.callerFilter;
                }

                if (savedState.showTypes && typeof savedState.showTypes === 'object') {
                    this.showTypes.active = savedState.showTypes.active !== undefined ? Boolean(savedState.showTypes.active) : true;
                    this.showTypes.hidden = Boolean(savedState.showTypes.hidden);
                    this.showTypes.expired = Boolean(savedState.showTypes.expired);
                    this.showTypes.completed = Boolean(savedState.showTypes.completed);
                } else if (savedState.hideCalls !== undefined) {
                    this.showTypes.hidden = !savedState.hideCalls;
                }

                this.setupDataTables();
                this.setupColumnContextMenu();
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

                this.$watch('showTypes', () => {
                    this.updateStatusBadgeColVisibility();
                    $('.callTable').each((_, el) => {
                        if ($.fn.DataTable.isDataTable(el)) {
                            $(el).DataTable().draw(false);
                        }
                    });
                    this.debouncePersist();
                }, { deep: true });
            },

            updateStatusBadgeColVisibility() {
                const shouldShow = Boolean(this.showTypes && (this.showTypes.hidden || this.showTypes.expired || this.showTypes.completed));
                $('.callTable').each((_, el) => {
                    if ($.fn.DataTable.isDataTable(el)) {
                        const dt = $(el).DataTable();
                        const col = dt.column('_status_badge:name');
                        if (col && col.length && col.visible() !== shouldShow) {
                            col.visible(shouldShow, false);
                        }
                    }
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
                    this.updateStatusBadgeColVisibility();
                });
            },

            toggleHiddenCalls() {
                this.showTypes.hidden = !this.showTypes.hidden;
            },

            persistState() {
                let state = getDashboardCookie() || {};
                state.tab = this.activeTab;
                state.callerFilter = this.activeCallerFilter;
                state.showTypes = { ...this.showTypes };
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

            createColConfig(tabIdOrIndex, ignoreUserSettings = false) {
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
                        name: '_badges',
                        title: '<span class="badge-col-header"></span>',
                        data: '_record_id',
                        className: 'leftListIcon',
                        width: '40px',
                        bSortable: false,
                        orderable: false,
                        searchable: false,
                        render: (val, type, row) => {
                            if (type !== "display") return val;

                            let hasCallStarted = Boolean(row['_isCallStarted']);
                            let hasCallback = Boolean(row['_callbackRequestor']);
                            let hasMultiTabs = Boolean(row['_onMultipleTabs']);

                            let otherTabsStr = (Array.isArray(row['_otherTabs']) && row['_otherTabs'].length)
                                ? row['_otherTabs'].join(', ')
                                : '';
                            let cbWho = row['_callbackRequestor'] === '1'
                                ? 'Participant'
                                : (row['_callbackRequestor'] === '2' ? 'Staff' : (row['_callbackRequestor'] || ''));

                            let iconHtml = '';

                            if (hasCallStarted) {
                                // Priority 1: Ongoing Call (Active Lock)
                                const user = module.userNameMap ? (module.userNameMap[row['_callStartedBy']] || row['_callStartedBy']) : (row['_callStartedBy'] || '');
                                let secondary = [];
                                if (hasCallback) secondary.push(cbWho ? `Callback requested (${cbWho})` : 'Callback requested');
                                if (hasMultiTabs) secondary.push(otherTabsStr ? `Also on: ${otherTabsStr}` : 'On multiple tabs');

                                let tooltip = user ? `On a call (${user})` : 'On a call';
                                if (secondary.length) tooltip += ` • ${secondary.join(' • ')}`;

                                iconHtml = module.renderers.renderCallStartedIcon(user, tooltip);
                            } else if (hasCallback) {
                                // Priority 2: Callback Requested (Actionable Task)
                                let secondary = [];
                                if (hasMultiTabs) secondary.push(otherTabsStr ? `Also on: ${otherTabsStr}` : 'On multiple tabs');

                                let tooltip = cbWho ? `Callback requested (${cbWho})` : 'Callback requested';
                                if (secondary.length) tooltip += ` • ${secondary.join(' • ')}`;

                                iconHtml = module.renderers.renderCallbackMsgIcon(tooltip);
                            } else if (hasMultiTabs) {
                                // Priority 3: Queued on Multiple Tabs
                                let tooltip = otherTabsStr ? `Record is on multiple tabs: ${otherTabsStr}` : 'Record is on multiple tabs';

                                iconHtml = module.renderers.renderMultiTabIcon(row['_otherTabs'], tooltip);
                            }

                            if (!iconHtml) {
                                return '<div class="badge-icon-cell d-flex align-items-center justify-content-center mx-auto"></div>';
                            }

                            return `<div class="badge-icon-cell d-flex align-items-center justify-content-center mx-auto">${iconHtml}</div>`;
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
                    let internalName = fieldIndex === 0 ? 'record_id' : colName;

                    let col = {
                        name: internalName,
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
                            name: 'call_attempt',
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
                            name: '_participantName',
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

                if (!config.some(c => c.name === 'call_attempt')) {
                    config.push({
                        name: 'call_attempt',
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

                if (!config.some(c => c.name === '_participantName')) {
                    config.push({
                        name: '_participantName',
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
                        name: '_visitName',
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
                        name: '_call_date_gen',
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
                        name: '_expireDate',
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
                        name: '_windowLower',
                        title: 'Start Calling',
                        data: '_windowLower',
                        defaultContent: 'Not Specified',
                        render: (val, type) => {
                            if (type !== 'display') return val || '';
                            return val ? formatDateTime(val, false, true) : '<span class="text-muted small">Not Specified</span>';
                        }
                    });
                    config.push({
                        name: '_windowUpper',
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
                        name: '_appt_dt',
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
                        name: '_missed_dt',
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
                        name: '_adhocReason',
                        title: 'Reason',
                        data: '_adhocReason',
                        defaultContent: ''
                    });
                    config.push({
                        name: '_adhocContactOn',
                        title: 'Call on',
                        data: '_adhocContactOn',
                        defaultContent: '',
                        render: (val, type, row) => {
                            if (type !== 'display') return val || '';
                            let formattedDate = val ? formatDateTime(val) : '';
                            let html = formattedDate || '';
                            if (row['_callbackRequestor']) {
                                let cbWho = row['_callbackRequestor'] === '1' ? 'Participant' : (row['_callbackRequestor'] === '2' ? 'Staff' : row['_callbackRequestor']);
                                html += ` <span class="badge bg-danger-subtle text-danger border ms-1" title="Requested by ${cbWho}"><i class="fas fa-bell me-1"></i>Callback (${cbWho})</span>`;
                            }
                            return html;
                        }
                    });
                }

                if (!isNewCallType) {
                    config.push({
                        name: '_call_date',
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

                const showBadgeColInitial = Boolean(this.showTypes && (this.showTypes.hidden || this.showTypes.expired || this.showTypes.completed));
                config.push({
                    name: '_status_badge',
                    title: 'Status',
                    data: '_status',
                    className: 'text-center statusBadgeCol',
                    defaultContent: '',
                    orderable: false,
                    searchable: true,
                    visible: showBadgeColInitial,
                    render: (val, type, row) => {
                        if (type !== 'display') return val || '';
                        let isCompleted = Boolean(row['_isCompleted'] || row['_status'] === 'complete');
                        if (isCompleted) {
                            return `<span class="badge bg-success-subtle text-success border px-2 py-1" title="Call completed"><i class="fas fa-check-circle me-1"></i>Completed</span>`;
                        }
                        let isExpired = Boolean(row['_isExpired'] || row['_status'] === 'expired');
                        if (isExpired) {
                            return `<span class="badge bg-danger-subtle text-danger border px-2 py-1" title="Call window has expired"><i class="fas fa-exclamation-triangle me-1"></i>Expired</span>`;
                        }
                        let isHidden = isRowHidden(row);
                        if (isHidden) {
                            return `<span class="badge bg-secondary-subtle text-secondary border px-2 py-1" title="Hidden call"><i class="fas fa-eye-slash me-1"></i>Hidden</span>`;
                        }
                        return '';
                    }
                });

                config.push({
                    name: '_callNotes',
                    title: 'Call Notes',
                    data: '_callNotes',
                    visible: false,
                    className: 'callNotesCol',
                    defaultContent: ''
                });

                // Apply saved user column customization (order & visibility) if enabled
                if (!ignoreUserSettings) {
                    let tabId = typeof tabIdOrIndex === 'string' ? tabIdOrIndex : (defaultTab.tab_id || '');
                    let userTabSettings = (module.userSettings && module.userSettings.tabs && module.userSettings.tabs[tabId]) ? module.userSettings.tabs[tabId] : null;

                    if (userTabSettings) {
                        let savedOrder = userTabSettings.order || [];
                        let savedHidden = userTabSettings.hidden || [];

                        if (savedHidden.length) {
                            config.forEach(col => {
                                if (col.name && col.name !== '_badges' && col.name !== '_status_badge' && col.name !== '_callNotes') {
                                    if (savedHidden.includes(col.name)) {
                                        col.visible = false;
                                    }
                                }
                            });
                        }

                        if (savedOrder.length) {
                            let badgeCol = config.find(c => c.name === '_badges');
                            let statusBadgeCol = config.find(c => c.name === '_status_badge');
                            let notesCol = config.find(c => c.name === '_callNotes');
                            let reorderableCols = config.filter(c => c.name !== '_badges' && c.name !== '_status_badge' && c.name !== '_callNotes');

                            let orderedCols = [];
                            savedOrder.forEach(colName => {
                                let found = reorderableCols.find(c => c.name === colName);
                                if (found) {
                                    orderedCols.push(found);
                                }
                            });
                            reorderableCols.forEach(col => {
                                if (!orderedCols.some(c => c.name === col.name)) {
                                    orderedCols.push(col);
                                }
                            });

                            config = [];
                            if (badgeCol) config.push(badgeCol);
                            config.push(...orderedCols);
                            if (statusBadgeCol) config.push(statusBadgeCol);
                            if (notesCol) config.push(notesCol);
                        }
                    }
                }

                return config;
            },

            buildChildRowHtml(rowData, tab_id) {
                let tabConfig = (module.tabs && module.tabs.config) ? module.tabs.config.find(t => t.tab_id === tab_id) : null;
                if (!tabConfig && module.tabs && module.tabs.config && module.tabs.config.length) {
                    tabConfig = module.tabs.config[0];
                }
                let expands = (tabConfig && tabConfig.expands && tabConfig.expands.length)
                    ? tabConfig.expands
                    : ((tabConfig && tabConfig.fields) ? (tabConfig.fields || []).filter(f => f.expanded) : []);

                if ((!expands || !expands.length) && module.tabs && module.tabs.config) {
                    const fallbackTab = module.tabs.config.find(t => t.expands && t.expands.length);
                    if (fallbackTab) expands = fallbackTab.expands;
                }

                let expandsHtml = '';
                if (expands && expands.length) {
                    expandsHtml = expands.map(f => {
                        let val = (rowData[f.field] !== undefined && rowData[f.field] !== null && rowData[f.field] !== '') ? rowData[f.field] : (f.default || '');
                        if (f.map && typeof f.map === 'object' && f.map[val] !== undefined) {
                            val = f.map[val];
                        }
                        if (f.isDate || (val && typeof val === 'string' && /^\d{4}[-/]\d{1,2}[-/]\d{1,2}/.test(val.trim()))) {
                            val = formatDateTime(val, f.hasTime);
                        }
                        let dName = (f.displayName || f.field).replace(/[:\s]+$/, '');
                        return `<div class="mb-1"><span class="fw-semibold text-secondary small">${dName}:</span> <span class="small text-dark">${val || '<span class="text-muted fst-italic">None</span>'}</span></div>`;
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
                let ongoingCallInfoHtml = '';

                let isCompleted = Boolean(rowData['_isCompleted'] || rowData['_status'] === 'complete');
                let isExpired = !isCompleted && Boolean(rowData['_isExpired'] || rowData['_status'] === 'expired');
                let isHidden = isRowHidden(rowData);

                if (rowData['_isCallStarted']) {
                    actionButtonsHtml += `<button type="button" class="btn btn-sm btn-danger endCallButton drawer-action-btn" data-record="${record}" data-callid="${callId}" title="End ongoing call"><i class="fas fa-phone-slash"></i> End Call</button>`;

                    let caller = rowData['_callStartedBy'] || '';
                    let callerDisplayName = module.userNameMap ? (module.userNameMap[caller] || caller) : (caller || 'Unknown');
                    let startedTime = rowData['_callStartedTime'] ? formatDateTime(rowData['_callStartedTime']) : '';
                    let duration = rowData['_callDuration'] || 30;

                    ongoingCallInfoHtml = `
                        <div class="drawer-ongoing-call-info ms-1" title="Ongoing call details">
                            <span class="d-inline-flex align-items-center gap-1">
                                <i class="fas fa-user"></i>
                                <span>Caller:</span>
                                <span class="ongoing-val">${callerDisplayName}</span>
                            </span>
                            ${startedTime ? `
                                <span class="ongoing-divider">|</span>
                                <span class="d-inline-flex align-items-center gap-1">
                                    <i class="far fa-clock"></i>
                                    <span>Started:</span>
                                    <span class="ongoing-val">${startedTime}</span>
                                </span>
                            ` : ''}
                            <span class="ongoing-divider">|</span>
                            <span class="d-inline-flex align-items-center gap-1">
                                <i class="fas fa-hourglass-half"></i>
                                <span>Expected:</span>
                                <span class="ongoing-val">${duration} min</span>
                            </span>
                        </div>
                    `;
                } else if (!isCompleted) {
                    actionButtonsHtml += `<button type="button" class="btn btn-sm btn-success startCallButton drawer-action-btn" data-record="${record}" data-callid="${callId}" title="Flag call as started"><i class="fas fa-phone-alt"></i> Start Call</button>`;
                }

                if (!isCompleted && rowData['_call_outcome'] !== '1') {
                    actionButtonsHtml += `<button type="button" class="btn btn-sm btn-outline-secondary noCallsButton drawer-action-btn ms-1" data-record="${record}" data-callid="${callId}" title="Mark no calls today"><i class="fas fa-calendar-times"></i> No Calls Today</button>`;
                }

                let statusBadgeHtml = '';
                if (isCompleted) {
                    statusBadgeHtml = `<span class="badge bg-success-subtle text-success border px-2 py-1 ms-1" title="Call completed"><i class="fas fa-check-circle me-1"></i>Completed Call</span>`;
                } else if (isExpired) {
                    statusBadgeHtml = `<span class="badge bg-danger-subtle text-danger border px-2 py-1 ms-1" title="Call window has expired"><i class="fas fa-exclamation-triangle me-1"></i>Expired Call</span>`;
                } else if (isHidden) {
                    statusBadgeHtml = `<span class="badge bg-secondary-subtle text-secondary border px-2 py-1 ms-1" title="Call hidden from default view"><i class="fas fa-eye-slash me-1"></i>Hidden Call</span>`;
                }

                let callGenHtml = '';
                if (rowData['_callGenerated']) {
                    callGenHtml = `<span class="drawer-call-generated" title="Date and time this call was queued into the Call Log"><i class="far fa-clock"></i> <span>Call Generated:</span> <span class="call-gen-val">${formatDateTime(rowData['_callGenerated'])}</span></span>`;
                }

                let drawerHeaderHtml = `
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            ${actionButtonsHtml}
                            ${ongoingCallInfoHtml}
                            ${statusBadgeHtml}
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

            setButtonLoading($btn) {
                let originalHtml = $btn.html();
                let originalWidth = $btn.outerWidth();
                if (originalWidth > 0) {
                    $btn.css('min-width', originalWidth + 'px');
                }
                $btn.prop('disabled', true)
                    .addClass('btn-loading')
                    .html('<span class="btn-bounce-ball-stage" aria-hidden="true"><span class="btn-bounce-ball"></span></span>');
                $btn.siblings('.drawer-action-btn').prop('disabled', true);

                return function restoreButton() {
                    $btn.html(originalHtml)
                        .css('min-width', '')
                        .prop('disabled', false)
                        .removeClass('btn-loading');
                    $btn.siblings('.drawer-action-btn').prop('disabled', false);
                };
            },

            updateRowCallState(record, callId, changes) {
                const self = this;
                record = String(record);
                callId = String(callId);

                const matchesCallId = (targetId) => {
                    if (!targetId) return false;
                    let t = String(targetId);
                    return t === callId || t.startsWith(callId + '|') || callId.startsWith(t + '|');
                };

                // 1. Update master displayedData cache
                if (self.displayedData) {
                    Object.values(self.displayedData).forEach(rows => {
                        if (Array.isArray(rows)) {
                            rows.forEach(r => {
                                if (r && String(r['_record_id']) === record && matchesCallId(r['_call_id'])) {
                                    Object.assign(r, changes);
                                }
                            });
                        }
                    });
                }

                // 2. Update caller list if a new caller became active
                if (changes._callStartedBy && changes._isCallStarted) {
                    let callerName = module.userNameMap ? (module.userNameMap[changes._callStartedBy] || changes._callStartedBy) : changes._callStartedBy;
                    if (callerName && !self.availableCallers.includes(callerName.trim())) {
                        self.availableCallers.push(callerName.trim());
                        self.availableCallers.sort();
                    }
                }

                // 3. Update all DataTables instances on the page where this row appears
                $('.callTable').each((index, el) => {
                    let $table = $(el);
                    if (!$.fn.DataTable || !$.fn.DataTable.isDataTable($table[0])) return;

                    let dt = $table.DataTable();
                    let tab_id = $table.closest('.tab-pane').prop('id');
                    let tableNeedsDraw = false;

                    dt.rows().every(function() {
                        let rowData = this.data();
                        if (rowData && String(rowData['_record_id']) === record && matchesCallId(rowData['_call_id'])) {
                            Object.assign(rowData, changes);
                            this.invalidate();
                            tableNeedsDraw = true;

                            if (this.child.isShown()) {
                                let updatedChildHtml = self.buildChildRowHtml(rowData, tab_id);
                                this.child(updatedChildHtml, 'dataTableChild').show();
                            }
                        }
                    });

                    if (tableNeedsDraw) {
                        dt.draw(false);
                    }
                });
            },

            setupDataTables() {
                const self = this;

                $.fn.dataTable.ext.search.push(
                    (_settings, _searchData, _index, rowData) => {
                        const showTypes = self.showTypes || { active: true, hidden: false, expired: false, completed: false };
                        const isCompleted = Boolean(rowData['_isCompleted'] || rowData['_status'] === 'complete');
                        const isExpired = !isCompleted && Boolean(rowData['_isExpired'] || rowData['_status'] === 'expired');
                        const isHidden = isRowHidden(rowData);
                        const isActive = !isCompleted && !isExpired && !isHidden;

                        if (isCompleted && !showTypes.completed) {
                            return false;
                        }
                        if (isExpired && !showTypes.expired) {
                            return false;
                        }
                        if (isHidden && !showTypes.hidden) {
                            return false;
                        }
                        if (isActive && !showTypes.active) {
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
                    let $btn = $(this);
                    let record = $btn.attr('data-record') || $btn.data('record') || '';
                    let callId = $btn.attr('data-callid') || $btn.data('callid') || '';
                    if (!record || !callId) {
                        let $tr = $btn.closest('tr');
                        if ($tr.hasClass('dataTableChild')) {
                            $tr = $tr.prev('tr.dataTablesRow');
                        }
                        let table = $tr.closest('.callTable').DataTable();
                        let rowData = table.row($tr).data() || {};
                        record = record || rowData['_record_id'] || '';
                        callId = callId || rowData['_call_id'] || '';
                    }
                    let restore = self.setButtonLoading($btn);
                    module.ajax("setCallStarted", {
                        record: record,
                        id: callId
                    }).then((res) => {
                        let resObj = (res && typeof res === 'object') ? res : {};
                        let isSaved = Boolean(resObj.saved || (resObj.data && resObj.data.saved));
                        if (isSaved) {
                            let caller = resObj.callStartedBy || (resObj.data && resObj.data.callStartedBy) || module.user || '';
                            let startTime = resObj.callStarted || (resObj.data && resObj.data.callStarted) || new Date().toISOString().slice(0, 19).replace('T', ' ');
                            self.updateRowCallState(record, callId, {
                                _callStarted: true,
                                _isCallStarted: true,
                                _callStartedBy: caller,
                                _callStartedTime: startTime
                            });
                        } else {
                            restore();
                            let errMsg = resObj.error || (resObj.data && resObj.data.error) || 'The server did not confirm saving the call status. Please try again.';
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Could Not Start Call',
                                    text: errMsg
                                });
                            } else {
                                alert(errMsg);
                            }
                        }
                    }).catch(err => {
                        console.error("Failed to start call:", err);
                        restore();
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Failed to communicate with server. Please try again.'
                            });
                        }
                    });
                });

                $(document).on('click', '.noCallsButton', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    let $btn = $(this);
                    let record = $btn.attr('data-record') || $btn.data('record') || '';
                    let callId = $btn.attr('data-callid') || $btn.data('callid') || '';
                    if (!record || !callId) {
                        let $tr = $btn.closest('tr');
                        if ($tr.hasClass('dataTableChild')) {
                            $tr = $tr.prev('tr.dataTablesRow');
                        }
                        let table = $tr.closest('.callTable').DataTable();
                        let rowData = table.row($tr).data() || {};
                        record = record || rowData['_record_id'] || '';
                        callId = callId || rowData['_call_id'] || '';
                    }
                    let restore = self.setButtonLoading($btn);
                    module.ajax("setNoCallsToday", {
                        record: record,
                        id: callId
                    }).then((res) => {
                        let resObj = (res && typeof res === 'object') ? res : {};
                        let isSaved = Boolean(resObj.saved || (resObj.data && resObj.data.saved));
                        if (isSaved) {
                            self.updateRowCallState(record, callId, {
                                _noCallsToday: true
                            });
                        } else {
                            restore();
                            let errMsg = resObj.error || (resObj.data && resObj.data.error) || 'The server did not confirm marking no calls today. Please try again.';
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Could Not Update Call',
                                    text: errMsg
                                });
                            } else {
                                alert(errMsg);
                            }
                        }
                    }).catch(err => {
                        console.error("Failed to set no calls today:", err);
                        restore();
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Failed to communicate with server. Please try again.'
                            });
                        }
                    });
                });

                $(document).on('click', '.endCallButton', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    let $btn = $(this);
                    let record = $btn.attr('data-record') || $btn.data('record') || '';
                    let callId = $btn.attr('data-callid') || $btn.data('callid') || '';
                    if (!record || !callId) {
                        let $tr = $btn.closest('tr');
                        if ($tr.hasClass('dataTableChild')) {
                            $tr = $tr.prev('tr.dataTablesRow');
                        }
                        let table = $tr.closest('.callTable').DataTable();
                        let rowData = table.row($tr).data() || {};
                        record = record || rowData['_record_id'] || '';
                        callId = callId || rowData['_call_id'] || '';
                    }
                    let restore = self.setButtonLoading($btn);
                    module.ajax("setCallEnded", {
                        record: record,
                        id: callId
                    }).then((res) => {
                        let resObj = (res && typeof res === 'object') ? res : {};
                        let isSaved = Boolean(resObj.saved || (resObj.data && resObj.data.saved));
                        if (isSaved) {
                            self.updateRowCallState(record, callId, {
                                _callStarted: false,
                                _isCallStarted: false,
                                _callStartedBy: '',
                                _callStartedTime: ''
                            });
                        } else {
                            restore();
                            let errMsg = resObj.error || (resObj.data && resObj.data.error) || 'The server did not confirm ending the call. Please try again.';
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Could Not End Call',
                                    text: errMsg
                                });
                            } else {
                                alert(errMsg);
                            }
                        }
                    }).catch(err => {
                        console.error("Failed to end call:", err);
                        restore();
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Failed to communicate with server. Please try again.'
                            });
                        }
                    });
                });

                $(document).on('click', '.rowLink', function(e) {
                    let record = $(this).attr('data-record') || $(this).data('record');
                    let callId = $(this).attr('data-callid') || $(this).data('callid');
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
                    self.initSingleDataTable(el);
                });
            },

            initSingleDataTable(el) {
                const self = this;
                let tab_id = $(el).closest('.tab-pane').prop('id');
                childRows[tab_id] = "";
                colConfig[tab_id] = self.createColConfig(tab_id);

                let savedState = getDashboardCookie() || {};
                let tabState = (savedState.tabs && savedState.tabs[tab_id]) ? savedState.tabs[tab_id] : null;
                let savedLen = (tabState && tabState.len) ? tabState.len : 100;
                let colCount = colConfig[tab_id].length;
                let savedOrder = [[1, 'asc']];
                if (tabState && Array.isArray(tabState.order) && tabState.order.length) {
                    if (tabState.order.every(o => Array.isArray(o) && o[0] < colCount)) {
                        savedOrder = tabState.order;
                    }
                }

                let isUnlocked = Boolean(self.unlockedTabs[tab_id]);

                let dt = $(el).DataTable({
                    autoWidth: false,
                    colReorder: {
                        enable: isUnlocked,
                        fixedColumnsLeft: 1
                    },
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

                if (isUnlocked) {
                    $(el).addClass('columns-unlocked');
                } else {
                    $(el).removeClass('columns-unlocked');
                }

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

                dt.on('column-reorder.dt', (_e, _settings, details) => {
                    if (self.dataLoaded && details && details.drop) {
                        self.saveUserColumns(tab_id);
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

                return dt;
            },

            reinitTabDataTable(tab_id) {
                let $pane = $(`#${tab_id}`);
                let $table = $pane.find('.callTable');
                if (!$table.length || !$.fn.DataTable.isDataTable($table[0])) return;
                let dt = $table.DataTable();
                let page = dt.page();
                let search = dt.search();

                let $wrapper = $table.closest('.dataTables_wrapper');
                $wrapper.find('.dataTables_info_wrapper').remove();

                dt.destroy();
                $table.empty();
                $table.removeClass('columns-unlocked');

                let newDt = this.initSingleDataTable($table[0]);
                let rows = this.displayedData[tab_id] || [];
                newDt.clear().rows.add(rows);
                if (search) newDt.search(search);
                if (typeof page === 'number' && page >= 0) newDt.page(page);
                newDt.draw(false);
                newDt.columns.adjust();
            },

            toggleLockColumns(tab_id) {
                let currentVal = Boolean(this.unlockedTabs && this.unlockedTabs[tab_id]);
                let nextVal = !currentVal;
                this.unlockedTabs = {
                    ...this.unlockedTabs,
                    [tab_id]: nextVal
                };

                let $pane = $(`#${tab_id}`);
                let $table = $pane.find('.callTable');
                if ($table.length && $.fn.DataTable.isDataTable($table[0])) {
                    let dt = $table.DataTable();
                    if (nextVal) {
                        dt.colReorder.enable();
                        $table.addClass('columns-unlocked');
                    } else {
                        dt.colReorder.disable();
                        $table.removeClass('columns-unlocked');
                    }
                }
            },

            saveUserColumns(tab_id) {
                let $pane = $(`#${tab_id}`);
                let $table = $pane.find('.callTable');
                if (!$table.length || !$.fn.DataTable.isDataTable($table[0])) return;
                let dt = $table.DataTable();
                let aoColumns = dt.settings()[0].aoColumns;

                let order = aoColumns
                    .map(c => c.sName)
                    .filter(name => name && name !== '_badges' && name !== '_status_badge' && name !== '_callNotes');

                let hidden = aoColumns
                    .filter(c => !c.bVisible && c.sName && c.sName !== '_badges' && c.sName !== '_status_badge' && c.sName !== '_callNotes')
                    .map(c => c.sName);

                if (!module.userSettings) module.userSettings = {};
                if (!module.userSettings.tabs) module.userSettings.tabs = {};
                module.userSettings.tabs[tab_id] = {
                    order: order,
                    hidden: hidden,
                    updated_at: new Date().toISOString()
                };

                module.ajax('saveUserColumns', {
                    tab_id: tab_id,
                    order: order,
                    hidden: hidden
                }).catch(err => {
                    console.error("Failed to save user column settings:", err);
                });
            },

            hideColumn(tab_id, colName) {
                if (!colName || colName === '_badges' || colName === '_status_badge' || colName === '_callNotes') return;
                let $pane = $(`#${tab_id}`);
                let $table = $pane.find('.callTable');
                if (!$table.length || !$.fn.DataTable.isDataTable($table[0])) return;
                let dt = $table.DataTable();
                let col = dt.column(colName + ':name');
                if (col && col.length) {
                    col.visible(false);
                    dt.columns.adjust().draw(false);
                    this.saveUserColumns(tab_id);
                }
            },

            unhideColumn(tab_id, colName) {
                let $pane = $(`#${tab_id}`);
                let $table = $pane.find('.callTable');
                if (!$table.length || !$.fn.DataTable.isDataTable($table[0])) return;
                let dt = $table.DataTable();
                let col = dt.column(colName + ':name');
                if (col && col.length) {
                    col.visible(true);
                    dt.columns.adjust().draw(false);
                    this.saveUserColumns(tab_id);
                }
            },

            unhideAllColumns(tab_id) {
                let $pane = $(`#${tab_id}`);
                let $table = $pane.find('.callTable');
                if (!$table.length || !$.fn.DataTable.isDataTable($table[0])) return;
                let dt = $table.DataTable();
                let aoColumns = dt.settings()[0].aoColumns;
                aoColumns.forEach(c => {
                    if (c.sName && c.sName !== '_status_badge' && c.sName !== '_callNotes') {
                        dt.column(c.sName + ':name').visible(true);
                    }
                });
                dt.columns.adjust().draw(false);
                this.saveUserColumns(tab_id);
            },

            resetColumns(tab_id) {
                if (module.userSettings && module.userSettings.tabs) {
                    delete module.userSettings.tabs[tab_id];
                }
                this.unlockedTabs = {
                    ...this.unlockedTabs,
                    [tab_id]: false
                };
                this.reinitTabDataTable(tab_id);
                module.ajax('resetUserColumns', { tab_id: tab_id }).catch(err => {
                    console.error("Failed to reset user columns on server:", err);
                });
            },

            resetAllColumns(tab_id) {
                this.resetColumns(tab_id);
            },

            autofitColumns(tab_id) {
                let $pane = $(`#${tab_id}`);
                let $table = $pane.find('.callTable');
                if ($table.length && $.fn.DataTable.isDataTable($table[0])) {
                    $table.DataTable().columns.adjust().draw(false);
                }
            },

            setupColumnContextMenu() {
                const self = this;
                let $menu = $('#callHeaderContextMenu');
                if (!$menu.length) {
                    $menu = $('<div id="callHeaderContextMenu" class="dropdown-menu shadow call-header-context-menu" style="display: none; position: fixed; z-index: 10050;"></div>');
                    $('body').append($menu);
                }

                const hideMenu = () => {
                    $menu.hide().empty();
                };

                $(document).on('click', (e) => {
                    if (!$(e.target).closest('#callHeaderContextMenu').length) {
                        hideMenu();
                    }
                });

                $(document).on('keydown', (e) => {
                    if (e.key === 'Escape') {
                        hideMenu();
                    }
                });

                $(window).on('scroll resize', hideMenu);

                $(document).on('contextmenu', '.callTable thead th', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    let $th = $(this);
                    let $table = $th.closest('.callTable');
                    let tab_id = $th.closest('.tab-pane').prop('id');
                    if (!tab_id || !$table.length || !$.fn.DataTable.isDataTable($table[0])) {
                        return;
                    }

                    let dt = $table.DataTable();
                    let colIdx = dt.column($th).index();
                    let aoColumns = dt.settings()[0].aoColumns;
                    let clickedCol = aoColumns[colIdx];
                    let colName = clickedCol ? clickedCol.sName : null;
                    let isCol0 = !colName || colName === '_badges' || colName === '_status_badge';

                    let colTitle = '';
                    if (!isCol0) {
                        colTitle = (clickedCol && clickedCol.sTitle) ? clickedCol.sTitle : $th.text().trim();
                        colTitle = colTitle.replace(/<[^>]*>?/gm, '').trim();
                    }

                    let isUnlocked = Boolean(self.unlockedTabs[tab_id]);

                    let visibleDataCols = aoColumns.filter(c => c.bVisible && c.sName && c.sName !== '_badges' && c.sName !== '_status_badge' && c.sName !== '_callNotes');
                    let hiddenCols = aoColumns.filter(c => !c.bVisible && c.sName && c.sName !== '_badges' && c.sName !== '_status_badge' && c.sName !== '_callNotes');

                    let menuHtml = '';

                    // 1. Lock / Unlock
                    if (isUnlocked) {
                        menuHtml += `<a class="dropdown-item py-1.5" href="#" data-action="toggle-lock"><i class="fas fa-lock me-2 text-primary"></i> Lock Columns</a>`;
                    } else {
                        menuHtml += `<a class="dropdown-item py-1.5" href="#" data-action="toggle-lock"><i class="fas fa-unlock me-2 text-primary"></i> Unlock Columns (Drag & Drop)</a>`;
                    }

                    menuHtml += `<hr class="dropdown-divider my-1">`;

                    // 2. Hide column
                    if (isCol0) {
                        menuHtml += `<span class="dropdown-item disabled text-muted py-1.5"><i class="fas fa-eye-slash me-2"></i> Cannot Hide System Column</span>`;
                    } else if (visibleDataCols.length <= 1) {
                        menuHtml += `<span class="dropdown-item disabled text-muted py-1.5" title="At least one column must remain visible"><i class="fas fa-eye-slash me-2"></i> Cannot Hide Only Visible Column</span>`;
                    } else {
                        let label = colTitle ? `Hide "${colTitle}"` : 'Hide Column';
                        menuHtml += `<a class="dropdown-item py-1.5" href="#" data-action="hide-col" data-colname="${colName}"><i class="fas fa-eye-slash me-2 text-secondary"></i> ${label}</a>`;
                    }

                    // 3. Unhide submenu
                    if (hiddenCols.length > 0) {
                        menuHtml += `
                            <div class="dropdown-submenu">
                                <a class="dropdown-item dropdown-toggle py-1.5" href="#" data-action="none"><i class="fas fa-eye me-2 text-success"></i> Unhide Column (${hiddenCols.length})</a>
                                <div class="dropdown-menu shadow submenu-popup py-1">
                        `;
                        hiddenCols.forEach(hc => {
                            let hTitle = hc.sTitle || hc.sName;
                            hTitle = hTitle.replace(/<[^>]*>?/gm, '').trim();
                            menuHtml += `<a class="dropdown-item py-1.5" href="#" data-action="unhide-col" data-colname="${hc.sName}"><i class="fas fa-plus me-2 text-muted"></i> ${hTitle}</a>`;
                        });
                        menuHtml += `
                                    <hr class="dropdown-divider my-1">
                                    <a class="dropdown-item py-1.5 text-primary fw-medium" href="#" data-action="unhide-all"><i class="fas fa-check-double me-2"></i> Unhide All (${hiddenCols.length})</a>
                                </div>
                            </div>
                        `;
                    } else {
                        menuHtml += `<span class="dropdown-item disabled text-muted py-1.5"><i class="fas fa-eye me-2"></i> Unhide Column (None Hidden)</span>`;
                    }

                    menuHtml += `<hr class="dropdown-divider my-1">`;

                    // 4. Auto-fit column widths
                    menuHtml += `<a class="dropdown-item py-1.5" href="#" data-action="autofit"><i class="fas fa-arrows-alt-h me-2 text-secondary"></i> Auto-fit Column Widths</a>`;

                    menuHtml += `<hr class="dropdown-divider my-1">`;

                    // 5. Reset Columns (unhides all columns and restores default order)
                    menuHtml += `<a class="dropdown-item py-1.5" href="#" data-action="reset-columns"><i class="fas fa-undo me-2 text-warning"></i> Reset Columns</a>`;

                    $menu.html(menuHtml);

                    // Action handlers
                    $menu.find('a[data-action]').off('click').on('click', function(evt) {
                        evt.preventDefault();
                        evt.stopPropagation();
                        let action = $(this).data('action');
                        let targetCol = $(this).data('colname');

                        if (action === 'toggle-lock') {
                            self.toggleLockColumns(tab_id);
                        } else if (action === 'hide-col') {
                            self.hideColumn(tab_id, targetCol);
                        } else if (action === 'unhide-col') {
                            self.unhideColumn(tab_id, targetCol);
                        } else if (action === 'unhide-all') {
                            self.unhideAllColumns(tab_id);
                        } else if (action === 'autofit') {
                            self.autofitColumns(tab_id);
                        } else if (action === 'reset-columns') {
                            self.resetColumns(tab_id);
                        }
                        hideMenu();
                    });

                    // Show and position
                    $menu.css({ display: 'block', visibility: 'hidden', left: 0, top: 0 });
                    let menuWidth = $menu.outerWidth();
                    let menuHeight = $menu.outerHeight();
                    let posX = e.clientX;
                    let posY = e.clientY;

                    if (posX + menuWidth > $(window).width()) {
                        posX = $(window).width() - menuWidth - 10;
                    }
                    if (posY + menuHeight > $(window).height()) {
                        posY = $(window).height() - menuHeight - 10;
                    }
                    if (posX < 0) posX = 5;
                    if (posY < 0) posY = 5;

                    $menu.css({
                        left: posX + 'px',
                        top: posY + 'px',
                        visibility: 'visible'
                    });

                    // Submenu hover & boundary handling
                    $menu.find('.dropdown-submenu').each(function() {
                        let $sub = $(this).find('.submenu-popup');
                        $(this).on('mouseenter', function() {
                            $sub.css({ display: 'block', visibility: 'hidden' });
                            let subWidth = $sub.outerWidth();
                            let subHeight = $sub.outerHeight();
                            let subOffset = $(this).offset();

                            if (posX + menuWidth + subWidth > $(window).width()) {
                                $sub.css({ left: 'auto', right: '100%' });
                            } else {
                                $sub.css({ left: '100%', right: 'auto' });
                            }

                            if (subOffset.top + subHeight > $(window).height() + $(window).scrollTop()) {
                                $sub.css({ top: 'auto', bottom: '0' });
                            } else {
                                $sub.css({ top: '-6px', bottom: 'auto' });
                            }

                            $sub.css({ visibility: 'visible' });
                        }).on('mouseleave', function() {
                            $sub.css({ display: 'none' });
                        });
                    });
                });
            },

            refreshTableData() {
                const self = this;
                this.isRefreshing = true;
                module.ajax("getData", {}).then((response) => {
                    this.isRefreshing = false;
                    this.lastDataPullTime = new Date();
                    this.lastDataPullText = formatDateTime(this.lastDataPullTime, true);

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
                    this.isRefreshing = false;
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