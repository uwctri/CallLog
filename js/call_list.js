(() => {

    const module = ExternalModules.UWMadison.CallLog;
    const earlyCall = 5 * 60 * 1000;
    const pageRefresh = 1 * 60 * 1000;

    let alwaysShowCallbackCol = false;
    let hideCalls = true;
    let childRows = {};
    let colConfig = {};
    let displayedData = {};

    let activeCallerFilter = '';

    const setupSearch = () => {
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

        $(document).on('change', '.caller-filter-select', (e) => {
            activeCallerFilter = $(e.target).val() || '';
            $('.caller-filter-select').val(activeCallerFilter);
            $('.callTable:visible').DataTable().draw();
        });

        $.fn.dataTable.ext.search.push(
            (_settings, _searchData, _index, rowData, _counter) => {
                if (hideCalls && (
                    (rowData['_atMaxAttempts'] && !rowData['_callbackToday']) || rowData['_callbackNotToday'] || rowData['_noCallsToday'] || rowData['_futureAdhoc']
                )) {
                    return false;
                }
                if (activeCallerFilter) {
                    let caller = rowData['call_open_user_full_name'] || rowData['_callStartedBy'] || rowData['call_open_user'] || '';
                    if (caller.trim() !== activeCallerFilter) {
                        return false;
                    }
                }
                return true;
            }
        );

        $(".toggleHiddenCalls").on('click', () => {
            hideCalls = !hideCalls;
            toggleCallBackCol();
            $('.callTable:visible').DataTable().draw();
        });
    };

    const setupLocalSettings = () => {
        const key = `ExternalModules.UWMadison.CallLog.${pid}`;
        $(".call-link").on('click', (el) => {
            let tab = $(el.currentTarget).data('tabid');
            localStorage.setItem(key, JSON.stringify({ tab }));
        });

        let data = localStorage.getItem(key);
        if (!data) return;

        data = JSON.parse(data);
        if (data.tab && $(`.call-link[data-tabid=${data.tab}]`).length) {
            $(`.call-link[data-tabid=${data.tab}]`).click();
        }
    };

    const toggleCallBackCol = () => {
        let currentTabId = $('.tab-pane:visible').prop('id');
        let currentData = displayedData[currentTabId] || [];

        let tabConfig = (module.tabs && module.tabs.config) ? module.tabs.config.find(tab => tab.tab_id === currentTabId) : null;
        let showAdhocDates = tabConfig ? tabConfig.showAdhocDates : false;

        let hasCallBacks = currentData.some(row => row._callbackRequestor);

        if (alwaysShowCallbackCol || hasCallBacks || showAdhocDates) {
            $(".callbackCol").show();
        } else {
            $(".callbackCol").hide();
        }
    };

    const callURLclick = (event) => {
        const target = event.currentTarget;
        let id = $(target).data('callid');

        module.ajax("setCallStarted", {
            id: id,
            record: $(target).text(),
            user: module.user
        }).catch(function (err) {
            console.error(err);
        });
    };

    const endCall = (event) => {
        const target = event.currentTarget;
        let id = $(target).data('callid');

        module.ajax("setCallEnded", {
            id: id,
            record: $(target).data('record'),
        }).then(function () {
            window.location.reload();
        }).catch(function (err) {
            console.error(err);
        });
        return false;
    };

    const noCallsToday = (event) => {
        const target = event.currentTarget;
        let id = $(target).data('callid');

        module.ajax("setNoCallsToday", {
            id: id,
            record: $(target).data('record'),
        }).then(function () {
            let dt = $('.callTable:visible').DataTable();
            let row = dt.row($(target).closest('tr'));
            let data = row.data();
            data['_noCallsToday'] = true;
            row.data(data).draw();
        }).catch(function (err) {
            console.error(err);
        });
        return false;
    };

    const createColConfig = (index, tab_id) => {
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

            if (fieldIndex === 0) {
                col.className = 'firstDataCol';
            }

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
    };

    const clickToExpand = (event) => {
        let target = event.currentTarget;
        let table = $(target).closest('table').DataTable();
        let row = table.row(target);

        if (row.child.isShown()) {
            row.child.hide();
            $(target).removeClass('shown');
            return;
        }

        let data = row.data();

        let childHtml = `<div class="container dtChildData">`;
        if (data['_callNotes']) {
            let notesList = data['_callNotes'].split('|||').filter(Boolean);
            notesList.forEach(n => {
                let parts = n.split('||');
                childHtml += `<div class="row"><strong>${parts[0]} ${parts[1]}:</strong> ${parts[3]}</div>`;
            });
        }
        childHtml += `</div>`;

        row.child(childHtml, 'dataTableChild').show();
        $(target).addClass('shown');
    };

    const refreshTableData = () => {
        module.ajax("getData", {}).then((response) => {
            if (!response || !response.data) return;

            displayedData = response.data;

            let callers = new Set();
            Object.values(displayedData).forEach(tabRows => {
                if (Array.isArray(tabRows)) {
                    tabRows.forEach(row => {
                        let caller = row['call_open_user_full_name'] || row['_callStartedBy'] || row['call_open_user'] || '';
                        if (caller && caller.trim()) callers.add(caller.trim());
                    });
                }
            });

            const $selects = $('.caller-filter-select');
            if ($selects.length) {
                const currentVal = $selects.first().val() || '';
                $selects.each((_, sel) => {
                    let $s = $(sel);
                    $s.empty().append('<option value="">-- All Callers / Users --</option>');
                    Array.from(callers).sort().forEach(c => {
                        $s.append(`<option value="${c}">${c}</option>`);
                    });
                    if (currentVal && callers.has(currentVal)) {
                        $s.val(currentVal);
                    }
                });
            }

            $('.callTable').each((index, el) => {
                let tab_id = $(el).closest('.tab-pane').prop('id');
                let dt = $(el).DataTable();
                let rows = displayedData[tab_id] || [];

                dt.clear();
                dt.rows.add(rows);
                dt.draw();
            });

            toggleCallBackCol();
        }).catch((err) => {
            console.error("Error fetching call list data:", err);
        });
    };

    const setup = () => {
        if (module.configError) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Call Log Configuration Issue',
                    text: 'The Call Log External Module requires the Call Log and Call Metadata instruments to exist.',
                });
            }
            return;
        }

        if (!module.tabs || !module.tabs.config || !module.tabs.config.length) {
            return;
        }

        setupSearch();

        $('.callTable').each((index, el) => {
            let tab_id = $(el).closest('.tab-pane').prop('id');
            childRows[tab_id] = "";
            colConfig[tab_id] = createColConfig(index, tab_id);

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

        setupLocalSettings();

        $(".call-list-card, .card").fadeIn();

        $('.callTable').on('click', '.dataTablesRow', clickToExpand);
        $('.callTable').on('click', '.noCallsButton', noCallsToday);
        $('.callTable').on('click', '.endCallButton', endCall);
        $('.callTable').on('click', '.rowLink', callURLclick);

        toggleCallBackCol();
        refreshTableData();
        $(".dataTables_empty").text('Loading...');

        setInterval(refreshTableData, pageRefresh);
    };

    setup();

})();