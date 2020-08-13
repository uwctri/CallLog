$(document).ready(function() {
    console.log("Loaded CTRI Call Log config")
    var $modal = $('#external-modules-configure-modal');
    $modal.on('show.bs.modal', function() {
        // Making sure we are overriding this modules's modal only.
        if ($(this).data('module') !== CTRICallLog.modulePrefix)
            return;
    
        if (typeof ExternalModules.Settings.prototype.resetConfigInstancesOld === 'undefined')
            ExternalModules.Settings.prototype.resetConfigInstancesOld = ExternalModules.Settings.prototype.resetConfigInstances;

        ExternalModules.Settings.prototype.resetConfigInstances = function() {
            ExternalModules.Settings.prototype.resetConfigInstancesOld();
            
            if ($modal.data('module') !== CTRICallLog.modulePrefix)
                return;
            
            // Basic cleanup
            $modal.find('thead').remove();
            $modal.find('tr[field=metadata]').hide();
            $modal.find("tr[field$=_settings]").hide();
            
            // Hide Bade Phone section's numbering
            $('tr[field=bad_phone_collection]').first().nextUntil('.sub_end').addBack().find('span').hide();
            
            // Rearrange the Withdraw config
            $("tr[field=withdraw_var] label").hide();
            $("tr[field=withdraw_event] td").css('padding-top','1.5rem').css('padding-bottom','.25rem');
            $("tr[field=withdraw_var] td").css('border','none').css('padding-top','.25rem').css('padding-bottom','1.5rem');
            if ( $(".withdrawTextLoaded").length == 0 )
                $("tr[field=withdraw_event] td span").first().after(`<span class="withdrawTextLoaded" style="position: absolute;transform: translateY(20px);">Hide all of a subject's calls when this condition is truthy</span>`);
            
            // Rearrange the Temporary Withdraw config
            $("tr[field=withdraw_tmp_var] label").hide();
            $("tr[field=withdraw_tmp_event] td").css('padding-top','1.5rem').css('padding-bottom','.25rem');
            $("tr[field=withdraw_tmp_var] td").css('border','none').css('padding-top','.25rem').css('padding-bottom','1.5rem');
            if ( $(".withdrawTmpTextLoaded").length == 0 )
                $("tr[field=withdraw_tmp_event] td span").first().after(`<span class="withdrawTmpTextLoaded" style="position: absolute;transform: translateY(20px);">Hide all of a subject's calls until this date</span>`);
            
            // Setup radio buttons to show correct settings
            $modal.find("input[name^=call_template____]").on('click', function () {
                $(this).closest('tr').nextAll('tr[field=new_settings]').first().nextUntil('tr[class=sub_end]').addBack().hide();
                $(this).closest('tr').nextAll('tr[field=reminder_settings]').first().nextUntil('tr[class=sub_end]').addBack().hide();
                $(this).closest('tr').nextAll('tr[field=followup_settings]').first().nextUntil('tr[class=sub_end]').addBack().hide();
                $(this).closest('tr').nextAll('tr[field=mcv_settings]').first().nextUntil('tr[class=sub_end]').addBack().hide();
                $(this).closest('tr').nextAll(`tr[field=${$(this).val()}_settings]`).first().nextUntil('tr[class=sub_end]').addBack().show();
            });
            $modal.find("input[name^=call_template____]:checked").click()
            
            // If nothing is selected on the radio options then default to first option
            $modal.find("tr[field=call_template], tr[field=tab_field_link]").each( function() {
                if ( $(this).find('input:checked').length == 0 )
                    $(this).find('input').first().click()
            });
            
        };
    });

    $modal.on('hide.bs.modal', function() {
        // Making sure we are overriding this modules's modal only.
        if ($(this).data('module') !== CTRICallLog.modulePrefix)
            return;

        if (typeof ExternalModules.Settings.prototype.resetConfigInstancesOld !== 'undefined')
            ExternalModules.Settings.prototype.resetConfigInstances = ExternalModules.Settings.prototype.resetConfigInstancesOld;

        $modal.removeClass('defaultFormStatusConfig');
    });
});