(() => {
    const module = ExternalModules.UWMadison.CallLog;

    const renderers = {
        renderNotesIcon: function (tooltip) {
            const title = tooltip || "Call notes logged";
            return `<span class="notes-icon-badge text-primary" title="${title}"><i class="fas fa-sticky-note"></i></span>`;
        },
        renderCallStartedIcon: function (startedBy, tooltip) {
            const title = tooltip || (startedBy ? `On a call (${startedBy})` : 'On a call');
            return `<span class="badge-call-ongoing text-white bg-danger rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 20px; height: 20px;" title="${title}"><i class="fas fa-phone" style="font-size: 10px;"></i></span>`;
        },
        renderMultiTabIcon: function (otherTabs, tooltip) {
            const tabsList = (Array.isArray(otherTabs) && otherTabs.length) ? otherTabs.join(', ') : '';
            const title = tooltip || (tabsList ? `Record is on multiple tabs: ${tabsList}` : 'Record is on multiple tabs');
            return `<span class="badge-multi-tab text-secondary" title="${title}"><i class="fas fa-users"></i></span>`;
        },
        renderCallbackMsgIcon: function (tooltip) {
            const title = tooltip || "Callback requested";
            return `<span class="callback-msg-badge text-danger" title="${title}"><i class="fas fa-bell"></i></span>`;
        },
        renderFormStatus: function (pid, record, formName, status) {
            const statusMap = {
                '0': { color: '#dc3545', title: 'Incomplete', icon: 'circle' },
                '1': { color: '#ffc107', title: 'Unverified', icon: 'adjust' },
                '2': { color: '#198754', title: 'Complete', icon: 'check-circle' }
            };
            const s = statusMap[status] || statusMap['0'];
            const url = `../DataEntry/index.php?pid=${pid}&id=${encodeURIComponent(record)}&page=${formName}`;
            return `<a href="${url}" class="form-status-link" title="${s.title}"><i class="fas fa-${s.icon}" style="color: ${s.color};"></i></a>`;
        },
        renderNotesEntry: function () {
            return `<tr id="call_notes_custom-tr">
                <td colspan="2" class="p-2 border-0">
                    <div class="call-notes-grid">
                        <div class="call-notes-col">
                            <label class="form-label small fw-semibold text-muted mb-1 d-flex align-items-center">
                                <i class="fas fa-history me-1"></i> Previous Call Notes
                            </label>
                            <textarea class="form-control form-control-sm notesOld bg-light" readonly placeholder="No previous notes recorded for this call type."></textarea>
                        </div>
                        <div class="call-notes-col">
                            <label class="form-label small fw-semibold text-dark mb-1 d-flex align-items-center">
                                <i class="fas fa-pen me-1 text-primary"></i> Current Call Notes
                            </label>
                            <textarea class="form-control form-control-sm notesNew" placeholder="Enter notes for this call..."></textarea>
                        </div>
                    </div>
                </td>
            </tr>`;
        },
        renderHistoricDisplay: function () {
            return `<tr class="bg-warning bg-opacity-10 border-warning border-start border-4">
                <td colspan="2" class="p-3">
                    <div class="d-flex align-items-center text-dark">
                        <i class="fas fa-history text-warning me-2 fs-5"></i>
                        <div>
                            <strong>Completed Call Record</strong> — This call log has been marked complete and is displayed in read-only mode.
                        </div>
                    </div>
                </td>
            </tr>`;
        },
        renderCallWrapper: function () {
            return `<tr id="call_log_wrapper-tr">
                <td colspan="2" class="p-0">
                    <div class="call-tabs-bar d-flex flex-wrap align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <ul class="nav nav-pills card-header-tabs m-0 call-tabs-nav p-0 border-0 gap-2"></ul>
                        </div>
                        <div class="d-flex align-items-center gap-2 call-adhoc-actions ms-auto"></div>
                    </div>
                </td>
            </tr>`;
        },
        renderCallLogTab: function (callID, name, isComplete) {
            const icon = isComplete ? '<i class="fas fa-check-circle text-success me-2 mr-2"></i>' : '<i class="fas fa-phone-alt me-2 mr-2 text-secondary opacity-75"></i>';
            return `<li class="nav-item">
                <button type="button" class="callTab custom-nav-pill cursor-pointer" data-call-id="${callID}">
                    ${icon} <span>${name}</span>
                </button>
            </li>`;
        },
        renderNoCallsDisplay: function () {
            return `<tr>
                <td colspan="2" class="p-4 text-center text-muted">
                    <i class="fas fa-check-circle text-success fs-3 mb-2"></i>
                    <p class="mb-0 fw-semibold">No calls are currently scheduled or due for this participant.</p>
                </td>
            </tr>`;
        },
        renderAdhocBtn: function (label) {
            const btnText = label || 'New Adhoc Call';
            return `<button type="button" class="btn btn-outline-primary btn-sm adhocButton shadow-xs py-1 px-2.5" data-bs-toggle="modal" data-bs-target="#adhocModal" data-toggle="modal" data-target="#adhocModal">
                <i class="fas fa-plus me-1"></i> ${btnText}
            </button>`;
        },
        renderAdhocModal: function (hasMultipleTypes, title) {
            const modalTitle = title || 'New Adhoc Call';
            return `<div class="modal fade" id="adhocModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-primary text-white py-2 px-3">
                            <h5 class="modal-title fs-6 fw-bold d-flex align-items-center">
                                <i class="fas fa-phone-volume me-2"></i> ${modalTitle}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-3">
                            <div class="mb-3" id="adhocCallTypeGroup" style="${hasMultipleTypes ? '' : 'display: none;'}">
                                <label class="form-label small fw-bold text-dark">Call Type</label>
                                <select class="form-select form-select-sm" name="callType"></select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Reason</label>
                                <select class="form-select form-select-sm" name="reason"></select>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-dark">Date</label>
                                    <input type="text" class="form-control form-control-sm" name="callDate" placeholder="YYYY-MM-DD">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold text-dark">Time</label>
                                    <input type="text" class="form-control form-control-sm" name="callTime" placeholder="HH:MM">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-bold text-dark">Notes</label>
                                <textarea class="form-control form-control-sm" name="notes" rows="3" placeholder="Optional call notes..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer py-2 px-3 bg-light">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm callModalSave">
                                <i class="fas fa-save me-1"></i> Save Call
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
        },
        renderCallStartedWarning: function (callerName) {
            return `<div class="alert alert-warning d-flex align-items-center mb-3 shadow-xs" role="alert">
                <i class="fas fa-exclamation-triangle me-2 fs-5 text-warning"></i>
                <div>
                    <strong>Notice:</strong> Staff member <strong>${callerName || 'another user'}</strong> is currently logging a call for this participant.
                </div>
            </div>`;
        },
        renderCallHistoryTable: function () {
            return `<div class="callHistoryContainer card border shadow-xs mb-4">
                <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small"><i class="fas fa-history me-1.5 text-primary"></i> Participant Call History</span>
                    <button type="button" class="btn btn-sm btn-link text-muted p-0 callSummarySettings" title="Call Settings"><i class="fas fa-cog"></i></button>
                </div>
                <div class="card-body p-2 callHistoryBody">
                    <table class="table table-sm table-hover callSummaryTable w-100 mb-0"></table>
                </div>
            </div>`;
        },
        renderCallHistoryEmpty: function () {
            return `<div class="callHistoryContainer card border shadow-xs mb-4">
                <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small"><i class="fas fa-history me-1.5 text-primary"></i> Participant Call History</span>
                    <button type="button" class="btn btn-sm btn-link text-muted p-0 callSummarySettings" title="Call Settings"><i class="fas fa-cog"></i></button>
                </div>
                <div class="card-body p-3 text-center text-muted small">
                    <i class="fas fa-phone-slash text-secondary opacity-50 fs-4 mb-2 d-block"></i>
                    No call history recorded for this participant yet.
                </div>
            </div>`;
        },
        renderCallHistorySettings: function () {
            return `<div class="text-start mb-2">
                <p class="small text-muted mb-2">Manage completion status for scheduled call types for this participant:</p>
            </div><div class="call-metadata-card">`;
        },
        renderCallHistoryRow: function (name, callId, isComplete) {
            return `<label class="call-metadata-item" for="meta_${callId}">
                <div class="d-flex align-items-center flex-grow-1">
                    <input class="form-check-input callMetadataEdit me-2" type="checkbox" data-call="${callId}" id="meta_${callId}" ${isComplete ? 'checked' : ''}>
                    <span class="call-name-label">${name}</span>
                </div>
                <span class="call-id-tag">${callId}</span>
            </label>`;
        },
        renderCallClosed: function () {
            return `<span class="badge bg-success ms-2"><i class="fas fa-check me-1"></i> Log Closed</span>`;
        },
        renderDeleteLog: function () {
            return `<button type="button" class="btn btn-outline-danger btn-xs deleteInstance" title="Delete Call Log Instance"><i class="fas fa-trash-alt"></i></button>`;
        },
        renderSettingsButton: function () {
            return `<button type="button" class="btn btn-sm btn-link text-muted p-0 callSummarySettings" title="Call Settings"><i class="fas fa-cog"></i></button>`;
        }
    };

    module.renderers = renderers;

    document.addEventListener('alpine:init', () => {
        if (typeof Alpine !== 'undefined') {
            Alpine.store('templates', renderers);
        }
    });
})();