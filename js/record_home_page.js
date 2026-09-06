(() => {
    let callLogLink = "";
    const module = ExternalModules.UWMadison.CallLog || {};
    const staticData = module.static || {};
    const workflow = module.workflow || {
        showRecordHomeButton: true,
        showCallLogInstrument: false,
        showMetadataInstrument: false
    };

    const callInst = staticData.instrument || 'call_log';
    const metaInst = staticData.instrumentMeta || 'call_log_metadata';
    const callIcon = `<a class="CallLogLink" title="Go to Call Log"><i class="fa fa-phone"></i></a>`;

    const systemTable = $(`.sysManTable [data-mlm-name=${callInst}]`).closest('td');
    const redcapTable = $(`#event_grid_table [data-mlm-name=${callInst}]`).closest('tr');

    if (!document.getElementById('callLogLinkStyle')) {
        const style = document.createElement('style');
        style.id = 'callLogLinkStyle';
        style.textContent = `
            .CallLogLink { cursor: pointer; margin-left: 4px; }
            #recordHomeCallLogBtn { font-weight: 500; display: inline-flex; align-items: center; gap: 6px; vertical-align: middle; cursor: pointer; }
        `;
        document.head.appendChild(style);
    }

    $(`#repeat_instrument_table-${staticData.instrumentEvent}-${callInst}`).parent().remove();

    // Scan for existing callLogLink from REDCap buttons or links
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

        if (workflow.showCallLogInstrument) {
            if ($(".CallLogLink").length < 1) {
                $(el).after(callIcon);
            }
            $(el).hide();
        }
    });

    // Fallback URL if callLogLink was not found from the DOM
    if (!callLogLink) {
        const urlParams = new URLSearchParams(window.location.search);
        const pid = urlParams.get('pid') || '';
        const recordId = urlParams.get('id') || '';
        const arm = urlParams.get('arm') || '';
        const eventId = staticData.instrumentEvent || '';
        const webroot = window.app_path_webroot || '';
        callLogLink = `${webroot}DataEntry/index.php?pid=${pid}&id=${encodeURIComponent(recordId)}&page=${callInst}&event_id=${eventId}${arm ? `&arm=${arm}` : ''}&instance=1`;
    }

    // Hide or Show Call Log instrument based on workflow setting
    if (!workflow.showCallLogInstrument) {
        systemTable.hide();
        redcapTable.hide();
        $(`.rc-form-menu-item[data-form="${callInst}"]`).hide();
        $(`div.formMenuList:has(a[id="form[${callInst}]"])`).hide();
        $(`div.formMenuList:has(a[href*="page=${callInst}"])`).hide();
    }

    // Hide Metadata instrument if workflow setting is disabled
    if (!workflow.showMetadataInstrument) {
        $(`.sysManTable [data-mlm-name=${metaInst}]`).closest('td').hide();
        $(`#event_grid_table [data-mlm-name=${metaInst}]`).closest('tr').hide();
        $(`.rc-form-menu-item[data-form="${metaInst}"]`).hide();
        $(`div.formMenuList:has(a[id="form[${metaInst}]"])`).hide();
        $(`div.formMenuList:has(a[href*="page=${metaInst}"])`).hide();
    }

    // Handle Call Log Button on Record Home Page
    if (workflow.showRecordHomeButton) {
        const btnHtml = `<button type="button" id="recordHomeCallLogBtn" class="btn btn-sm btn-primary ms-2" title="Go to Call Log for this participant">
            <i class="fas fa-phone"></i> Call Log
        </button>`;

        if ($('#recordActionDropdownTrigger').length) {
            if ($('#recordHomeCallLogBtn').length === 0) {
                $('#recordActionDropdownTrigger').after(btnHtml);
            }
        } else if ($('#record_display_name').length) {
            if ($('#recordHomeCallLogBtn').length === 0) {
                $('#record_display_name').append(`<span class="ms-3">${btnHtml}</span>`);
            }
        } else if ($('#event_grid_table').length) {
            if ($('#recordHomeCallLogBtn').length === 0) {
                $('#event_grid_table').before(`<div class="mb-2">${btnHtml}</div>`);
            }
        }
    }

    // Click handlers
    $("body").off('click.callLogLink').on("click.callLogLink", ".CallLogLink", (e) => {
        e.preventDefault();
        if (callLogLink) location.href = callLogLink;
    });

    $("body").off('click.recordHomeCallLogBtn').on("click.recordHomeCallLogBtn", "#recordHomeCallLogBtn", (e) => {
        e.preventDefault();
        if (callLogLink) location.href = callLogLink;
    });
})();
