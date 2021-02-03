function buildCallSummaryTable() {
    if ( isEmpty(CTRICallLog.metadata) || !(Object.keys(CTRICallLog.data).length > 1 || CTRICallLog.data[1]['call_id']) )
        return;
    $("#center").append(`<div class="callHistoryContainer"><table class="callSummaryTable compact" style="width:100%"></table></div>`);
    $('.callHistoryContainer').css('top',$("#record_id-tr").offset().top);
    $('.callSummaryTable').DataTable({
        pageLength: 50,
        dom: 'rt',
        order: [[ 0, "desc" ]],
        createdRow: (row,data,index) => $(row).addClass('dataTablesRow'),
        columns: [
            {title:'#',data:'instance',className:'dt-center'},
            {title:'Call',data:'name'},
            {title:'Msg',data:'leftMessage', className: 'dt-body-center'},
            {title:'Call time',data:'datetime', render: (data,type,row,meta) =>
                ( type === 'display' || type === 'filter' ) ? formatDate(new Date(data),CTRICallLog.defaultDateTimeFormat).toLowerCase(): data },
            {title:'',data:'deleteInstance',bSortable: false}
        ],
        data: $.map(CTRICallLog.data, function(data, index) { 
            let m = CTRICallLog.metadata[data['call_id']];
            let allowDelete = (Object.keys(CTRICallLog.data)[Object.keys(CTRICallLog.data).length-1] == index) && (data['call_outcome'] != '1');
            return {
                instance: index,
                name: m && m['name'] ? m['name'] : (data['call_id'] || "Unknown"),
                datetime: data['call_open_datetime'],
                leftMessage: data['call_left_message'][1] == "1" ? 'Yes' : 'No',
                deleteInstance: allowDelete ? '<a class="deleteInstance"><i class="fas fa-times"></i></a>' : ''
            };
        })
    });
    
    // Enable click to expand child row
    $('.dataTablesRow').on('click', function () {
        let table = $(this).closest('table').DataTable();
        let row = table.row( this );
        if ( row.child.isShown() ) {
            row.child.hide();
            $(this).removeClass('shown');
        } else {
            let data = CTRICallLog.data[row.data()['instance']];
            let note = data['call_notes'] ? data['call_notes'] : "No Notes Taken";
            row.child( `${data['call_open_user_full_name']} - ${note}`, 'dataTableChild' ).show();
            $(this).next().addClass( $(this).hasClass('even') ? 'even' : 'odd' );
            $(this).addClass('shown');
        }
    });
    
    // If not on the call log then we are done
    if ( getParameterByName('page') != CTRICallLog.static.instrumentLower)
        return;
    
    // Allow deleting the most recent version of the call log
    $('.deleteInstance').on('click', function () {
        event.stopPropagation(); // Don't expand child row
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
            if (result.isConfirmed) {
                let instance = getParameterByName('instance') > 1 ? getParameterByName('instance')-1 : 1;
                // Post to delete, removes metadata too
                $.ajax({
                    method: 'POST',
                    url: CTRICallLog.calldeletePOST,
                    data: {record: getParameterByName('id')},
                    error: (jqXHR, textStatus, errorThrown) => console.log(textStatus + " " +errorThrown),
                    success: (data) => {
                        let url = new URL(location.href);
                        url.searchParams.set('instance',instance);
                        window.onbeforeunload = function() { };
                        window.location = url;
                    }
                });
            }
        })
    });
}

$(document).ready(function () {
    if ( getParameterByName('page') == CTRICallLog.static.instrumentLower)
        return;
    buildCallSummaryTable();
});