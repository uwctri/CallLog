(() => {
    const callSummaryPageSize = 20;

    const threeDotClick = () => {
        Swal.fire({
            title: 'Call Metadata Settings',
            html: CallLog.templates.callHistorySettings,
            showCancelButton: true,
            focusCancel: true
        }).then((result) => {

            if (!result.isConfirmed)
                return;

            // Edit the CallLog.metadata
            $(".callMetadataEdit").each(function () {
                CallLog.metadata[$(this).data('call')].complete = $(this).is(':checked');
            });

            // Write back the metadata
            CallLog.em.ajax("metadataSave", {
                record: getParameterByName('id'),
                metadata: JSON.stringify(CallLog.metadata)
            }).then(function (response) {
                console.log(response);
                window.onbeforeunload = function () { };
                window.location = window.location;
            }).catch(function (err) {
                console.log(err);
            });
        })
    }

    const childRowExpand = (event) => {
        let target = event.currentTarget;
        let table = $(target).closest('table').DataTable();
        let row = table.row(target);
        if (row.child.isShown()) {
            row.child.hide();
            $(target).removeClass('shown');
            return;
        }
        let data = CallLog.data[row.data()['instance']];
        const opt = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
        let date = new Date(data['call_open_datetime']);
        date = date.toLocaleDateString('en-uk', opt).replace(',', '')
        let note = data['call_notes'] ? data['call_notes'] : "No Notes Taken";
        let logClosed = data['call_outcome'] == "1" ? CallLog.templates.callClosed : "";
        row.child(`<b>${date}</b><br>${data['call_open_user_full_name']} - ${note}${logClosed}`, 'dataTableChild').show();
        $(target).next().addClass($(target).hasClass('even') ? 'even' : 'odd');
        $(target).addClass('shown');
    }

    const openDeleteModal = () => {
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
            if (!result.isConfirmed)
                return;

            let instance = getParameterByName('instance') > 1 ? getParameterByName('instance') - 1 : 1;

            // Post to delete, removes metadata too
            CallLog.em.ajax("callDelete", {
                record: getParameterByName('id')
            }).then(function (response) {
                console.log(response);
                let url = new URL(location.href);
                url.searchParams.set('instance', instance);
                window.onbeforeunload = function () { };
                window.location = url;
            }).catch(function (err) {
                console.log(err);
            });

        });
    }

    const buildCallSummaryTable = () => {
        // Check if we have anything to actually build
        if (isEmpty(CallLog.metadata) || !(Object.keys(CallLog.data).length > 1 || !CallLog.data[1] || CallLog.data[1]['call_id']))
            return;

        // Insert the table, lock its location
        $("#center").append(CallLog.templates.callHistoryTable);
        $('.callHistoryContainer').css('top', $("#record_id-tr").offset().top);

        // Init the DataTable
        $('.callSummaryTable').DataTable({
            pageLength: 20,
            dom: Object.keys(CallLog.data).length > callSummaryPageSize ? 'rtp' : 'rt',
            order: [
                [0, "desc"]
            ],
            createdRow: (row, data, index) => $(row).addClass('dataTablesRow'),
            columns: [
                { title: '#', data: 'instance', className: 'dt-center' },
                { title: 'Call', data: 'name' },
                { title: 'Msg', data: 'leftMessage', className: 'dt-body-center' },
                {
                    title: 'Call time',
                    data: 'datetime',
                    render: (data, type, row, meta) =>
                        (type === 'display' || type === 'filter') ? formatDate(new Date(data), CallLog.format.dateTime).toLowerCase() : data
                },
                { title: '', data: 'deleteInstance', bSortable: false }
            ],
            data: $.map(CallLog.data, (data, index) => {
                let m = CallLog.metadata[data['call_id']];
                let allowDelete = (Object.keys(CallLog.data)[Object.keys(CallLog.data).length - 1] == index);
                return {
                    instance: index,
                    name: m && m['name'] ? m['name'] : (data['call_id'] || "Unknown"),
                    datetime: data['call_open_datetime'],
                    leftMessage: data['call_left_message'][1] == "1" ? 'Yes' : 'No',
                    deleteInstance: allowDelete ? CallLog.templates.deleteLog : ''
                };
            })
        });

        // Adjust width upward by 10% for a little extra room
        $(".callHistoryContainer").css('width', $(".callHistoryContainer").css('width').slice(0, -2) * 1.1)

        // Build the "settings" menu, used for un-completing any calls
        $(".callHistoryContainer .sorting_disabled").html(CallLog.templates.settingsButton);
        let callHistroyRows = "";
        $.each(CallLog.metadata, (k, v) => {
            callHistroyRows += CallLog.templates.callHistoryRow
                .replace('CALLID', k)
                .replace('CALLNAME', v.name)
                .replace('checked', v.complete ? 'checked' : '');
        });
        CallLog.templates.callHistorySettings += callHistroyRows;
        $(".callSummarySettings").on('click', threeDotClick);

        // Enable click to expand child row
        $('body').on('click', '.dataTablesRow', childRowExpand);

        // If not on the call log then we are done
        if (getParameterByName('page') != CallLog.static.instrument)
            return;

        // Allow deleting the most recent version of the call log
        $('.deleteInstance').on('click', openDeleteModal);
    }

    buildCallSummaryTable();
})();