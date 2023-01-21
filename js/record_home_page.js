
$(document).ready(() => {

    let callLogLink = "";
    const callIcon = `<a class="CallLogLink"><i class="fa fa-phone"></i></a>`;
    const systemTable = $(`.sysManTable [data-mlm-name=${CallLog.static.instrument}]`).closest('td');
    const redcapTable = $(`#event_grid_table [data-mlm-name=${CallLog.static.instrument}]`).closest('tr');

    // Prep the styled button
    $('head').append(`<style>.CallLogLink { cursor: pointer; }</style>`)
    $("body").on("click", ".CallLogLink", () => { location.href = callLogLink; });

    // Hide the Call Log repeating instrument table
    $(`#repeat_instrument_table-${CallLog.static.instrumentEvent}-${CallLog.static.instrument}`).parent().remove();

    // Replace Call Log icons with the phone
    systemTable.add(redcapTable).find('button, a').each((_, el) => {
        if ($(el).hasClass("invis")) {
            return;
        }
        // First instance, deprioritized
        if ($(el).is('a') && !callLogLink) {
            callLogLink = $(el).prop('href');
        }
        // Any other instance
        if ($(el).is('button')) {
            callLogLink = $(el).attr('onclick').split(`='`)[1].replace(`';`, '');
        }
        // Insert the button
        if ($(".CallLogLink").length < 1) {
            $(el).after(callIcon);
        }
        $(el).hide();
    });

});

