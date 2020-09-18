CTRICallLog.defaultDateFormat = 'MM-dd-y';
CTRICallLog.defaultDateTimeFormat = 'MM-dd-y hh:mma';

Object.filter = (obj, predicate) => 
    Object.keys(obj)
          .filter( key => predicate(obj[key]) )
          .reduce( (res, key) => (res[key] = obj[key], res), {} );

function conv24to12(ts) {
    if (!ts) return "";
    let H = +ts.substr(0, 2);
    let h = (H % 12) || 12;
    h = (h < 10)?("0"+h):h;
    return h + ts.substr(2, 3) + (H < 12 ? "am" : "pm");
};

function conv12to24(ts) {
    if (!ts) return "";
    let H = ('0' + (+ts.substr(0, 2) + (ts.includes('PM') ? 12 : 0))).slice(-2);
    return H + ':' + ts.split(' ')[0].split(':')[1] ;
}

function addGoToCallLogButton() {
    let $isCallLogNext = $(".form_menu_selected").parent().nextAll().filter( function() {
        return $(this).find('a').css('pointer-events') != "none";
    }).first().find('#form\\[call_log\\]');
    if ( $isCallLogNext.length == 0 )
        return;
    $("#__SUBMITBUTTONS__-div .btn-group").hide();
    $("#__SUBMITBUTTONS__-div #submit-btn-saverecord").clone(true).off().attr('onclick','goToCallLog()').prop('id','goto-call-log').addClass('ml-1').text('Save & Go To Call Log').insertAfter("#__SUBMITBUTTONS__-div #submit-btn-saverecord");
}

function goToCallLog(){
    appendHiddenInputToForm('save-and-redirect', $('#form\\[call_log\\]').prop('href'));
    dataEntrySubmit('submit-btn-savecontinue');
    return false;
}

function editLeftSideCallLog() {
    let a = `#form\\[${CTRICallLog.static.instrumentLower}\\]`;
    if ( $(a).next().length ) {
        $(a).next().hide();
        $(a).prop('href',$(a).next().prop('href'));
    }
    $(a).prev().find('img').hide().after('<i class="fas fa-phone"></i>')
}

$(document).ready(function () {
    editLeftSideCallLog()
    addGoToCallLogButton();
});