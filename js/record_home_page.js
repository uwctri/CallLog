$(document).ready(function () {
    // Prep the styled button
    let callStlye = `<a class="CallLogLink"><i class="fa fa-phone"></i></a>`;
    $('head').append(`<style>.CallLogLink { cursor: pointer; }</style>`)
    
    // Replace Call Log icons with the phone
    $(`#event_grid_table td:contains(${CallLog.static.instrument})`).parent().find('button, a').each( function() {
        let src = "";
        // First instance
        if ( $(this).is('a') && $(this).siblings().length == 0 ) {
            src = $(this).attr('src') || $(this).prop('href');
        }
        // Any other instance
        else if ( $(this).is('button') ) {
            src = $(this).attr('onclick').split(`='`)[1].replace(`';`,'');
        }
        // Insert the button and make it work
        if ( src ) {
            $(this).after(callStlye);
            $(this).parent().find('.CallLogLink').on('click', () => {location.href = src;});
        }
        $(this).hide();
    });
    
    // Handle the CTRI Study Managment Table too
    let src = "";
    let c = $(`#systemManagementTable td:contains(${CallLog.static.instrument})`);
    if (c) {
        $(c).find('a, button').hide();
        $(c).find('a').after(callStlye);
        if ( $(c).find('button').length ) {
            src = $(c).find('button').attr('onclick').split(`='`)[1].replace(`';`,'');
        } else {
            src = $(c).find('a').attr('src') || $(c).find('a').prop('href');
        }
        $(c).find('.CallLogLink').on('click', () => {location.href = src;});
    }
    
    // Hide the Call Log repeating instrument table
    $(`th.header:contains(${CallLog.static.instrument})`).closest('table').parent().remove();
});