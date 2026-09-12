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
            return `<tr id="historic-display-tr" class="bg-warning bg-opacity-10 border-warning border-start border-4">
                <td colspan="2" class="p-3">
                    <div class="d-flex align-items-center text-dark">
                        <i class="fas fa-history text-warning me-2 fs-5"></i>
                        <div>
                            <strong>Historic Call Log</strong> — This call log is complete. Editing it may affect call history and attempt tracking; proceed at your own risk.
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
        renderNoCallsDisplay: function (options) {
            options = options || {};
            const hasAdhoc = Boolean(options.hasAdhoc);
            const participantName = options.participantName || '';
            const recordId = options.recordId || '';
            const callListUrl = options.callListUrl || '';
            const allCompleted = Boolean(options.allCompleted);

            let title = allCompleted ? 'All Scheduled Calls Completed' : 'No Calls Currently Due';
            let iconClass = allCompleted ? 'fa-check-circle text-success' : 'fa-phone-slash text-secondary';
            let iconBg = allCompleted ? 'bg-success-subtle text-success' : 'bg-light text-secondary';

            let subtitle = '';
            if (allCompleted) {
                subtitle = participantName
                    ? `All scheduled calls for <strong>${participantName}</strong> (Record #${recordId}) have been completed.`
                    : (recordId ? `All scheduled calls for Record #${recordId} have been completed.` : 'All scheduled calls for this participant have been completed.');
            } else {
                subtitle = participantName
                    ? `There are currently no scheduled calls due for <strong>${participantName}</strong> (Record #${recordId}).`
                    : (recordId ? `There are currently no scheduled calls due for Record #${recordId}.` : 'There are currently no calls scheduled or due for this participant.');
            }

            let adhocHtml = '';
            if (hasAdhoc) {
                adhocHtml = `
                    <div class="no-calls-adhoc-section mt-4 pt-3 border-top w-100 text-center">
                        <div class="d-flex align-items-center justify-content-center text-center gap-2 mb-2 text-dark fw-semibold">
                            <i class="fas fa-plus-circle text-primary me-2 mr-2"></i>
                            <span>Need to make an unscheduled call?</span>
                        </div>
                        <p class="mb-3 small text-muted text-center mx-auto" style="max-width: 440px;">
                            You can create an Adhoc Call to document an unscheduled participant contact, inquiry, or follow-up.
                        </p>
                        <div class="d-flex flex-wrap justify-content-center align-items-center text-center mx-auto">
                            <button type="button" class="btn btn-primary px-3 py-2 fw-semibold adhocButton shadow-xs m-1 d-inline-flex align-items-center justify-content-center" data-bs-toggle="modal" data-bs-target="#adhocModal" data-toggle="modal" data-target="#adhocModal">
                                <i class="fas fa-plus me-1.5 mr-2"></i> <span>New Adhoc Call</span>
                            </button>
                            ${callListUrl ? `
                                <button type="button" class="btn btn-outline-secondary px-3 py-2 fw-semibold goToCallListBtn m-1 d-inline-flex align-items-center justify-content-center">
                                    <i class="fas fa-arrow-left me-1.5 mr-2"></i> <span>Return to Call List</span>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            } else if (callListUrl) {
                adhocHtml = `
                    <div class="mt-4 pt-3 border-top d-flex justify-content-center align-items-center text-center w-100 mx-auto">
                        <button type="button" class="btn btn-outline-secondary px-3 py-2 fw-semibold goToCallListBtn m-1 d-inline-flex align-items-center justify-content-center">
                            <i class="fas fa-arrow-left me-1.5 mr-2"></i> <span>Return to Call List</span>
                        </button>
                    </div>
                `;
            }

            return `<tr id="no-calls-display-tr">
                <td colspan="2" class="no-calls-cell p-4 p-md-5 text-center">
                    <div class="no-calls-card text-center p-4 p-md-5 mx-auto">
                        <div class="no-calls-icon-wrapper d-flex align-items-center justify-content-center mx-auto mb-3 text-center">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle ${iconBg}" style="width: 64px; height: 64px;">
                                <i class="fas ${iconClass} fa-2x"></i>
                            </span>
                        </div>
                        <h5 class="fw-bold text-dark mb-2 text-center w-100">${title}</h5>
                        <p class="no-calls-subtitle mb-0 text-center mx-auto w-100" style="max-width: 520px;">
                            ${subtitle}
                        </p>
                        ${adhocHtml}
                    </div>
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
                            <input type="hidden" name="callDate">
                            <input type="hidden" name="callTime">
                            <div class="generation-note text-muted small mb-3 d-flex align-items-center">
                                <i class="far fa-clock text-secondary me-2"></i>
                                <span>Generated: <strong class="text-dark fw-semibold" id="adhocGenerationText"></strong></span>
                            </div>
                            <div class="card border rounded p-3 mb-3 bg-light schedule-callback-card">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input cursor-pointer" type="checkbox" id="adhocScheduleCallback" name="scheduleCallback">
                                    <label class="form-check-label small fw-bold text-dark cursor-pointer mb-0" for="adhocScheduleCallback">
                                        <i class="fas fa-calendar-alt text-primary me-1"></i> Schedule Call Back
                                    </label>
                                </div>
                                <div id="adhocCallbackContainer" class="mt-3 pt-3 border-top" style="display: none;">
                                    <div class="row g-3 mb-3">
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark mb-1">Call Back Date</label>
                                            <input type="text" class="form-control form-control-sm" name="callbackDate" placeholder="YYYY-MM-DD">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark mb-1">Call Back Time</label>
                                            <input type="text" class="form-control form-control-sm" name="callbackTime" placeholder="HH:MM">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold text-dark mb-2 d-block">Callback Requested By</label>
                                        <div class="d-flex align-items-center gap-4">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input cursor-pointer" type="radio" name="callbackRequestor" id="adhocCbReqParticipant" value="1" checked>
                                                <label class="form-check-label small cursor-pointer" for="adhocCbReqParticipant">Participant</label>
                                            </div>
                                            <div class="form-check mb-0">
                                                <input class="form-check-input cursor-pointer" type="radio" name="callbackRequestor" id="adhocCbReqStaff" value="2">
                                                <label class="form-check-label small cursor-pointer" for="adhocCbReqStaff">Staff</label>
                                            </div>
                                        </div>
                                    </div>
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
                    <span class="fw-bold text-dark small d-inline-flex align-items-center">
                        <i class="fas fa-history me-2 mr-2 text-primary d-inline-flex align-items-center justify-content-center"></i>
                        <span>Participant Call History</span>
                    </span>
                    <button type="button" class="btn btn-sm btn-link text-muted p-0 callHistorySettings d-inline-flex align-items-center justify-content-center" title="Call Metadata Settings"><i class="fas fa-cog"></i></button>
                </div>
                <div class="card-body p-2 callHistoryBody">
                    <table class="table table-sm table-hover callHistoryTable w-100 mb-0"></table>
                </div>
            </div>`;
        },
        renderCallHistoryEmpty: function () {
            return `<div class="callHistoryContainer card border shadow-xs mb-4">
                <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small d-inline-flex align-items-center">
                        <i class="fas fa-history me-2 mr-2 text-primary d-inline-flex align-items-center justify-content-center"></i>
                        <span>Participant Call History</span>
                    </span>
                    <button type="button" class="btn btn-sm btn-link text-muted p-0 callHistorySettings d-inline-flex align-items-center justify-content-center" title="Call Metadata Settings"><i class="fas fa-cog"></i></button>
                </div>
                <div class="card-body p-4 text-center text-muted small d-flex flex-column align-items-center justify-content-center">
                    <div class="d-flex align-items-center justify-content-center mx-auto mb-2 text-secondary opacity-50" style="width: 40px; height: 40px;">
                        <i class="fas fa-phone-slash fa-2x"></i>
                    </div>
                    <span class="text-center">No call history recorded for this participant yet.</span>
                </div>
            </div>`;
        },
        renderCallHistorySettings: function () {
            return `<div class="call-history-settings-grid">
            <div class="call-history-settings-column call-history-status-column">
            <div class="call-history-settings-section">
                <div class="call-history-settings-section-title"><i class="fas fa-tasks me-2"></i>Call Status</div>
                <div class="call-history-settings-section-copy">
                    <p class="small text-muted mb-1">Use the dropdowns below to set the status of each scheduled call.</p>
                    <p class="small text-muted mb-2">Changes are saved when you select <strong>Save Settings</strong>.</p>
                </div>
                <div class="call-metadata-card">`;
        },
        renderCallHistoryRawMetadata: function (rawMetadata) {
            return `</div></div>
            <div class="call-history-settings-column call-history-secondary-column">
            <div class="call-history-settings-section call-history-raw-section">
                <div class="call-history-settings-section-title"><i class="fas fa-code me-2"></i>Raw Metadata Payload</div>
                <p class="small text-muted mb-2">Read-only by default. Enabling editing can overwrite call scheduling and history data.</p>
                <label class="call-history-raw-toggle">
                    <input type="checkbox" id="enableRawMetadataEdit">
                    <span>I understand the risk and want to enable raw JSON editing.</span>
                </label>
                <textarea class="form-control callHistoryRawMetadata" rows="9" readonly spellcheck="false">${rawMetadata}</textarea>
            </div>`;
        },
        renderCallHistoryDeleteAction: function () {
            return `<div class="call-history-settings-danger mt-3 pt-3">
                <div class="call-history-danger-copy">
                    <div class="call-history-settings-section-title text-danger border-0 p-0"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</div>
                    <p class="small text-muted mb-2">Only delete the most recent call log. Deleting an older call can disrupt total call attempt tracking. This cannot be undone.</p>
                </div>
                <button type="button" class="btn btn-danger btn-sm deleteCallHistoryInstance">
                    <i class="fas fa-trash-alt me-1"></i> Delete Most Recent Call Log
                </button>
            </div>`;
        },
        renderCallHistoryRow: function (name, callId, status) {
            const displayName = name || callId;
            const currentStatus = ['incomplete', 'complete', 'expired'].includes(status) ? status : 'incomplete';
            return `<div class="call-metadata-item" data-call="${callId}">
                <div class="call-metadata-main">
                    <div class="call-metadata-header">
                        <span class="call-name-label">${displayName}</span>
                    </div>
                    <div class="call-metadata-meta">
                        <select class="form-select form-select-sm callMetadataStatusSelect call-metadata-status is-${currentStatus}" data-call="${callId}">
                            <option value="incomplete" ${currentStatus === 'incomplete' ? 'selected' : ''}>Incomplete</option>
                            <option value="complete" ${currentStatus === 'complete' ? 'selected' : ''}>Complete</option>
                            <option value="expired" ${currentStatus === 'expired' ? 'selected' : ''}>Expired</option>
                        </select>
                    </div>
                </div>
                <div class="call-metadata-sub">
                    <span class="call-id-tag"><i class="fas fa-hashtag me-1"></i>${callId}</span>
                </div>
            </div>`;
        },
        renderCallClosed: function () {
            return `<span class="badge bg-success ms-2"><i class="fas fa-check me-1"></i> Log Closed</span>`;
        }
    };

    module.renderers = renderers;

    document.addEventListener('alpine:init', () => {
        if (typeof Alpine !== 'undefined') {
            Alpine.store('templates', renderers);
        }
    });
})();