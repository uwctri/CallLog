$(document).ready(function () {
    $('head').append(`
    <style>
        .CallLogLink { cursor: pointer; }
    </style>
    `)
    
    // Replace Call Log icons with the phone
    $(`#event_grid_table td:contains(${CTRICallLog.static.instrument})`).parent().find('button, a').each( function() {
        let src = "";
        // First instance
        if ( $(this).is('a') && $(this).siblings().length == 0 )
            src = $(this).attr('src') || $(this).prop('href');
        // Any other instance
        else if ( $(this).is('button') )
            src = $(this).attr('onclick').split(`='`)[1].replace(`';`,'');
        // Insert the button and make it work
        if ( src ) {
            $(this).after(`<a class="CallLogLink"><i class="fa fa-phone"></i></a>`);
            $(this).parent().find('.CallLogLink').on('click', function() {
                location.href = src;
            });
        }
        $(this).hide();
    });
    
    // Handle the CTRI System Man Table too
    let src = "";
    let c = $(`#systemManagementTable td:contains(${CTRICallLog.static.instrument})`);
    if (c) {
        $(c).find('a, button').hide();
        $(c).find('a').after(`<a class="CallLogLink"><i class="fa fa-phone"></i></a>`);
        if ( $(c).find('button').length ) 
            src = $(c).find('button').attr('onclick').split(`='`)[1].replace(`';`,'');
        else
            src = $(c).find('a').attr('src') || $(c).find('a').prop('href');
        $(c).find('.CallLogLink').on('click', function() {
            location.href = src;
        });
    }
    
    // Hide the Call Log repeating instrument table table
    $(`th.header:contains(${CTRICallLog.static.instrument})`).closest('table').parent().remove();
});