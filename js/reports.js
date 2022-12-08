(() => {
    let data = ExternalModules.UWMadison.CallLog.config;
    for (const record_id in data) {
        let local = data[record_id]['metadata'];
        data[record_id]['open_calls'] ??= 0;
        for (const call_name in local) {
            if (local[call_name]['complete']) continue;
            if (local[call_name]['start'] > today) continue;
            data[record_id]['open_calls'] += 1;
        }
    }

    $(".container table").DataTable({
        data: Object.values(data),
        order: [[2, 'desc']],
        pageLength: 20,
        lengthMenu: [
            [20, 50, 100, -1],
            [20, 50, 100, 'All'],
        ],
        columns: [
            {
                title: 'Record ID',
                data: 'id',
                render: (data, type, row) => {
                    return type == "display" ? `<a href=../DataEntry/record_home.php?pid=${pid}&id=${data}>${data}</a>` : data;
                }
            },
            {
                title: 'Label',
                data: 'label'
            },
            {
                title: 'Open Calls',
                data: 'open_calls'
            }
        ]
    });

})();