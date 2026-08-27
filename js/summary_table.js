(() => {
    const callSummaryPageSize = 20;
    const module = ExternalModules.UWMadison.CallLog;

    const threeDotClick = () => {
        if (typeof Swal === 'undefined') return;

        let settingsHtml = module.renderers ? module.renderers.renderCallHistorySettings() : '';
        let callHistoryRows = "";
        $.each(module.metadata, (k, v) => {
            if (module.renderers && module.renderers.renderCallHistoryRow) {
                callHistoryRows += module.renderers.renderCallHistoryRow(v.name || '', k, !!v.complete);
            }
        });
        settingsHtml += callHistoryRows;

        Swal.fire({
            title: 'Call Metadata Settings',
            html: settingsHtml,
            showCancelButton: true,
            focusCancel: true
        }).then((result) => {
            if (!result.isConfirmed) return;

            $(".callMetadataEdit").each(function () {
                const callId = $(this).data('call');
                if (module.metadata && module.metadata[callId]) {
                    module.metadata[callId].complete = $(this).is(':checked');
                }
            });

            module.ajax("metadataSave", {
                record: getParameterByName('id'),
                metadata: JSON.stringify(module.metadata)
            }).then(function () {
                window.onbeforeunload = function () { };
                window.location.reload();
            }).catch(function (err) {
                console.error(err);
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
        let date = data['call_open_datetime'] || '';
        let note = data['call_notes'] ? data['call_notes'] : "No Notes Taken";
        let logClosed = data['call_outcome'] === "1" ? (module.renderers ? module.renderers.renderCallClosed() : '') : "";
        let userName = data['call_open_user_full_name'] || '';

        row.child(`<b>${date}</b><br>${userName} - ${note}${logClosed}`, 'dataTableChild').show();
        $(target).next().addClass($(target).hasClass('even') ? 'even' : 'odd');
        $(target).addClass('shown');
    };

    const openDeleteModal = () => {
        if (typeof Swal === 'undefined') return;

        Swal.fire({
            icon: 'warning',
            title: 'Are you sure?',
            text: "Are you sure you want to delete the previous instance of Call Log",
            showCancelButton: true,
            showConfirmButton: true,
            focusCancel: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#337ab7',
            cancelButtonText: 'Close',
            confirmButtonText: 'Delete Call Log'
        }).then((result) => {
            if (!result.isConfirmed) return;

            let instance = getParameterByName('instance') > 1 ? getParameterByName('instance') - 1 : 1;

            module.ajax("callDelete", {
                record: getParameterByName('id')
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

    const buildCallSummaryTable = () => {
        if (!module.metadata || !module.renderers || !module.renderers.renderCallHistoryTable) return;
        if (!module.data || !(Object.keys(module.data).length > 1 || !module.data[1] || module.data[1]['call_id'])) return;

        if ($("#center").length && !$(".callHistoryContainer").length) {
            $("#center").append(module.renderers.renderCallHistoryTable());
            let targetOffset = $("#record_id-tr").length ? $("#record_id-tr").offset().top : 0;
            if (targetOffset) {
                $('.callHistoryContainer').css('top', targetOffset);
            }
        }

        $('.callSummaryTable').DataTable({
            pageLength: 20,
            dom: Object.keys(module.data).length > callSummaryPageSize ? 'rtp' : 'rt',
            order: [
                [0, "desc"]
            ],
            createdRow: (row) => $(row).addClass('dataTablesRow'),
            columns: [
                { title: '#', data: 'instance', className: 'dt-center' },
                { title: 'Call', data: 'name' },
                { title: 'Msg', data: 'leftMessage', className: 'dt-body-center' },
                { title: 'Call time', data: 'datetime' },
                { title: '', data: 'deleteInstance', bSortable: false }
            ],
            data: $.map(module.data, (data, index) => {
                let m = module.metadata[data['call_id']];
                let dataKeys = Object.keys(module.data);
                let allowDelete = (dataKeys[dataKeys.length - 1] == index);
                return {
                    instance: index,
                    name: (m && m['name']) ? m['name'] : (data['call_id'] || "Unknown"),
                    datetime: data['call_open_datetime'] || '',
                    leftMessage: (data['call_left_message'] && data['call_left_message'][1] === "1") ? 'Yes' : 'No',
                    deleteInstance: allowDelete ? (module.renderers ? module.renderers.renderDeleteLog() : '') : ''
                };
            })
        });

        $(".callHistoryContainer .sorting_disabled").html(module.renderers ? module.renderers.renderSettingsButton() : '');
        $(".callSummarySettings").on('click', threeDotClick);

        $('body').on('click', '.dataTablesRow', childRowExpand);

        if (getParameterByName('page') !== module.static.instrument) return;

        $('.deleteInstance').on('click', openDeleteModal);
    };

    buildCallSummaryTable();
})();