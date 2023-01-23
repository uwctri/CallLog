(() => {
    let configInit = false;

    // Setup template buttons to show correct settings
    $("body").on('click', ".callConfig input[name^=call_template____]", function () {
        let $tr = $(this).closest('tr');
        $tr.next().nextUntil('.sub_start.sub_parent.repeatable').addBack().hide();
        $tr.nextAll(`tr[field=${$(this).val()}_settings]`).first().nextUntil('tr[class=sub_end]').addBack().show();
    });

    // Setup link buttons to show correct settings for link
    $("body").on('click', ".callConfig input[name^=tab_link____]", function () {
        let $el = $(this).closest('tr').nextUntil('.sub_end');
        $(this).val() == "instrument" ? $el.show() : $el.hide();
    });

    let $modal = $('#external-modules-configure-modal');
    $modal.on('show.bs.modal', function () {

        // Making sure we are overriding this modules's modal only.
        if ($(this).data('module') !== CallLog.prefix) return;

        if (typeof ExternalModules.Settings.prototype.resetConfigInstancesOld === 'undefined')
            ExternalModules.Settings.prototype.resetConfigInstancesOld = ExternalModules.Settings.prototype.resetConfigInstances;

        ExternalModules.Settings.prototype.resetConfigInstances = function () {

            ExternalModules.Settings.prototype.resetConfigInstancesOld();
            if ($modal.data('module') !== CallLog.prefix) return;

            $modal.addClass('callConfig');

            // Remove junk "1" from text field
            $("tr[field$=_text] span").remove();

            // If nothing is selected on the radio options then default to first option
            $modal.find("tr[field=call_template], tr[field=tab_link]").each(function () {
                if ($(this).find('input:checked').length == 0)
                    $(this).find('input').first().click()
            });

            // Correcting weird col issue
            $modal.find(".sub_start").each((_, x) => $(x).find('td').last().attr('colspan', '2'));

            // Below operations run only once
            if (configInit) return;

            configInit = true;
            $modal.find("input[name^=call_template____]:checked").click();
            $modal.find("input[name^=tab_link____]:checked").click();

            // Insert a button to deploy Payemnts form
            $modal.find("[field=intro_text] label").after('<button class="setupCallLog" style="float:right">Deploy Call Long Instruments</button>')
            $modal.find(".setupCallLog").on("click", () => {
                $(".setupCallLog").attr("disabled", true);
                CallLog.em.ajax("deployInstruments", {}).then((response) => {
                    location.reload();
                }).catch((err) => {
                    console.log(err)
                });
            });

            // Hide all the flag fields and set events for them
            $modal.on('change', 'input[name^=tab_calls_included____]', function () {
                $el = $(this);
                let localValues = $el.val().split(',').map(x => x.trim());
                $.each(['followup', 'mcv', 'adhoc'], function (_, template) {
                    let followUpNames = $.makeArray($(`input[name^=call_template____][value=${template}]:checked`).closest('tr').map(function () {
                        return $(this).prevUntil('.sub_start').last().find('input').val()
                    }))
                    $el.closest('tr').nextAll("tr[field=tab_includes_" + template + "]").first().find('input').val('');
                    if (localValues.some(item => followUpNames.includes(item)))
                        $el.closest('tr').nextAll("tr[field=tab_includes_" + template + "]").first().find('input').val('1');
                });
            });

            // Hide Bad Phone section's numbering
            $modal.find('tr[field=bad_phone_collection]').first().nextUntil('.sub_end').addBack().find('span').hide();

            // Rearrange the Withdraw config
            if ($(".withdrawTextLoaded").length == 0) {
                $("tr[field=withdraw_event] td span").first().after(
                    `<span class="withdrawTextLoaded" style="position: absolute;transform: translateY(20px);">Hide all of a subject's calls when this condition is truthy</span>`
                );
            }

            // Rearrange the Temporary Withdraw config
            if ($(".withdrawTmpTextLoaded").length == 0) {
                $("tr[field=withdraw_tmp_event] td span").first().after(
                    `<span class="withdrawTmpTextLoaded" style="position: absolute;transform: translateY(20px);">Hide all of a subject's calls until this date</span>`
                );
            }
        };
    });

    $modal.on('hide.bs.modal', function () {
        // Making sure we are overriding this modules's modal only.
        if ($(this).data('module') !== CallLog.prefix) return;

        $(this).removeClass('callConfig');
        configInit = false;

        if (typeof ExternalModules.Settings.prototype.resetConfigInstancesOld !== 'undefined')
            ExternalModules.Settings.prototype.resetConfigInstances = ExternalModules.Settings.prototype.resetConfigInstancesOld;
    });
})();