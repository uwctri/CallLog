(() => {
    const callHistoryPageSize = 20;
    const module = ExternalModules.UWMadison.CallLog;
    const getParam = (name) => module.utils.getParam(name);
    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const saveMetadata = (metadata) => module.utils.saveMetadata(metadata, true);

    const threeDotClick = () => {
        if (typeof Swal === 'undefined' && (!module.swal || !module.swal.fire)) return;

        const isCallLogPage = (getParam('page') === (module.static ? module.static.instrument : 'call_log'));
        const hasCallHistory = module.data && Object.keys(module.data).length > 0;
        const rawMetadata = escapeHtml(JSON.stringify(module.metadata || {}, null, 2));
        let settingsHtml = module.renderers.renderCallHistorySettings();
        let callHistoryRows = "";
        $.each(module.metadata, (k, v) => {
            const statusVal = v.status || 'incomplete';
            callHistoryRows += module.renderers.renderCallHistoryRow(v.name || '', k, statusVal);
        });
        settingsHtml += callHistoryRows + '</div>';
        settingsHtml += module.renderers.renderCallHistoryRawMetadata(rawMetadata);
        if (isCallLogPage && hasCallHistory) {
            settingsHtml += module.renderers.renderCallHistoryDeleteAction();
        }
        settingsHtml += '</div></div>';

        (module.swal ? module.swal.fire : Swal.fire)({
            title: 'Call Metadata Settings',
            customIcon: '<i class="fas fa-sliders-h"></i>',
            customClass: {
                popup: 'call-history-settings-popup'
            },
            html: settingsHtml,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check me-1.5"></i> Save Settings',
            cancelButtonText: 'Cancel',
            focusCancel: true,
            didOpen: () => {
                $('.callMetadataStatusSelect').off('change').on('change', function () {
                    const newStatus = $(this).val();
                    $(this)
                        .removeClass('is-complete is-incomplete is-expired')
                        .addClass(`is-${newStatus}`);
                });
                $('#enableRawMetadataEdit').off('change').on('change', function () {
                    $('.callHistoryRawMetadata')
                        .prop('readonly', !this.checked)
                        .toggleClass('call-history-raw-editable', this.checked);
                });
                $('.deleteCallHistoryInstance').off('click').on('click', function () {
                    const swalObj = window.Swal || (typeof Swal !== 'undefined' ? Swal : null);
                    if (swalObj && typeof swalObj.close === 'function') swalObj.close();
                    setTimeout(openDeleteModal, 0);
                });
            }
        }).then((result) => {
            if (!result.isConfirmed) return;

            const rawEditEnabled = $('#enableRawMetadataEdit').is(':checked');
            let metadataToSave = module.metadata;
            if (rawEditEnabled) {
                try {
                    metadataToSave = JSON.parse($('.callHistoryRawMetadata').val());
                } catch (err) {
                    (module.swal ? module.swal.error : Swal.fire)('Invalid Metadata', 'The raw metadata must contain valid JSON before it can be saved.', { icon: 'error' });
                    return;
                }
                if (!metadataToSave || Array.isArray(metadataToSave) || typeof metadataToSave !== 'object') {
                    (module.swal ? module.swal.error : Swal.fire)('Invalid Metadata', 'The raw metadata must be a JSON object.', { icon: 'error' });
                    return;
                }
            }

            $(".callMetadataStatusSelect").each(function () {
                const callId = $(this).data('call');
                if (metadataToSave && metadataToSave[callId]) {
                    metadataToSave[callId].status = $(this).val() || 'incomplete';
                }
            });

            if (!rawEditEnabled) {
                saveMetadata(metadataToSave);
                return;
            }

            const confirmPromise = module.swal
                ? module.swal.confirm(
                    'Save Raw Metadata?',
                    'This will replace the stored call metadata with the JSON you entered. Continue only if you have verified the payload.',
                    {
                        confirmButtonText: 'Save Raw Metadata',
                        cancelButtonText: 'Cancel',
                        isDestructive: true,
                        focusCancel: true
                    }
                )
                : Swal.fire({
                    icon: 'warning',
                    title: 'Save Raw Metadata?',
                    text: 'This will replace the stored call metadata with the JSON you entered. Continue only if you have verified the payload.',
                    showCancelButton: true,
                    confirmButtonText: 'Save Raw Metadata',
                    cancelButtonText: 'Cancel',
                    focusCancel: true
                });
            confirmPromise.then((confirmation) => {
                if (confirmation.isConfirmed) saveMetadata(metadataToSave);
            });
        });
    };

    const childRowExpand = (event) => {
        let target = event.currentTarget;
        let table = $(target).closest('table').DataTable();
        let row = table.row(target);
        if (row.child.isShown()) {
            row.child.hide();
            $(target).removeClass('shown');
            return;
        }
        let data = (module.data && module.data[row.data()['instance']]) ? module.data[row.data()['instance']] : {};
        let date = data['call_open_datetime'] || (data['call_open_date'] ? `${data['call_open_date']} ${data['call_open_time'] || ''}`.trim() : '');
        let note = data['call_notes'] ? data['call_notes'] : "No Notes Taken";
        let logClosed = data['call_outcome'] === "1" ? (module.renderers ? module.renderers.renderCallClosed() : '') : "";
        let userName = data['call_open_user_full_name'] || '';

        row.child(`<div class="call-child-note p-2 border-start border-3 border-primary bg-light"><div class="small text-muted mb-1"><strong>${userName || 'User'}</strong> &bull; ${date} ${logClosed}</div><div class="small text-dark" style="white-space: pre-wrap;">${note}</div></div>`, 'dataTableChild').show();
        $(target).next().addClass($(target).hasClass('even') ? 'even' : 'odd');
        $(target).addClass('shown');
    };

    const openDeleteModal = () => {
        if (typeof Swal === 'undefined' && (!module.swal || !module.swal.confirm)) return;

        let confirmPromise = module.swal
            ? module.swal.confirm(
                'Delete Call Log Instance',
                'Are you sure you want to delete the previous instance of this Call Log? This action cannot be undone.',
                {
                    confirmButtonText: '<i class="fas fa-trash-alt me-1.5"></i> Delete Instance',
                    cancelButtonText: 'Cancel',
                    isDestructive: true,
                    focusCancel: true
                }
            )
            : Swal.fire({
                icon: 'warning',
                title: 'Delete Call Log Instance',
                text: 'Are you sure you want to delete the previous instance of this Call Log? This action cannot be undone.',
                showCancelButton: true,
                confirmButtonText: 'Delete Instance',
                cancelButtonText: 'Cancel',
                focusCancel: true
            });

        confirmPromise.then((result) => {
            if (!result.isConfirmed) return;

            let instance = getParam('instance') > 1 ? getParam('instance') - 1 : 1;

            module.ajax("callDelete", {
                record: getParam('id')
            }).then(function () {
                let url = new URL(location.href);
                url.searchParams.set('instance', instance);
                window.onbeforeunload = function () { };
                window.location.href = url.toString();
            }).catch(function (err) {
                console.error(err);
            });
        });
    };

    const formatStopwatchTime = (totalSeconds) => {
        const s = Math.max(0, Math.floor(totalSeconds));
        const hrs = Math.floor(s / 3600);
        const mins = Math.floor((s % 3600) / 60);
        const secs = s % 60;
        const mm = String(mins).padStart(2, '0');
        const ss = String(secs).padStart(2, '0');
        if (hrs > 0) {
            const hh = String(hrs).padStart(2, '0');
            return `${hh}:${mm}:${ss}`;
        }
        return `${mm}:${ss}`;
    };

    const initCallStopwatch = () => {
        if (!module.enableCallTimer) return;
        const $stopwatch = $('.callStopwatchContainer');
        if (!$stopwatch.length) return;

        let elapsedSeconds = 0;
        let isRunning = false;
        let timerInterval = null;

        const updateDisplay = () => {
            $('#stopwatchDisplay').text(formatStopwatchTime(elapsedSeconds));
        };

        const setRunningState = (running) => {
            isRunning = running;
            const $toggleBtn = $('#stopwatchToggleBtn');
            const $statusBadge = $('.stopwatch-status-badge');
            if (isRunning) {
                $toggleBtn
                    .removeClass('btn-primary')
                    .addClass('btn-warning')
                    .html('<i class="fas fa-pause me-1"></i> <span class="stopwatch-toggle-label">Pause</span>');
                $statusBadge
                    .removeClass('bg-secondary-subtle text-secondary bg-warning-subtle text-warning')
                    .addClass('bg-success-subtle text-success border-success-subtle')
                    .text('Running');
                $('.callStopwatchContainer').addClass('stopwatch-running');
            } else {
                $toggleBtn
                    .removeClass('btn-warning')
                    .addClass('btn-primary')
                    .html('<i class="fas fa-play me-1"></i> <span class="stopwatch-toggle-label">Resume</span>');
                $statusBadge
                    .removeClass('bg-secondary-subtle text-secondary bg-success-subtle text-success')
                    .addClass('bg-warning-subtle text-warning border-warning-subtle')
                    .text('Paused');
                $('.callStopwatchContainer').removeClass('stopwatch-running');
            }
        };

        const startTimer = () => {
            if (isRunning) return;
            setRunningState(true);
            timerInterval = setInterval(() => {
                elapsedSeconds += 1;
                updateDisplay();
            }, 1000);
        };

        const pauseTimer = () => {
            if (!isRunning) return;
            clearInterval(timerInterval);
            timerInterval = null;
            setRunningState(false);
        };

        const resetTimer = () => {
            clearInterval(timerInterval);
            timerInterval = null;
            isRunning = false;
            elapsedSeconds = 0;
            updateDisplay();
            $('#stopwatchToggleBtn')
                .removeClass('btn-warning')
                .addClass('btn-primary')
                .html('<i class="fas fa-play me-1"></i> <span class="stopwatch-toggle-label">Start</span>');
            $('.stopwatch-status-badge')
                .removeClass('bg-success-subtle text-success bg-warning-subtle text-warning')
                .addClass('bg-secondary-subtle text-secondary')
                .text('Ready');
            $('.callStopwatchContainer').removeClass('stopwatch-running');
        };

        $('#stopwatchToggleBtn').off('click').on('click', () => {
            if (isRunning) {
                pauseTimer();
            } else {
                startTimer();
            }
        });

        $('#stopwatchResetBtn').off('click').on('click', resetTimer);

        // Expose timer methods on module for tab integration
        module.stopwatch = {
            start: startTimer,
            pause: pauseTimer,
            reset: resetTimer,
            getElapsed: () => elapsedSeconds,
            isRunning: () => isRunning
        };

        // Auto-start when a call tab is active
        if ($('.callTab.active').length || $('.callTab').length) {
            startTimer();
        }
    };

    const positionHistoryContainer = () => {
        const $container = $('.callHistoryContainer');
        const $stopwatch = $('.callStopwatchContainer');
        if (!$container.length && !$stopwatch.length) return;

        const $form = $('#form').length ? $('#form') : ($('#questiontable').length ? $('#questiontable') : null);
        if (!$form || !$form.length) return;

        const $table = $('#center table.form_border').length ? $('#center table.form_border') : $form;
        const formOffset = $table.offset() || $form.offset();
        const formWidth = Math.min($table.outerWidth() || $form.outerWidth() || 800, 820);
        const windowWidth = $(window).width();
        const windowHeight = $(window).height();
        const scrollTop = $(window).scrollTop();
        const sideMargin = 16;
        const maxSidebarWidth = 380;
        const minSidebarWidth = 310;

        const targetLeft = Math.round(formOffset.left + formWidth + sideMargin);
        const availableRight = windowWidth - targetLeft - 15;

        if (availableRight >= minSidebarWidth) {
            const sidebarWidth = Math.min(maxSidebarWidth, availableRight);
            const formTop = Math.round(formOffset.top);
            const $fixedNav = $('.navbar.fixed-top, #redcap-header, .rcproject-navbar');
            const minTop = $fixedNav.length ? Math.round($fixedNav.outerHeight() + 10) : 55;
            const dockedTop = Math.max(formTop - scrollTop, minTop);

            const hasStopwatch = $stopwatch.length > 0;
            const stopwatchHeight = hasStopwatch ? Math.round($stopwatch.outerHeight() || 140) : 0;
            const historyMaxHeight = Math.max(160, windowHeight - dockedTop - stopwatchHeight - 35);

            if ($container.length) {
                $container.removeClass('callHistoryStacked').addClass('callHistoryDocked').css({
                    position: 'fixed',
                    left: `${targetLeft}px`,
                    top: `${dockedTop}px`,
                    width: `${sidebarWidth}px`,
                    'max-width': `${sidebarWidth}px`,
                    'max-height': `${historyMaxHeight}px`,
                    'overflow-y': 'auto',
                    'z-index': 1000,
                    margin: 0,
                    display: 'block'
                });
            }

            if (hasStopwatch) {
                const historyHeight = $container.length ? Math.round($container.outerHeight() || 0) : 0;
                const stopwatchTop = dockedTop + historyHeight + 12;

                $stopwatch.removeClass('callHistoryStacked').addClass('callHistoryDocked').css({
                    position: 'fixed',
                    left: `${targetLeft}px`,
                    top: `${stopwatchTop}px`,
                    width: `${sidebarWidth}px`,
                    'max-width': `${sidebarWidth}px`,
                    'z-index': 1000,
                    margin: 0,
                    display: 'block'
                });
            }
        } else {
            if ($container.length) {
                $container.removeClass('callHistoryDocked').addClass('callHistoryStacked').css({
                    position: 'relative',
                    left: 'auto',
                    top: 'auto',
                    width: '100%',
                    'max-width': `${formWidth}px`,
                    'max-height': 'none',
                    'overflow-y': 'visible',
                    'z-index': 'auto',
                    'margin-top': '24px',
                    'margin-bottom': '16px',
                    display: 'block'
                });
                if ($form.next()[0] !== $container[0]) {
                    $form.after($container);
                }
            }

            if ($stopwatch.length) {
                $stopwatch.removeClass('callHistoryDocked').addClass('callHistoryStacked').css({
                    position: 'relative',
                    left: 'auto',
                    top: 'auto',
                    width: '100%',
                    'max-width': `${formWidth}px`,
                    'max-height': 'none',
                    'overflow-y': 'visible',
                    'z-index': 'auto',
                    'margin-top': '16px',
                    'margin-bottom': '24px',
                    display: 'block'
                });
                if ($container.length) {
                    $container.after($stopwatch);
                } else {
                    $form.after($stopwatch);
                }
            }
        }
    };

    const buildCallHistoryTable = () => {
        if (!module.metadata) return;

        const validInstances = [];
        if (module.data && typeof module.data === 'object') {
            $.each(module.data, function (inst, d) {
                if (d && (d['call_id'] || d['call_open_datetime'] || d['call_open_date'] || d['call_outcome'])) {
                    validInstances.push({ instance: inst, data: d });
                }
            });
        }

        if (!$(".callHistoryContainer").length) {
            const htmlToAppend = validInstances.length > 0
                ? module.renderers.renderCallHistoryTable()
                : module.renderers.renderCallHistoryEmpty();
            
            const $form = $('#form');
            if ($form.length) {
                $form.after(htmlToAppend);
            } else {
                $("#center").append(htmlToAppend);
            }
        }

        // Render Stopwatch Widget below Call History table
        if (Boolean(module.enableCallTimer) && !$(".callStopwatchContainer").length) {
            const stopwatchHtml = module.renderers.renderCallStopwatchWidget();
            const $historyContainer = $(".callHistoryContainer");
            if ($historyContainer.length) {
                $historyContainer.after(stopwatchHtml);
            } else if ($('#form').length) {
                $('#form').after(stopwatchHtml);
            } else {
                $("#center").append(stopwatchHtml);
            }
            initCallStopwatch();
        }

        $(".callHistorySettings").off('click').on('click', threeDotClick);

        if (validInstances.length === 0) {
            positionHistoryContainer();
            setTimeout(positionHistoryContainer, 150);
            $(window).off('resize.callHistory scroll.callHistory').on('resize.callHistory scroll.callHistory', positionHistoryContainer);
            $(window).on('load', positionHistoryContainer);
            if (window.ResizeObserver && $('#center').length && !window.__callHistoryResizeObserver) {
                window.__callHistoryResizeObserver = new ResizeObserver(() => {
                    positionHistoryContainer();
                });
                window.__callHistoryResizeObserver.observe($('#center')[0]);
                if ($('#west').length) window.__callHistoryResizeObserver.observe($('#west')[0]);
            }
            return;
        }

        $('.callHistoryTable').DataTable({
            pageLength: 20,
            dom: validInstances.length > callHistoryPageSize ? 'rtp' : 'rt',
            ordering: false,
            createdRow: (row) => $(row).addClass('dataTablesRow'),
            columns: [
                { title: '#', data: 'instance', className: 'dt-center text-muted small' },
                { title: 'Call', data: 'name', className: 'fw-semibold' },
                { title: 'Msg', data: 'leftMessage', className: 'dt-body-center' },
                { title: 'Call time', data: 'datetime', className: 'small' }
            ],
            data: validInstances.map((item) => {
                const data = item.data;
                const index = item.instance;
                const m = (module.metadata && data['call_id']) ? module.metadata[data['call_id']] : null;
                let callName = (m && m['name']) ? m['name'] : '';
                if (!callName && data['call_id']) {
                    const cid = String(data['call_id']);
                    const baseId = cid.split('|')[0].split('||')[0];
                    if (module.callNames && module.callNames[baseId]) {
                        callName = module.callNames[baseId];
                    } else if (cid.includes('||')) {
                        callName = 'Adhoc Call';
                    } else {
                        callName = cid;
                    }
                }
                return {
                    instance: index,
                    name: callName || "Unknown Call",
                    datetime: data['call_open_datetime'] || (data['call_open_date'] ? `${data['call_open_date']} ${data['call_open_time'] || ''}`.trim() : ''),
                    leftMessage: (data['call_left_message'] && (data['call_left_message'][1] === "1" || data['call_left_message'] === "1")) ? '<span class="badge bg-info text-white">Yes</span>' : '<span class="text-muted small">No</span>'
                };
            })
        });

        $('body').off('click', '.dataTablesRow').on('click', '.dataTablesRow', childRowExpand);

        positionHistoryContainer();
        setTimeout(positionHistoryContainer, 150);
        $(window).off('resize.callHistory scroll.callHistory').on('resize.callHistory scroll.callHistory', positionHistoryContainer);
        $(window).on('load', positionHistoryContainer);

        if (window.ResizeObserver && $('#center').length && !window.__callHistoryResizeObserver) {
            window.__callHistoryResizeObserver = new ResizeObserver(() => {
                positionHistoryContainer();
            });
            window.__callHistoryResizeObserver.observe($('#center')[0]);
            if ($('#west').length) window.__callHistoryResizeObserver.observe($('#west')[0]);
        }
    };

    $(function () {
        buildCallHistoryTable();
    });
})();