(() => {
    let data = ExternalModules.UWMadison.CallLog.reportConfig;

    let structure = [
        {
            title: 'Record ID',
            data: '_id',
            render: (data, type, row) => {
                return type == "display" ? `<a href=../DataEntry/record_home.php?pid=${pid}&id=${data}>${data}</a>` : data;
            }
        },
        {
            title: 'Label',
            data: '_label'
        }
    ];

    for (const field in data['_cols']) {
        if (!field) continue;
        structure.push({
            title: data['_cols'][field]['name'],
            data: field
        });
    }

    for (const record_id in data) {
        if (record_id.startsWith('_')) continue;

        // Setup metadata stuff
        let meta = data[record_id]['metadata'];
        data[record_id]['_open_calls'] ??= 0;
        for (const call_name in meta) {
            if (!call_name) continue;
            if (meta[call_name]['complete']) continue;
            if (meta[call_name]['start'] > today) continue;
            if (meta[call_name]['end'] < today) continue;
            data[record_id]['_open_calls'] += 1;
        }
        delete data[record_id]['metadata'];

        // Extra cols added by user (map values)
        for (const field in data[record_id]) {
            if (field.startsWith('_')) continue;
            let val = data[record_id][field];
            if (val in data['_cols'][field]['map']) {
                data[record_id][field] = data['_cols'][field]['map'][val];
            }
        }
    }

    delete data['_cols'];
    const formated_data = Object.values(data)

    $(".container table").DataTable({
        data: formated_data,
        order: [[structure.length, 'desc']],
        pageLength: 20,
        lengthMenu: [
            [20, 50, 100, -1],
            [20, 50, 100, 'All'],
        ],
        columns: [...structure, {
            title: 'Open Calls',
            data: '_open_calls'
        }]
    });

})();