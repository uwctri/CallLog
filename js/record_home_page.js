CallLog.link = "";
CallLog.fn.buildCallLogBtn = () => {

    let callStlye = `<a class="CallLogLink"><i class="fa fa-phone"></i></a>`;
    let systemTable = $(`.sysManTable [data-mlm-name=${CallLog.static.instrumentLower}]`).closest('td');
    let redcapTable = $(`#event_grid_table [data-mlm-name=${CallLog.static.instrumentLower}]`).closest('tr');
    
    systemTable.add(redcapTable).find('button, a').each((_, el) => {
        // First instance, deprioritized
        if ($(el).is('a') && !CallLog.link ) {
            CallLog.link = $(el).prop('href');
        }
        // Any other instance
        if ($(el).is('button')) {
            CallLog.link = $(el).attr('onclick').split(`='`)[1].replace(`';`, '');
        }
        // Insert the button
        if ($(".CallLogLink").length < 1) {
            $(el).after(callStlye);
        }
        $(el).hide();
    });
}

$(document).ready(() => {

    // Prep the styled button
    $('head').append(`<style>.CallLogLink { cursor: pointer; }</style>`)
    $("body").on("click", ".CallLogLink", () => { location.href = CallLog.link; });

    // Hide the Call Log repeating instrument table
    $(`th.header:contains(${CallLog.static.instrument})`).closest('table').parent().remove();

    // Replace Call Log icons with the phone
    CallLog.fn.buildCallLogBtn();
});

