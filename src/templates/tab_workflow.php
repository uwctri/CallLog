<?php
/**
 * Tab 5: Workflow
 */
?>
<div class="tab-pane fade show active" x-show="activeTab === 'workflow'">
    
    <!-- Navigation & Visibility Controls -->
    <div class="card border shadow-sm mb-4 rounded-3">
        <div class="card-header bg-light border-bottom fw-bold py-3 px-4 text-dark">
            <i class="fas fa-compass text-primary me-2"></i> How to Navigate to the Call Log
        </div>
        <div class="card-body p-4">
            <!-- Option 1: Record Home Page Button -->
            <div class="mb-4">
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input me-2 cursor-pointer" type="checkbox" id="cfg_show_record_home_button" x-model="showRecordHomeButton">
                    <label class="form-check-label fw-bold text-dark cursor-pointer" for="cfg_show_record_home_button">
                        Show "Call Log" Button on Record Home Page
                    </label>
                </div>
                <div class="setting-blurb text-muted small ps-4" style="line-height: 1.5;">
                    Places a dedicated <strong>Call Log</strong> button directly on each participant's Record Home page next to the record action menu. This gives study staff a fast, one-click shortcut into the call log without needing to locate the instrument in the event grid or sidebar.
                </div>
            </div>

            <!-- Option 2: Show Call Log Instrument in Navigation -->
            <div class="mb-4 border-top pt-3">
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input me-2 cursor-pointer" type="checkbox" id="cfg_show_call_log_instrument" x-model="showCallLogInstrument">
                    <label class="form-check-label fw-bold text-dark cursor-pointer" for="cfg_show_call_log_instrument">
                        Show Call Log Instrument in Navigation
                    </label>
                </div>
                <div class="setting-blurb text-muted small ps-4" style="line-height: 1.5;">
                    Controls whether the <code>call_log</code> instrument is listed in REDCap's left sidebar and the Record Home event grid. When turned off, the instrument is hidden from routine navigation so staff enter calls through the Record Home button above or the main Call List dashboard.
                </div>
            </div>

            <!-- Option 3: Show Metadata Instrument -->
            <div class="mb-2 border-top pt-3">
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input me-2 cursor-pointer" type="checkbox" id="cfg_show_metadata_instrument" x-model="showMetadataInstrument">
                    <label class="form-check-label fw-bold text-dark cursor-pointer" for="cfg_show_metadata_instrument">
                        Show Call Log Metadata Instrument
                    </label>
                </div>
                <div class="setting-blurb text-muted small ps-4" style="line-height: 1.5;">
                    Controls whether the internal <code>call_log_metadata</code> instrument is displayed in REDCap menus and record tables.
                    <div class="alert alert-light border mt-2 mb-0 py-2 px-3 small text-secondary">
                        <i class="fas fa-info-circle text-info me-1"></i>
                        <strong>Note:</strong> Default is <strong>hidden</strong>. This instrument stores internal tracking metadata and call history as JSON. It should remain hidden during normal study operations, but can be enabled for debugging, auditing data, or troubleshooting specific records.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Call Log Features -->
    <div class="card border shadow-sm mb-4 rounded-3">
        <div class="card-header bg-light border-bottom fw-bold py-3 px-4 text-dark">
            <i class="fas fa-sliders-h text-primary me-2"></i> Call Log Features
        </div>
        <div class="card-body p-4">
            <!-- Form Customization Guidance -->
            <div class="setting-blurb mb-3">
                You are free to customize and extend the <strong>Call Log</strong> instrument in REDCap's Online Designer by adding survey items, participant checklists, or custom notes fields.
                However, certain core variables (such as <code>call_id</code>, <code>call_template</code>, timestamps, <code>call_outcome</code>, and <code>call_notes</code>) are native to the Call Log module and required for attempt tracking and call history.
            </div>

            <!-- Call Duration Stopwatch Widget -->
            <div class="border-top pt-3 mb-3">
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input me-2 cursor-pointer" type="checkbox" id="cfg_enable_call_timer" x-model="enableCallTimer">
                    <label class="form-check-label fw-bold text-dark cursor-pointer" for="cfg_enable_call_timer">
                        Display Call Duration Stopwatch Widget During Calls
                    </label>
                </div>
                <div class="setting-blurb text-muted small ps-4" style="line-height: 1.5;">
                    Renders an interactive stopwatch widget positioned directly below the participant call history table while on a call. Callers can start, pause, resume, and record call durations in real time, with automatic state preservation when switching between call tabs.
                </div>
            </div>

            <!-- Native Fields Reference Table & Reset Action -->
            <div class="border-top pt-3">
                <div class="d-flex flex-wrap justify-content-between align-items-end mb-2 gap-2">
                    <div class="fw-bold text-dark small mb-1">
                        <i class="fas fa-cubes text-primary me-1.5"></i> Native Call Log Fields Reference
                        <span class="badge bg-light text-muted border ms-1 font-monospace" style="font-size: 0.75rem;">26 fields</span>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-sm btn-warning-reset" @click="resetCallLogInstrument()" :disabled="resettingInstrument">
                            <i class="fas fa-sync-alt me-1" :class="{ 'fa-spin': resettingInstrument }"></i>
                            <span x-text="resettingInstrument ? 'Resetting...' : 'Reset Fields'"></span>
                        </button>
                        <div class="text-muted mt-1" style="font-size: 0.75rem;">
                            <i class="fas fa-shield-alt text-success me-1"></i>Preserves custom fields
                        </div>
                    </div>
                </div>
                <div class="border rounded-2 bg-white">
                    <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0 text-muted" style="font-size: 0.825rem;">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 35px;" class="ps-3">#</th>
                                    <th>Field Label</th>
                                    <th>Variable Name</th>
                                    <th>Type</th>
                                    <th class="text-center" style="width: 130px;">Role</th>
                                    <th class="text-center pe-3" style="width: 110px;">Visibility</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="ps-3">1</td>
                                    <td class="fw-semibold text-dark">Call Template</td>
                                    <td><code>call_template</code></td>
                                    <td>Dropdown</td>
                                    <td class="text-center"><span class="badge bg-danger-subtle text-danger border border-danger-subtle">Native Required</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-secondary-subtle text-muted border">Hidden</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">2</td>
                                    <td class="fw-semibold text-dark">Call ID</td>
                                    <td><code>call_id</code></td>
                                    <td>Text</td>
                                    <td class="text-center"><span class="badge bg-danger-subtle text-danger border border-danger-subtle">Native Required</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-secondary-subtle text-muted border">Hidden</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">3</td>
                                    <td class="fw-semibold text-dark">Call Event</td>
                                    <td><code>call_event_name</code></td>
                                    <td>Text</td>
                                    <td class="text-center"><span class="badge bg-info-subtle text-info border border-info-subtle">Core Native</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-secondary-subtle text-muted border">Hidden</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">4</td>
                                    <td class="fw-semibold text-dark">Call Attempt</td>
                                    <td><code>call_attempt</code></td>
                                    <td>Text</td>
                                    <td class="text-center"><span class="badge bg-danger-subtle text-danger border border-danger-subtle">Native Required</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-secondary-subtle text-muted border">Hidden</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">5</td>
                                    <td class="fw-semibold text-dark">Call Open Date</td>
                                    <td><code>call_open_date</code></td>
                                    <td>Date (@TODAY)</td>
                                    <td class="text-center"><span class="badge bg-danger-subtle text-danger border border-danger-subtle">Native Required</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-secondary-subtle text-muted border">Hidden</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">6</td>
                                    <td class="fw-semibold text-dark">Call Open Time</td>
                                    <td><code>call_open_time</code></td>
                                    <td>Time (@NOW)</td>
                                    <td class="text-center"><span class="badge bg-danger-subtle text-danger border border-danger-subtle">Native Required</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-secondary-subtle text-muted border">Hidden</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">7</td>
                                    <td class="fw-semibold text-dark">Call Open Datetime</td>
                                    <td><code>call_open_datetime</code></td>
                                    <td>Datetime (@NOW)</td>
                                    <td class="text-center"><span class="badge bg-danger-subtle text-danger border border-danger-subtle">Native Required</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-secondary-subtle text-muted border">Hidden</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">8</td>
                                    <td class="fw-semibold text-dark">Call Open User</td>
                                    <td><code>call_open_user</code></td>
                                    <td>Text (@USERNAME)</td>
                                    <td class="text-center"><span class="badge bg-danger-subtle text-danger border border-danger-subtle">Native Required</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-secondary-subtle text-muted border">Hidden</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">9</td>
                                    <td class="fw-semibold text-dark">Call Open User Full Name</td>
                                    <td><code>call_open_user_full_name</code></td>
                                    <td>Text</td>
                                    <td class="text-center"><span class="badge bg-danger-subtle text-danger border border-danger-subtle">Native Required</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-secondary-subtle text-muted border">Hidden</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">10</td>
                                    <td class="fw-semibold text-dark">Call Log Title Header</td>
                                    <td><code>call_hdr_title</code></td>
                                    <td>Descriptive</td>
                                    <td class="text-center"><span class="badge bg-secondary-subtle text-secondary border">Structure / UI</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-success-subtle text-success border border-success-subtle">Visible</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">11</td>
                                    <td class="fw-semibold text-dark">Call Details Header</td>
                                    <td><code>call_hdr_details</code></td>
                                    <td>Descriptive</td>
                                    <td class="text-center"><span class="badge bg-secondary-subtle text-secondary border">Structure / UI</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-success-subtle text-success border border-success-subtle">Visible</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">12</td>
                                    <td class="fw-semibold text-dark">Call Info Summary Table</td>
                                    <td><code>call_hdr_call_info_table</code></td>
                                    <td>Descriptive</td>
                                    <td class="text-center"><span class="badge bg-secondary-subtle text-secondary border">Structure / UI</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-success-subtle text-success border border-success-subtle">Visible</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">13</td>
                                    <td class="fw-semibold text-dark">Call Script Header</td>
                                    <td><code>call_hdr_script</code></td>
                                    <td>Descriptive</td>
                                    <td class="text-center"><span class="badge bg-secondary-subtle text-secondary border">Structure / UI</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-success-subtle text-success border border-success-subtle">Visible</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">14</td>
                                    <td class="fw-semibold text-dark">Call Script Dynamic Display</td>
                                    <td><code>call_script</code></td>
                                    <td>Descriptive</td>
                                    <td class="text-center"><span class="badge bg-info-subtle text-info border border-info-subtle">Dynamic Script</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-success-subtle text-success border border-success-subtle">Visible</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">15</td>
                                    <td class="fw-semibold text-dark">Subject Answered phone</td>
                                    <td><code>call_answered</code></td>
                                    <td>Checkbox</td>
                                    <td class="text-center"><span class="badge bg-info-subtle text-info border border-info-subtle">Core Native</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-success-subtle text-success border border-success-subtle">Visible</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">16</td>
                                    <td class="fw-semibold text-dark">Phone Disconnected / Not in Service</td>
                                    <td><code>call_disconnected</code></td>
                                    <td>Checkbox</td>
                                    <td class="text-center"><span class="badge bg-info-subtle text-info border border-info-subtle">Core Native</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-success-subtle text-success border border-success-subtle">Visible</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">17</td>
                                    <td class="fw-semibold text-dark">Disconnected Notice Alert</td>
                                    <td><code>call_text_disconnect</code></td>
                                    <td>Descriptive</td>
                                    <td class="text-center"><span class="badge bg-warning-subtle text-dark border border-warning-subtle">Advisory Notice</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-info-subtle text-info border border-info-subtle">Conditional</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">18</td>
                                    <td class="fw-semibold text-dark">Left a Message</td>
                                    <td><code>call_left_message</code></td>
                                    <td>Checkbox</td>
                                    <td class="text-center"><span class="badge bg-info-subtle text-info border border-info-subtle">Core Native</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-info-subtle text-info border border-info-subtle">Conditional</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">19</td>
                                    <td class="fw-semibold text-dark">Set Callback</td>
                                    <td><code>call_requested_callback</code></td>
                                    <td>Checkbox</td>
                                    <td class="text-center"><span class="badge bg-info-subtle text-info border border-info-subtle">Core Native</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-success-subtle text-success border border-success-subtle">Visible</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">20</td>
                                    <td class="fw-semibold text-dark">Callback Requested by</td>
                                    <td><code>call_callback_requested_by</code></td>
                                    <td>Radio</td>
                                    <td class="text-center"><span class="badge bg-info-subtle text-info border border-info-subtle">Core Native</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-info-subtle text-info border border-info-subtle">Conditional</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">21</td>
                                    <td class="fw-semibold text-dark">Callback Date</td>
                                    <td><code>call_callback_date</code></td>
                                    <td>Date (@TOMORROWBUTTON)</td>
                                    <td class="text-center"><span class="badge bg-info-subtle text-info border border-info-subtle">Core Native</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-info-subtle text-info border border-info-subtle">Conditional</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">22</td>
                                    <td class="fw-semibold text-dark">Callback Time</td>
                                    <td><code>call_callback_time</code></td>
                                    <td>Time (@HIDEBUTTON)</td>
                                    <td class="text-center"><span class="badge bg-info-subtle text-info border border-info-subtle">Core Native</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-info-subtle text-info border border-info-subtle">Conditional</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">23</td>
                                    <td class="fw-semibold text-dark">Call Log Outcome</td>
                                    <td><code>call_outcome</code></td>
                                    <td>Radio</td>
                                    <td class="text-center"><span class="badge bg-danger-subtle text-danger border border-danger-subtle">Native Required</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-success-subtle text-success border border-success-subtle">Visible</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">24</td>
                                    <td class="fw-semibold text-dark">Task(s) Remaining</td>
                                    <td><code>call_task_remaining</code></td>
                                    <td>Checkbox</td>
                                    <td class="text-center"><span class="badge bg-info-subtle text-info border border-info-subtle">Core Native</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-info-subtle text-info border border-info-subtle">Conditional</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">25</td>
                                    <td class="fw-semibold text-dark">Call Notes</td>
                                    <td><code>call_notes</code></td>
                                    <td>Notes</td>
                                    <td class="text-center"><span class="badge bg-danger-subtle text-danger border border-danger-subtle">Native Required</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-success-subtle text-success border border-success-subtle">Visible</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">26</td>
                                    <td class="fw-semibold text-dark">End Of Call Log Footer</td>
                                    <td><code>call_hdr_end</code></td>
                                    <td>Descriptive</td>
                                    <td class="text-center"><span class="badge bg-secondary-subtle text-secondary border">Structure / UI</span></td>
                                    <td class="text-center pe-3"><span class="badge bg-success-subtle text-success border border-success-subtle">Visible</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
