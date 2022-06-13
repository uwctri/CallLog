CallLog.link = "";
CallLog.fn.buildCallLogBtn = () => {

    let callStlye = `<a class="CallLogLink"><i class="fa fa-phone"></i></a>`;
    $(`[data-mlm-name=${CallLog.static.instrumentLower}]`).closest('td').find('button, a').each((_, el) => {
        // First instance
        if ($(el).is('a') && $(el).siblings().length == 1) {
            CallLog.link = $(el).attr('src') || $(el).prop('href');
        }
        // Any other instance
        else if ($(el).is('button')) {
            CallLog.link = $(el).attr('onclick').split(`='`)[1].replace(`';`, '');
        }
        // Insert the button
        if (CallLog.link) {
            $(el).after(callStlye);
        }
        $(el).hide();
    });

    if ($(".CallLogLink").length != 1) {
        requestAnimationFrame(CallLog.fn.buildCallLogBtn);
    }
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

