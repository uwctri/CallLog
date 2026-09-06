(() => {
    const module = ExternalModules.UWMadison.CallLog;

    const renderers = {
        renderNotesIcon: function () {
            return `<span class="notes-icon-badge text-primary" title="Call notes logged"><i class="fas fa-sticky-note"></i></span>`;
        },
        renderCallStartedIcon: function (startedBy) {
            const title = startedBy ? `On a call (${startedBy})` : 'On a call';
            return `<span class="badge-call-ongoing text-white bg-danger rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 20px; height: 20px;" title="${title}"><i class="fas fa-phone" style="font-size: 10px;"></i></span>`;
        },
        renderMultiTabIcon: function (otherTabs) {
            const tabsList = (Array.isArray(otherTabs) && otherTabs.length) ? otherTabs.join(', ') : '';
            const title = tabsList ? `Record is on multiple tabs: ${tabsList}` : 'Record is on multiple tabs';
            return `<span class="badge-multi-tab text-secondary" title="${title}"><i class="fas fa-users"></i></span>`;
        },
        renderCallbackMsgIcon: function () {
            return `<span class="callback-msg-badge text-danger" title="Callback requested"><i class="fas fa-bell"></i></span>`;
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
                <td colspan="2">
                    <div class="d-flex w-100 border rounded shadow-xs" style="height: 180px;">
                        <div class="panel-left p-2 bg-light border-end" style="width: 50%;">
                            <label class="form-label small fw-bold text-secondary mb-1">Previous Call Notes</label>
                            <textarea class="form-control form-control-sm notesOld bg-white" readonly style="height: 140px; resize: none;"></textarea>
                        </div>
                        <div class="splitter cursor-col-resize bg-secondary opacity-25" style="width: 5px; cursor: col-resize;"></div>
                        <div class="panel-right p-2 flex-grow-1 bg-white">
                            <label class="form-label small fw-bold text-dark mb-1">New Call Notes</label>
                            <textarea class="form-control form-control-sm notesNew" placeholder="Enter notes for this call..." style="height: 140px; resize: none;"></textarea>
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
                    <div class="card border-0 shadow-xs mb-3">
                        <div class="card-header bg-light border-bottom p-2">
                            <ul class="nav nav-tabs card-header-tabs m-0"></ul>
                        </div>
                    </div>
                </td>
            </tr>`;
        },
        renderCallLogTab: function (callID, name) {
            return `<li class="nav-item">
                <button type="button" class="nav-link callTab py-1.5 px-3 fw-semibold cursor-pointer" data-call-id="${callID}">
                    ${name}
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
        renderAdhocBtn: function (adhocId, label) {
            return `<button type="button" class="btn btn-outline-primary btn-sm me-2 adhocButton" data-bs-toggle="modal" data-bs-target="#${adhocId}">
                <i class="fas fa-plus me-1"></i> ${label}
            </button>`;
        },
        renderAdhocModal: function (adhocId, title) {
            return `<div class="modal fade" id="${adhocId}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white py-2 px-3">
                            <h5 class="modal-title fs-6 fw-bold">${title}</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-3">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Reason</label>
                                <select class="form-select form-select-sm" name="reason"></select>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Date</label>
                                    <input type="text" class="form-control form-control-sm" name="callDate" placeholder="YYYY-MM-DD">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Time</label>
                                    <input type="text" class="form-control form-control-sm" name="callTime" placeholder="HH:MM">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-bold">Notes</label>
                                <textarea class="form-control form-control-sm" name="notes" rows="3" placeholder="Optional call notes..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer py-2 px-3">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm callModalSave">Save Call</button>
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
                    <span class="fw-bold text-dark"><i class="fas fa-history me-1.5 text-primary"></i> Participant Call History</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary callSummarySettings border-0 px-2 py-1"><i class="fas fa-cog"></i></button>
                </div>
                <div class="card-body p-2">
                    <table class="table table-sm table-hover callSummaryTable w-100"></table>
                </div>
            </div>`;
        },
        renderCallHistorySettings: function () {
            return `<div class="text-start mb-3">
                <p class="small text-muted mb-2">Select which call types to mark complete or active for this participant:</p>
            </div>`;
        },
        renderCallHistoryRow: function (name, callId, isComplete) {
            return `<div class="form-check text-start mb-2">
                <input class="form-check-input callMetadataEdit" type="checkbox" data-call="${callId}" id="meta_${callId}" ${isComplete ? 'checked' : ''}>
                <label class="form-check-label small fw-semibold text-dark" for="meta_${callId}">${name} (${callId})</label>
            </div>`;
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