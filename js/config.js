$(document).ready(function() {
    console.log("Loaded Call Log config")

    if (CallLog.configError) {
        Swal.fire({
            icon: 'error',
            title: 'Call Log config Issue',
            text: 'The Call Log instrument used by the Call Log External Module is either missing or not marked as a repeatable instrument. Please invesitage and resovle.',
        })
    }

    var $modal = $('#external-modules-configure-modal');
    $modal.on('show.bs.modal', function() {
        // Making sure we are overriding this modules's modal only.
        if ($(this).data('module') !== CallLog.modulePrefix)
            return;

        if (typeof ExternalModules.Settings.prototype.resetConfigInstancesOld === 'undefined')
            ExternalModules.Settings.prototype.resetConfigInstancesOld = ExternalModules.Settings.prototype.resetConfigInstances;

        ExternalModules.Settings.prototype.resetConfigInstances = function() {
            ExternalModules.Settings.prototype.resetConfigInstancesOld();
            if ($modal.data('module') !== CallLog.modulePrefix)
                return;

            // Basic cleanup
            $modal.find('thead').remove();
            $modal.find('tr[field=metadata]').hide();
            $modal.find("tr[field$=_settings]").hide();

            // Hide Bade Phone section's numbering
            $modal.find('tr[field=bad_phone_collection]').first().nextUntil('.sub_end').addBack().find('span').hide();

            // Rearrange the Withdraw config
            $modal.find("tr[field=withdraw_var] label").hide();
            $modal.find("tr[field=withdraw_event] td").css({ 'padding-top': '1.5rem', 'padding-bottom': '0.25rem' });
            $modal.find("tr[field=withdraw_var] td").css({ 'border': 'none', 'padding-top': '0.25rem', 'padding-bottom': '1.5rem' });
            if ($(".withdrawTextLoaded").length == 0)
                $("tr[field=withdraw_event] td span").first().after(`<span class="withdrawTextLoaded" style="position: absolute;transform: translateY(20px);">Hide all of a subject's calls when this condition is truthy</span>`);

            // Rearrange the Temporary Withdraw config
            $modal.find("tr[field=withdraw_tmp_var] label").hide();
            $modal.find("tr[field=withdraw_tmp_event] td").css({ 'padding-top': '1.5rem', 'padding-bottom': '0.25rem' });
            $modal.find("tr[field=withdraw_tmp_var] td").css({ 'border': 'none', 'padding-top': '0.25rem', 'padding-bottom': '1.5rem' });
            if ($(".withdrawTmpTextLoaded").length == 0)
                $("tr[field=withdraw_tmp_event] td span").first().after(`<span class="withdrawTmpTextLoaded" style="position: absolute;transform: translateY(20px);">Hide all of a subject's calls until this date</span>`);

            // Rearrange the Followup Anchor config
            $modal.find("tr[field=followup_date] label, tr[field=followup_date] span").hide();
            $modal.find("tr[field=followup_event] td").css({ 'padding-top': '1.5rem', 'padding-bottom': '0.25rem' });
            $modal.find("tr[field=followup_date] td").css({ 'border': 'none', 'padding-top': '0.25rem', 'padding-bottom': '1.5rem' });

            // Rearrange the linked instrument configs for url links
            $modal.find("tr[field=tab_field_link_instrument] label, tr[field=tab_field_link_instrument] span").hide();
            $modal.find("tr[field=tab_field_link_event] td").css({ 'padding-top': '1.5rem', 'padding-bottom': '0.25rem' });
            $modal.find("tr[field=tab_field_link_instrument] td").css({ 'border': 'none', 'padding-top': '0.25rem', 'padding-bottom': '1.5rem' });

            // Setup radio buttons to show correct settings
            $modal.find("input[name^=call_template____]").on('click', function() {
                $(this).closest('tr').nextAll('tr[field=new_settings]').first().nextUntil('.sub_start.sub_parent.repeatable').addBack().hide();
                $(this).closest('tr').nextAll(`tr[field=${$(this).val()}_settings]`).first().nextUntil('tr[class=sub_end]').addBack().show();
            });
            $modal.find("input[name^=call_template____]:checked").click()

            // Setup radio buttons to show correct settings
            $modal.find("input[name^=tab_field_link____]").on('click', function() {
                $(this).closest('tr').nextUntil('.sub_end').hide();
                if ($(this).val() == "instrument")
                    $(this).closest('tr').nextUntil('.sub_end').show();
            });
            $modal.find("input[name^=tab_field_link____]:checked").click()

            // If nothing is selected on the radio options then default to first option
            $modal.find("tr[field=call_template], tr[field=tab_field_link]").each(function() {
                if ($(this).find('input:checked').length == 0)
                    $(this).find('input').first().click()
            });

            // Hide all the flag fields and set events for them
            $("tr[field^=tab_includes_]").hide();
            $("input[name^=tab_calls_included____]").on('change', function() {
                $el = $(this);
                let localValues = $el.val().split(',').map(x => x.trim());
                $.each(['followup', 'mcv', 'adhoc'], function(_, template) {
                    let followUpNames = $.makeArray($("input[name^=call_template____][value=" + template + "]:checked").closest('tr').map(function() {
                        return $(this).prevUntil('.sub_start').last().find('input').val()
                    }))
                    $el.closest('tr').nextAll("tr[field=tab_includes_" + template + "]").first().find('input').val('');
                    if (localValues.some(item => followUpNames.includes(item)))
                        $el.closest('tr').nextAll("tr[field=tab_includes_" + template + "]").first().find('input').val('1');
                });
            });

            // Correcting weird col issue
            $modal.find(".sub_start").each((_, x) => $(x).find('td').last().attr('colspan', '2'));

        };
    });

    $modal.on('hide.bs.modal', function() {
        // Making sure we are overriding this modules's modal only.
        if ($(this).data('module') !== CallLog.modulePrefix)
            return;
        if (typeof ExternalModules.Settings.prototype.resetConfigInstancesOld !== 'undefined')
            ExternalModules.Settings.prototype.resetConfigInstances = ExternalModules.Settings.prototype.resetConfigInstancesOld;
    });
});