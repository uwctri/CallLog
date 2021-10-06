CallLog.defaultDateFormat = 'MM-dd-y';
CallLog.defaultDateTimeFormat = 'MM-dd-y hh:mma';

function ArraysEqual(a1, a2) {
    var i = a1.length;
    while (i--) {
        if (a1[i] !== a2[i]) return false;
    }
    return true
}

Date.prototype.addDays = (days) => new Date( this.setDate(this.getDate() + days) );

Object.filter = (obj, predicate) => 
    Object.keys(obj)
          .filter( key => predicate(obj[key]) )
          .reduce( (res, key) => (res[key] = obj[key], res), {} );
          
Object.filterKeys = (obj, allowedKeys) =>
    Object.keys(obj)
        .filter(key => (Array.isArray(allowedKeys) ? allowedKeys : [allowedKeys]).includes(key))
        .reduce((res, key) => {
                res[key] = obj[key];
                return res; }, {});

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

function isCallLogNext() {
    return $(".form_menu_selected").parent().nextAll().filter( function() {
        return $(this).find('a').css('pointer-events') != "none";
    }).first().find('#form\\[call_log\\]').length > 0
}

function addGoToCallLogButton() {
    if ( !isCallLogNext() )
        return;
    setInterval(function() {
        $("#formSaveTip .btn-group").hide();
    }, 100);
    $("#__SUBMITBUTTONS__-div .btn-group").hide();
    $("#__SUBMITBUTTONS__-div #submit-btn-saverecord").clone(true).off().attr('onclick','goToCallLog()').prop('id','goto-call-log').text('Save & Go To Call Log').insertAfter("#__SUBMITBUTTONS__-div #submit-btn-saverecord");
    $("#goto-call-log").before('<br>');
}

function hijackRequiredPopup() {
    if ( !$("#reqPopup").length || !isCallLogNext() )
        return;
    if ( !$("#reqPopup:visible").length ) {
        window.requestAnimationFrame(hijackRequiredPopup);
        return;
    }
    let $btn = $("#reqPopup").parent().find('.ui-dialog-buttonpane button').first();
    $btn.off().on('click', function() {
        window.location.href = $('#form\\[call_log\\]').prop('href');
    });
    $btn.text('Ignore and go to Call Log');
}

function goToCallLog() {
    appendHiddenInputToForm('save-and-redirect', $('#form\\[call_log\\]').prop('href'));
    dataEntrySubmit('submit-btn-savecontinue');
    return false;
}

function goToCallList() {
    appendHiddenInputToForm('save-and-redirect', $("#external_modules_panel a:contains('Call List')").prop('href'));
    dataEntrySubmit('submit-btn-savecontinue');
    return false;
}

function editLeftSideCallLog() {
    let a = `#form\\[${CallLog.static.instrumentLower}\\]`;
    if ( $(a).next().length ) {
        $(a).next().hide();
        $(a).prev().prop('href',$(a).next().prop('href'));
        $(a).prop('href',$(a).next().prop('href'));
    } else if ( $(a).find('.repeat_event_count_menu').text() ) {
        let instance = Number($(a).find('.repeat_event_count_menu').text().replace(/[\\(\\)]/g,'').split('/').pop())+1;
        $(a).prop('href',$(a).prop('href').replace(/instance=(.*)/g,'instance='+instance));
        $(a).prev().prop('href', $(a).prev().prop('href').replace(/instance=(.*)/g,'instance='+instance));
    }
    $(a).prev().find('img').hide().after('<i class="fas fa-phone"></i>')
}

function showCallStartWarning() {
    if ( !CallLog.recentCaller )
        return
    $("#questiontable").before(`
    <div class="alert alert-danger" style="text-align:center" role="alert">
        <br>
        <div class="row">
            <div class="col-1"><i class="fas fa-exclamation-triangle h2 mt-1"></i></div>
            <div class="col-10 h6">
                This subject's record was recently opened from the Call List by ${CallLog.userNameMap[CallLog.recentCaller]}.
                <br>
                They may currently be on the phone with the subject.
            </div>
            <div class="col-1"><i class="fas fa-exclamation-triangle h2 mt-1"></i></div>
        </div>
        <br>
    </div>`);
}

$(document).ready(function () {
    editLeftSideCallLog()
    addGoToCallLogButton();
    showCallStartWarning();
    hijackRequiredPopup();
});