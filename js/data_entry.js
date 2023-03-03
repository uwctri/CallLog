(() => {

    const module = ExternalModules.UWMadison.CallLog;

    const isCallLogNext = () => {
        return $(".form_menu_selected").parent().nextAll().filter(function () {
            return $(this).find('a').css('pointer-events') != "none";
        }).first().find('#form\\[call_log\\]').length > 0
    }

    const addGoToCallLogButton = () => {
        if (!isCallLogNext()) return;
        $("#__SUBMITBUTTONS__-div .btn-group").hide();
        let el = $("#__SUBMITBUTTONS__-div #submit-btn-saverecord");
        el.clone(true).off().prop('id', 'goto-call-log').text('Save & Go To Call Log').insertAfter(el);
        el.next().on('click', goToCallLog);
        $("#goto-call-log").before('<br>');
    }

    const modifyRequiredPopup = () => {
        if (!$("#reqPopup").length || !isCallLogNext())
            return;
        if (!$("#reqPopup:visible").length) {
            window.requestAnimationFrame(modifyRequiredPopup);
            return;
        }
        let $btn = $("#reqPopup").parent().find('.ui-dialog-buttonpane button').first();
        $btn.off().on('click', () => {
            window.location.href = $('#form\\[call_log\\]').prop('href');
        });
        $btn.text('Ignore and go to Call Log');
    }

    const goToCallLog = () => {
        appendHiddenInputToForm('save-and-redirect', $('#form\\[call_log\\]').prop('href'));
        dataEntrySubmit('submit-btn-savecontinue');
        return false;
    }

    const formatNavbar = () => {
        let a = `#form\\[${module.static.instrument}\\]`;
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

    formatNavbar();
    addGoToCallLogButton();
    modifyRequiredPopup();
    if (module.recentCaller) {
        $("#questiontable").before(module.templates.callStartedWarning.replace("USERNAME", module.userNameMap[module.recentCaller]));
    }
})();