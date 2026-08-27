(() => {
    const module = ExternalModules.UWMadison.CallLog;

    const injectNoticeStyles = () => {
        if (document.getElementById('callLogConfigNoticeStyle')) return;
        const style = document.createElement('style');
        style.id = 'callLogConfigNoticeStyle';
        style.textContent = `
            .callLogCustomConfigNotice {
                border-radius: 6px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            }
            .callLogCustomConfigNotice a.btn-primary {
                color: #ffffff !important;
            }
        `;
        document.head.appendChild(style);
    };

    const $modal = $('#external-modules-configure-modal');
    $modal.on('show.bs.modal', function () {
        if ($(this).data('module') !== module.prefix) return;

        injectNoticeStyles();

        if (typeof ExternalModules.Settings.prototype.resetConfigInstancesOld === 'undefined') {
            ExternalModules.Settings.prototype.resetConfigInstancesOld = ExternalModules.Settings.prototype.resetConfigInstances;
        }

        ExternalModules.Settings.prototype.resetConfigInstances = function () {
            ExternalModules.Settings.prototype.resetConfigInstancesOld();
            if ($modal.data('module') !== module.prefix) return;

            $modal.addClass('callConfig');

            if (!$modal.find('.callLogCustomConfigNotice').length) {
                const configUrl = `/redcap_v${redcap_version}/ExternalModules/?prefix=${module.prefix}&page=config&pid=${pid}`;
                const noticeHtml = `
                    <div class="callLogCustomConfigNotice alert alert-info m-3" role="alert">
                        <h5><i class="fas fa-cog mr-2"></i> Custom Configuration Interface Available</h5>
                        <p class="mb-2">For faster performance and an improved layout, use the dedicated <strong>Call Log Settings</strong> page to configure call types, tabs, and display rules.</p>
                        <a href="${configUrl}" class="btn btn-sm btn-primary text-white">
                            <i class="fas fa-external-link-alt mr-1"></i> Open Call Log Settings Page
                        </a>
                    </div>
                `;
                $modal.find('.modal-body').prepend(noticeHtml);
            }
        };
    });

    $modal.on('hide.bs.modal', function () {
        if ($(this).data('module') !== module.prefix) return;
        $(this).removeClass('callConfig');

        if (typeof ExternalModules.Settings.prototype.resetConfigInstancesOld !== 'undefined') {
            ExternalModules.Settings.prototype.resetConfigInstances = ExternalModules.Settings.prototype.resetConfigInstancesOld;
        }
    });
})();