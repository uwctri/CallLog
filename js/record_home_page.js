(() => {
    let callLogLink = "";
    const module = ExternalModules.UWMadison.CallLog;
    const callIcon = `<a class="CallLogLink" title="Go to Call Log"><i class="fa fa-phone"></i></a>`;

    const systemTable = $(`.sysManTable [data-mlm-name=${module.static.instrument}]`).closest('td');
    const redcapTable = $(`#event_grid_table [data-mlm-name=${module.static.instrument}]`).closest('tr');

    if (!document.getElementById('callLogLinkStyle')) {
        const style = document.createElement('style');
        style.id = 'callLogLinkStyle';
        style.textContent = '.CallLogLink { cursor: pointer; margin-left: 4px; }';
        document.head.appendChild(style);
    }

    $("body").off('click.callLogLink').on("click.callLogLink", ".CallLogLink", () => {
        if (callLogLink) location.href = callLogLink;
    });

    $(`#repeat_instrument_table-${module.static.instrumentEvent}-${module.static.instrument}`).parent().remove();

    systemTable.add(redcapTable).find('button, a').each((_, el) => {
        if ($(el).hasClass("invis")) return;

        if ($(el).is('a') && !callLogLink) {
            callLogLink = $(el).prop('href');
        }

        if ($(el).is('button') && $(el).attr('onclick')) {
            const parts = $(el).attr('onclick').split(`='`);
            if (parts.length > 1) {
                callLogLink = parts[1].replace(`';`, '');
            }
        }

        if ($(".CallLogLink").length < 1) {
            $(el).after(callIcon);
        }
        $(el).hide();
    });
})();
