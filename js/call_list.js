(() => {
    const module = ExternalModules.UWMadison.CallLog;
    const pageRefresh = 60 * 1000;

    let childRows = {};
    let colConfig = {};

    function registerComponent() {
        
        Alpine.data('callListDashboard', () => ({
            activeTab: '',
            hideCalls: true,
            activeCallerFilter: '',
            availableCallers: [],
            displayedData: {},

            init() {
                const tabs = (module.tabs && module.tabs.config) ? module.tabs.config : [];
                const firstTabId = tabs.length ? tabs[0].tab_id : '';
                
                const key = `ExternalModules.UWMadison.CallLog.${pid}`;
                let saved = localStorage.getItem(key);
                if (saved) {
                    try {
                        let parsed = JSON.parse(saved);
                        if (parsed.tab && tabs.some(t => t.tab_id === parsed.tab)) {
                            this.activeTab = parsed.tab;
                        } else {
                            this.activeTab = firstTabId;
                        }
                    } catch (e) {
                        this.activeTab = firstTabId;
                    }
                } else {
                    this.activeTab = firstTabId;
                }

                this.setupDataTables();
                this.refreshTableData();

                setInterval(() => this.refreshTableData(), pageRefresh);

                this.$watch('activeCallerFilter', () => {
                    $('.callTable:visible').DataTable().draw();
                });
            },

            selectTab(tabId) {
                this.activeTab = tabId;
                const key = `ExternalModules.UWMadison.CallLog.${pid}`;
                localStorage.setItem(key, JSON.stringify({ tab: tabId }));
                this.$nextTick(() => {
                    $('.callTable:visible').DataTable().draw();
                    this.toggleCallBackCol();
                });
            },

            toggleHiddenCalls() {
                this.hideCalls = !this.hideCalls;
                this.toggleCallBackCol();
                $('.callTable:visible').DataTable().draw();
            },

            toggleCallBackCol() {
                let currentData = this.displayedData[this.activeTab] || [];
                let tabConfig = (module.tabs && module.tabs.config) ? module.tabs.config.find(tab => tab.tab_id === this.activeTab) : null;
                let showAdhocDates = tabConfig ? tabConfig.showAdhocDates : false;
                let hasCallBacks = currentData.some(row => row._callbackRequestor);

                if (hasCallBacks || showAdhocDates) {
                    $(".callbackCol").show();
                } else {
                    $(".callbackCol").hide();
                }
            },

            createColConfig(index) {
                let defaultTab = (module.tabs && module.tabs.config) ? module.tabs.config[index] : { fields: [] };
                let fields = defaultTab.fields || [];

                let config = [
                    {
                        title: '',
                        data: '_record_id',
                        className: 'leftListIcon',
                        bSortable: false,
                        render: (val, type, row) => {
                            if (type !== "display") return val;
                            if (row['_callbackRequestor']) {
                                return module.renderers.renderCallbackMsgIcon();
                            }
                            if (row['_isCallStarted']) {
                                const user = module.userNameMap ? (module.userNameMap[row['_callStartedBy']] || row['_callStartedBy']) : row['_callStartedBy'];
                                return module.renderers.renderCallStartedIcon(user);
                            }
                            return (row['_callNotes'] || row['_hasNotes']) ? module.renderers.renderNotesIcon() : '';
                        }
                    }
                ];

                fields.forEach((fieldConfig, fieldIndex) => {
                    let colName = fieldConfig.field;
                    let fieldDisplayName = fieldConfig.displayName || colName;

                    let col = {
                        title: fieldDisplayName,
                        data: colName,
                        render: (val, type, row) => {
                            if (type !== "display") return val;

                            if (val === undefined || val === null || val === '') {
                                val = fieldConfig.default || '';
                            }

                            if (fieldConfig.isFormStatus) {
                                let formName = colName.replace('_complete', '');
                                val = module.renderers.renderFormStatus(pid, row['_record_id'], formName, val || '0');
                            }

                            if (fieldConfig.link) {
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

                                return `<a class="rowLink" data-callid="${row['_call_id'] || ''}" href="${link}">${val}</a>`;
                            }

                            return val;
                        }
                    };

                    if (fieldIndex === 0) col.className = 'firstDataCol';
                    config.push(col);
                });

                config.push({
                    title: 'Call Date',
                    data: '_call_date',
                    className: 'callbackCol',
                    render: (val, type, row) => {
                        if (type !== "display") return val;
                        let html = val || '';
                        if (row['_callbackRequestor']) {
                            html += ` <span class="callbackRequestor" title="Requested by ${row['_callbackRequestor']}">(${row['_callbackRequestor']})</span>`;
                        }
                        return html;
                    }
                });

                config.push({
                    title: '',
                    data: '_record_id',
                    className: 'text-right',
                    bSortable: false,
                    render: (val, type, row) => {
                        if (type !== "display") return val;
                        let callId = row['_call_id'] || '';
                        let btnHtml = '';
                        if (row['_call_outcome'] !== '1') {
                            btnHtml += `<a href="#" class="noCallsButton me-1" data-record="${val}" data-callid="${callId}">No Call Today</a>`;
                        }
                        return btnHtml;
                    }
                });

                return config;
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

                $(document).on('input propertychange paste', '.customSearch', (e) => {
                    let $table = $('.callTable:visible').DataTable();
                    let query = $(e.target).val() || '';

                    if (query.split(' ')[0] === 'regex') {
                        $table.search(query.replace('regex ', ''), true, false).draw();
                    } else if (query[0] === '!') {
                        $table.search('^(?!.*' + query.slice(1) + ')', true, false).draw();
                    } else {
                        $table.search(query, false, true).draw();
                    }
                });

                $('.callTable').each((index, el) => {
                    let tab_id = $(el).closest('.tab-pane').prop('id');
                    childRows[tab_id] = "";
                    colConfig[tab_id] = self.createColConfig(index);

                    $(el).DataTable({
                        lengthMenu: [
                            [25, 50, 100, -1],
                            [25, 50, 100, "All"]
                        ],
                        language: {
                            emptyTable: "No calls to display"
                        },
                        columns: colConfig[tab_id],
                        createdRow: (row) => $(row).addClass('dataTablesRow'),
                        sDom: 'ltpi'
                    });
                });
            },

            refreshTableData() {
                module.ajax("getData", {}).then((response) => {
                    if (!response || !response.data) return;

                    this.displayedData = response.data;

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

                    $('.callTable').each((index, el) => {
                        let tab_id = $(el).closest('.tab-pane').prop('id');
                        let dt = $(el).DataTable();
                        let rows = this.displayedData[tab_id] || [];

                        dt.clear();
                        dt.rows.add(rows);
                        dt.draw();
                    });

                    this.toggleCallBackCol();
                }).catch((err) => {
                    console.error("Error fetching call list data:", err);
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