<?php
// Call Log Custom Configuration Interface
$projectId = isset($_GET['pid']) ? (int)$_GET['pid'] : (defined('PROJECT_ID') ? (int)PROJECT_ID : 0);
?>
<div class="container-fluid py-3 px-4 call-config-dashboard m-0" style="max-width: 1300px;">
    
    <!-- Top Action Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h3 class="fw-bold mb-1 text-dark">
                <i class="fas fa-cog text-primary me-2"></i> Call Log Configuration
            </h3>
            <p class="text-muted small mb-0">Manage rules, unique call types, dashboard tabs, and instrument deployments.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" id="btnSaveConfig" class="btn btn-success btn-sm px-3 shadow-sm d-inline-flex align-items-center">
                <i class="fas fa-save me-1"></i> Save Configuration
            </button>
        </div>
    </div>

    <!-- Configuration Navigation Pills -->
    <ul class="nav nav-pills mb-4 p-1 rounded-3 shadow-xs bg-light" id="configTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active btn-sm fw-semibold" id="tab-status-btn" data-toggle="pill" data-target="#tab-status" href="#tab-status" type="button" role="tab">
                <i class="fas fa-check-circle me-1"></i> Setup & Deployment Status
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link btn-sm fw-semibold" id="tab-general-btn" data-toggle="pill" data-target="#tab-general" href="#tab-general" type="button" role="tab">
                <i class="fas fa-sliders-h me-1"></i> General & Triggers
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link btn-sm fw-semibold" id="tab-calls-btn" data-toggle="pill" data-target="#tab-calls" href="#tab-calls" type="button" role="tab">
                <i class="fas fa-phone-volume me-1"></i> Call Types (<span id="pillCallTypesCount">0</span>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link btn-sm fw-semibold" id="tab-dashboard-btn" data-toggle="pill" data-target="#tab-dashboard" href="#tab-dashboard" type="button" role="tab">
                <i class="fas fa-columns me-1"></i> Dashboard Tabs (<span id="pillTabsCount">0</span>)
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="configTabsContent">
        
        <!-- Tab 1: Status & Deployment -->
        <div class="tab-pane fade show active" id="tab-status" role="tabpanel">
            
            <!-- Stats Row Above Checklist -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100 bg-white text-dark">
                        <div class="card-body text-center py-4">
                            <i class="fas fa-phone-alt fa-2x text-primary mb-2"></i>
                            <h5 class="fw-bold mb-1" id="statTotalCallsCount">0</h5>
                            <span class="text-muted small">Total Generated Calls</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100 bg-white text-dark">
                        <div class="card-body text-center py-4">
                            <i class="fas fa-phone-volume fa-2x text-info mb-2"></i>
                            <h5 class="fw-bold mb-1" id="statCallTypesCount">0</h5>
                            <span class="text-muted small">Configured Call Types</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100 bg-white text-dark">
                        <div class="card-body text-center py-4">
                            <i class="fas fa-table fa-2x text-success mb-2"></i>
                            <h5 class="fw-bold mb-1" id="statTabsCount">0</h5>
                            <span class="text-muted small">Configured Dashboard Tabs</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100 bg-white text-dark">
                        <div class="card-body text-center py-4">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h5 class="fw-bold mb-1">24 Hours</h5>
                            <span class="text-muted small">Cron Evaluation Interval</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Deployment Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom fw-bold py-3">
                    <i class="fas fa-file-import text-primary me-2"></i> Instrument Deployment (call.csv)
                </div>
                <div class="card-body">
                    <p class="card-text text-secondary mb-3">
                        The Call Log module relies on two instruments: <code>call_log</code> (Data Entry form) and <code>call_log_metadata</code> (Metadata storage). Click below to deploy or update these instruments automatically from <code>call.csv</code> into your REDCap Data Dictionary.
                    </p>
                    
                    <div id="deployTargetEventContainer" class="mb-3 p-3 bg-light border rounded" style="display:none;">
                        <label class="form-label fw-bold mb-1">Assign Instruments to Single Event:</label>
                        <div class="setting-blurb mb-2">
                            Multiple events detected. Select the single event where the <code>call_log</code> and <code>call_log_metadata</code> instruments should be assigned:
                        </div>
                        <select id="deployTargetEventSelect" class="form-select form-select-sm" style="max-width: 400px;"></select>
                    </div>

                    <button type="button" id="btnDeployInstruments" class="btn btn-outline-primary btn-sm shadow-xs">
                        <i class="fas fa-download me-1"></i> Deploy Call Log Instruments (call.csv)
                    </button>
                </div>
            </div>

            <!-- Setup Progress Checklist Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-tasks text-success me-2"></i> Module Setup Progress</span>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4" id="chk-item-deploy">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle me-3 fs-5 text-muted status-icon"></i>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">1. Deploy Call Log Instruments</h6>
                                    <small class="text-muted">Deploy <code>call_log</code> and <code>call_log_metadata</code> instruments from <code>call.csv</code>.</small>
                                </div>
                            </div>
                            <span class="badge bg-secondary status-badge">Pending</span>
                        </li>

                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4" id="chk-item-trigger">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-bolt me-3 fs-5 text-muted status-icon"></i>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">2. Select Generation Triggers</h6>
                                    <small class="text-muted">Select form saves that trigger automated participant call evaluation.</small>
                                </div>
                            </div>
                            <span class="badge bg-secondary status-badge">Pending</span>
                        </li>

                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4" id="chk-item-calls">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-list-ol me-3 fs-5 text-muted status-icon"></i>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">3. Define Unique Call Types</h6>
                                    <small class="text-muted">Create unique call rules (MCV, NTS, Reminders, Follow-ups, Ad-hoc).</small>
                                </div>
                            </div>
                            <span class="badge bg-secondary status-badge">Pending</span>
                        </li>

                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4" id="chk-item-tabs">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-columns me-3 fs-5 text-muted status-icon"></i>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">4. Configure Dashboard Call Tabs</h6>
                                    <small class="text-muted">Add at least one tab to group and present calls on the Call List dashboard.</small>
                                </div>
                            </div>
                            <span class="badge bg-secondary status-badge">Pending</span>
                        </li>

                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4" id="chk-item-withdraw">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-user-slash me-3 fs-5 text-muted status-icon"></i>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">5. Subject Withdrawal Rules (Optional)</h6>
                                    <small class="text-muted">Set permanent subject withdrawal event and field rules to exclude subjects.</small>
                                </div>
                            </div>
                            <span class="badge bg-secondary status-badge">Optional</span>
                        </li>
                    </ul>
                </div>
            </div>

        </div>

        <!-- Tab 2: General & Triggers -->
        <div class="tab-pane fade" id="tab-general" role="tabpanel">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom fw-bold py-3">
                    <i class="fas fa-bolt text-warning me-2"></i> Generation Triggers & Display Options
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Trigger Call Log Generation On Form Save:</label>
                        <div class="setting-blurb mb-2">
                            Select project data entry forms that should trigger automated call generation whenever saved by research staff.
                        </div>
                        <div id="cfg_trigger_save_container" class="custom-multiselect-container">
                            <div class="custom-multiselect-box"></div>
                            <div class="custom-multiselect-menu"></div>
                        </div>
                    </div>

                    <div class="mb-4 border-top pt-3">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" id="cfg_same_day_mcv_nts">
                            <label class="form-check-label fw-bold" for="cfg_same_day_mcv_nts">Show Missed/Cancelled Visit (MCV) and Need to Schedule (NTS) Calls Same Day</label>
                        </div>
                        <div class="setting-blurb">
                            By default, generated calls appear starting the day after form save. Toggle this setting ON to present Missed/Cancelled Visit (MCV) and Need to Schedule (NTS) calls immediately on the same day.
                        </div>
                    </div>

                    <div class="mb-2 border-top pt-3">
                        <label class="form-label fw-bold">Include Call Summary Table On Instruments:</label>
                        <div class="setting-blurb mb-2">
                            Choose which data entry instruments will render the interactive Call Summary widget, giving study staff quick access to caller notes and attempt history directly on participant records.
                        </div>
                        <div id="cfg_call_summary_container" class="custom-multiselect-container">
                            <div class="custom-multiselect-box"></div>
                            <div class="custom-multiselect-menu"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Holiday Skip Rules Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom fw-bold py-3">
                    <i class="fas fa-calendar-alt text-primary me-2"></i> Holiday Skip Rules
                </div>
                <div class="card-body">
                    <div class="setting-blurb mb-3">
                        Holiday Skip Rules prevent automated call dates (such as Follow-up windows, Reminders, and Need to Schedule) from landing on days when staff are off. Calculated call dates that fall on enabled holidays or weekends automatically adjust to the preceding, or following, business day.
                    </div>
                    
                    <h6 class="fw-bold text-dark mb-2">Standard US Holidays</h6>
                    <p class="small text-muted mb-3">Select standard holidays to observe for this project:</p>
                    <div class="row g-2 mb-4" id="standardHolidaysContainer"></div>

                    <hr class="my-4">

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Custom Site Closures & Institutional Holidays</h6>
                            <p class="small text-muted mb-0">Add custom annual site closures (use MM-DD format, e.g. 12-26 for Day After Christmas or 11-03 for Staff Day):</p>
                        </div>
                        <button type="button" id="btnAddCustomHoliday" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-plus me-1"></i> Add Custom Closure
                        </button>
                    </div>
                    <div id="customHolidaysContainer" class="d-flex flex-column gap-2 mt-3"></div>
                </div>
            </div>

            <!-- Subject Withdrawal Conditions Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom fw-bold py-3">
                    <i class="fas fa-user-slash text-danger me-2"></i> Subject Withdrawal Conditions
                </div>
                <div class="card-body">
                    <div class="setting-blurb mb-3">
                        Configure conditions under which subjects are permanently excluded from the call log. A subject is considered withdrawn when any specified event/field combination contains a <strong>truthy value</strong> (e.g., checked checkbox, non-zero number, or non-empty date).
                    </div>
                    <div id="withdrawRulesContainer" class="d-flex flex-column gap-3 mb-3"></div>
                    <button type="button" id="btnAddWithdrawRule" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-plus me-1"></i> Add Withdrawal Condition
                    </button>
                </div>
            </div>

            <!-- Row Expansion Area Fields Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom fw-bold py-3">
                    <i class="fas fa-chevron-down text-info me-2"></i> Row Expansion Area Fields
                </div>
                <div class="card-body">
                    <div class="setting-blurb mb-3">
                        Specify which project fields are displayed when callers click to expand a participant row on the Call List dashboard (e.g., alternate phone numbers, preferred call times, or secondary contact names).
                    </div>
                    <div id="expandsFieldsContainer" class="d-flex flex-column gap-3 mb-3"></div>
                    <button type="button" id="btnAddExpandsField" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> Add Expansion Field
                    </button>
                </div>
            </div>
        </div>

        <!-- Tab 3: Call Types -->
        <div class="tab-pane fade" id="tab-calls" role="tabpanel">
            <div class="setting-blurb mb-3">
                Define unique call rules to power participant call queues. Assign unique call IDs, friendly display names, template types, and max attempt limits before calls auto-retire.
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">Unique Call Types</h5>
                <button type="button" id="btnAddCallType" class="btn btn-primary btn-sm px-3 shadow-sm">
                    <i class="fas fa-plus me-1"></i> Add Call Type
                </button>
            </div>

            <div id="callTypesContainer" class="d-flex flex-column gap-4 mb-4"></div>
        </div>

        <!-- Tab 4: Dashboard Tabs -->
        <div class="tab-pane fade" id="tab-dashboard" role="tabpanel">
            <div class="setting-blurb mb-3">
                Configure segmented tabs for the main Call List dashboard. Each tab presents specific call types and custom participant fields.
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">Dashboard Call Tabs</h5>
                <button type="button" id="btnAddTab" class="btn btn-success btn-sm px-3 shadow-sm">
                    <i class="fas fa-plus me-1"></i> Add Call Tab
                </button>
            </div>

            <div id="callTabsContainer" class="d-flex flex-column gap-4 mb-4"></div>
        </div>

    </div>
</div>
