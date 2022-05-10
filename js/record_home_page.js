$(document).ready(function() {

    // Prep the styled button
    let callStlye = `<a class="CallLogLink"><i class="fa fa-phone"></i></a>`;
    $('head').append(`<style>.CallLogLink { cursor: pointer; }</style>`)
    CallLog.link = "";
    $("body").on("click",".CallLogLink", () => { location.href = CallLog.link; });

    // Replace Call Log icons with the phone
    $(`#event_grid_table [data-mlm-name=${CallLog.static.instrumentLower}]`).closest('tr').find('button, a').each(function() {
        // First instance
        if ($(this).is('a') && $(this).siblings().length == 0) {
            CallLog.link = $(this).attr('src') || $(this).prop('href');
        }
        // Any other instance
        else if ($(this).is('button')) {
            CallLog.link = $(this).attr('onclick').split(`='`)[1].replace(`';`, '');
        }
        // Insert the button
        if (CallLog.link) {
            $(this).after(callStlye);
        }
        $(this).hide();
    });
    
    // Hide the Call Log repeating instrument table
    $(`th.header:contains(${CallLog.static.instrument})`).closest('table').parent().remove();
});