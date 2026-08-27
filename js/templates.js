(() => {
    const module = ExternalModules.UWMadison.CallLog;

    module.renderers = {
        renderNotesIcon: function() {
            return `<span class="notes-icon-badge text-primary" title="Call notes logged"><i class="fas fa-sticky-note"></i></span>`;
        },
        renderCallStartedIcon: function(startedBy) {
            return `<span class="started-icon-badge text-warning" title="Call in progress by ${startedBy || 'another user'}"><i class="fas fa-phone-volume"></i></span>`;
        },
        renderCallbackMsgIcon: function() {
            return `<span class="callback-msg-badge text-danger" title="Callback requested"><i class="fas fa-bell"></i></span>`;
        },
        renderFormStatus: function(pid, record, formName, status) {
            const statusMap = {
                '0': { color: '#dc3545', title: 'Incomplete', icon: 'circle' },
                '1': { color: '#ffc107', title: 'Unverified', icon: 'adjust' },
                '2': { color: '#198754', title: 'Complete', icon: 'check-circle' }
            };
            const s = statusMap[status] || statusMap['0'];
            const url = `../DataEntry/index.php?pid=${pid}&id=${encodeURIComponent(record)}&page=${formName}`;
            return `<a href="${url}" class="form-status-link" title="${s.title}"><i class="fas fa-${s.icon}" style="color: ${s.color};"></i></a>`;
        }
    };
})();