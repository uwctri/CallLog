(() => {
    const module = ExternalModules.UWMadison.CallLog;

    const isCallLogNext = () => {
        return $(".rc-form-menu-current").parent().parent().nextAll().filter(function () {
            return $(this).find('a').css('pointer-events') !== "none";
        }).first().attr('data-form') === 'call_log';
    };

    const goToCallLog = () => {
        let href = $('div[data-form=call_log] a').map((i, el) => el.href).filter((i, el) => el !== "javascript:;").get(0);
        if (typeof appendHiddenInputToForm === 'function' && typeof dataEntrySubmit === 'function') {
            appendHiddenInputToForm('save-and-redirect', href);
            dataEntrySubmit('submit-btn-savecontinue');
        } else if (href) {
            window.location.href = href;
        }
        return false;
    };

    const addGoToCallLogButton = () => {
        if (!isCallLogNext()) return;
        $("#__SUBMITBUTTONS__-div .btn-group").hide();
        let el = $("#__SUBMITBUTTONS__-div #submit-btn-saverecord");
        if (el.length && !$("#goto-call-log").length) {
            el.clone(true).off().attr("onclick", "").prop('id', 'goto-call-log').text('Save & Go To Call Log').insertAfter(el);
            $("#goto-call-log").on('click', goToCallLog).before('<br>');
            $("#submit-btn-savenextform").parent().remove();
        }
    };

    const modifyRequiredPopup = () => {
        if (!$("#reqPopup").length || !isCallLogNext()) return;
        if (!$("#reqPopup:visible").length) {
            window.requestAnimationFrame(modifyRequiredPopup);
            return;
        }
        let $btn = $("#reqPopup").parent().find('.ui-dialog-buttonpane button').first();
        $btn.off().on('click', () => {
            let href = $('div[data-form=call_log] a').map((i, el) => el.href).filter((i, el) => el !== "javascript:;").get(0);
            if (href) window.location.href = href;
        });
        $btn.text('Ignore and go to Call Log');
    };

    const formatNavbar = () => {
        let a = `#form\\[${module.static.instrument}\\]`;
        if ($(a).next().length) {
            $(a).next().hide();
            $(a).prev().prop('href', $(a).next().prop('href'));
            $(a).prop('href', $(a).next().prop('href'));
        } else if ($(a).find('.repeat_event_count_menu').text()) {
            let instance = Number($(a).find('.repeat_event_count_menu').text().replace(/[\(\)]/g, '').split('/').pop()) + 1;
            $(a).prop('href', $(a).prop('href').replace(/instance=(.*)/g, 'instance=' + instance));
            $(a).prev().prop('href', $(a).prev().prop('href').replace(/instance=(.*)/g, 'instance=' + instance));
        }
        $(a).prev().find('img').hide().after('<i class="fas fa-phone"></i>');
    };

    formatNavbar();
    addGoToCallLogButton();
    modifyRequiredPopup();

    if (module.recentCaller && module.renderers && module.renderers.renderCallStartedWarning) {
        const callerName = module.userNameMap ? (module.userNameMap[module.recentCaller] || module.recentCaller) : module.recentCaller;
        $("#questiontable").before(
            module.renderers.renderCallStartedWarning(callerName)
        );
    }
})();