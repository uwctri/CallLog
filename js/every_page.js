CallLog.defaultDateFormat = 'MM-dd-y';
CallLog.defaultDateTimeFormat = 'MM-dd-y hh:mma';
CallLog.fn = CallLog.fn || {};
CallLog.css = CallLog.css || {};

function to24hr(t) {
    let isPM = t.includes('P');
    t = t.toLowerCase().replaceAll(/[amp ]/g, '');
    if (!isPM) return t;
    let [h, m] = t.split(':');
    return h == 12 ? t : `${parseInt(h)+12}:${m}`;
}

Date.prototype.addDays = (days) => new Date(this.setDate(this.getDate() + days));

Object.filter = (obj, predicate) =>
    Object.keys(obj)
    .filter(key => predicate(obj[key]))
    .reduce((res, key) => (res[key] = obj[key], res), {});

Object.filterKeys = (obj, allowedKeys) =>
    Object.keys(obj)
    .filter(key => (Array.isArray(allowedKeys) ? allowedKeys : [allowedKeys]).includes(key))
    .reduce((res, key) => {
        res[key] = obj[key];
        return res;
    }, {});

CallLog.fn.isCallLogNext = function() {
    return $(".form_menu_selected").parent().nextAll().filter(function() {
        return $(this).find('a').css('pointer-events') != "none";
    }).first().find('#form\\[call_log\\]').length > 0
}

CallLog.fn.addGoToCallLogButton = function() {
    if (!CallLog.fn.isCallLogNext())
        return;
    $("#formSaveTip .btn-group").addClass('d-none');
    $("#__SUBMITBUTTONS__-div .btn-group").hide();
    let el = $("#__SUBMITBUTTONS__-div #submit-btn-saverecord");
    el.clone(true).off().attr('onclick', 'CallLog.fn.goToCallLog()').prop('id', 'goto-call-log').text('Save & Go To Call Log').insertAfter(el);
    $("#goto-call-log").before('<br>');
}

CallLog.fn.modifyRequiredPopup = function() {
    if (!$("#reqPopup").length || !isCallLogNext())
        return;
    if (!$("#reqPopup:visible").length) {
        window.requestAnimationFrame(CallLog.fn.modifyRequiredPopup);
        return;
    }
    let $btn = $("#reqPopup").parent().find('.ui-dialog-buttonpane button').first();
    $btn.off().on('click', function() {
        window.location.href = $('#form\\[call_log\\]').prop('href');
    });
    $btn.text('Ignore and go to Call Log');
}

CallLog.fn.goToCallLog = function() {
    appendHiddenInputToForm('save-and-redirect', $('#form\\[call_log\\]').prop('href'));
    dataEntrySubmit('submit-btn-savecontinue');
    return false;
}

CallLog.fn.goToCallList = function() {
    appendHiddenInputToForm('save-and-redirect', $("#external_modules_panel a:contains('Call List')").prop('href'));
    dataEntrySubmit('submit-btn-savecontinue');
    return false;
}

CallLog.fn.formatNavForCalls = function() {
    let a = `#form\\[${CallLog.static.instrumentLower}\\]`;
    if ($(a).next().length) {
        $(a).next().hide();
        $(a).prev().prop('href', $(a).next().prop('href'));
        $(a).prop('href', $(a).next().prop('href'));
    } else if ($(a).find('.repeat_event_count_menu').text()) {
        let instance = Number($(a).find('.repeat_event_count_menu').text().replace(/[\\(\\)]/g, '').split('/').pop()) + 1;
        $(a).prop('href', $(a).prop('href').replace(/instance=(.*)/g, 'instance=' + instance));
        $(a).prev().prop('href', $(a).prev().prop('href').replace(/instance=(.*)/g, 'instance=' + instance));
    }
    $(a).prev().find('img').hide().after('<i class="fas fa-phone"></i>')
}

CallLog.fn.loadTemplates = function() {
    CallLog.templates = {};
    $.each($("template[id=CallLog]").prop('content').children, (_, el) =>
        CallLog.templates[$(el).prop('id')] = $(el).prop('outerHTML'));
}

$(document).ready(function() {
    CallLog.fn.loadTemplates();
    CallLog.fn.formatNavForCalls();
    CallLog.fn.addGoToCallLogButton();
    if (CallLog.recentCaller) {
        $("#questiontable").before(CallLog.templates.callStartedWarning.replace("USERNAME", CallLog.userNameMap[CallLog.recentCaller]));
    }
    CallLog.fn.modifyRequiredPopup();
});