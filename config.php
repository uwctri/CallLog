<?php
// Call Log Custom Configuration Interface
$projectId = isset($_GET['pid']) ? (int)$_GET['pid'] : (defined('PROJECT_ID') ? (int)PROJECT_ID : 0);
?>
<script type="text/javascript">
if (typeof tinymce === 'undefined') {
    document.write('<script type="text/javascript" src="<?= (defined('APP_PATH_WEBROOT') ? APP_PATH_WEBROOT : '/redcap_v17.4.0/') ?>Resources/webpack/css/tinymce/tinymce.min.js"><\/script>');
}
</script>
<div class="container-fluid py-3 px-4 call-config-dashboard m-0" style="max-width: 1300px;" x-data="callLogConfig">
    
    <!-- Top Action Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary text-white rounded-3 shadow-sm d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                <i class="fas fa-cog text-white" style="font-size: 1.65rem;"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h3 class="fw-bold mb-0" style="color: #0f172a;">Call Log Configuration</h3>
                </div>
                <span class="text-muted small">Manage rules, unique call types, dashboard tabs, and instrument deployments.</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" @click="saveConfig()" :disabled="saving" class="btn btn-success btn-sm px-3 py-2 shadow-sm fw-bold d-inline-flex align-items-center">
                <i class="fas me-2" :class="saving ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                <span x-text="saving ? 'Saving Settings...' : 'Save Configuration'">Save Configuration</span>
            </button>
        </div>
    </div>

    <!-- Configuration Navigation Pills -->
    <ul class="custom-nav-pills" role="tablist">
        <li>
            <button type="button" class="custom-nav-pill" :class="{ 'active': activeTab === 'status' }" @click="activeTab = 'status'">
                <i class="fas fa-check-circle me-2"></i> Quick Setup & Status
            </button>
        </li>
        <li>
            <button type="button" class="custom-nav-pill" :class="{ 'active': activeTab === 'general' }" @click="activeTab = 'general'">
                <i class="fas fa-sliders-h me-2"></i> General & Triggers
            </button>
        </li>
        <li>
            <button type="button" class="custom-nav-pill" :class="{ 'active': activeTab === 'calls' }" @click="activeTab = 'calls'">
                <i class="fas fa-phone-volume me-2"></i> Call Types
            </button>
        </li>
        <li>
            <button type="button" class="custom-nav-pill" :class="{ 'active': activeTab === 'dashboard' }" @click="activeTab = 'dashboard'">
                <i class="fas fa-columns me-2"></i> Dashboard Tabs
            </button>
        </li>
        <li>
            <button type="button" class="custom-nav-pill" :class="{ 'active': activeTab === 'workflow' }" @click="activeTab = 'workflow'">
                <i class="fas fa-route me-2"></i> Workflow
            </button>
        </li>
        <li>
            <button type="button" class="custom-nav-pill" :class="{ 'active': activeTab === 'reports' }" @click="activeTab = 'reports'">
                <i class="fas fa-chart-line me-2"></i> Reports & Analytics
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        
        <!-- Tab 1: Quick Setup & Status -->
        <div class="tab-pane fade show active" x-show="activeTab === 'status'">
            
            <!-- Stats Row -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card border shadow-sm h-100 bg-white text-dark rounded-3">
                        <div class="card-body text-center py-3 px-2">
                            <i class="fas fa-phone-alt fa-2x text-primary mb-2"></i>
                            <h4 class="fw-bold mb-1 text-dark" x-text="totalGeneratedCalls">0</h4>
                            <span class="text-muted small fw-semibold">Generated Calls</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card border shadow-sm h-100 bg-white text-dark rounded-3">
                        <div class="card-body text-center py-3 px-2">
                            <i class="fas fa-check-double fa-2x text-success mb-2"></i>
                            <h4 class="fw-bold mb-1 text-dark" x-text="completedCalls">0</h4>
                            <span class="text-muted small fw-semibold">Completed Calls</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card border shadow-sm h-100 bg-white text-dark rounded-3">
                        <div class="card-body text-center py-3 px-2">
                            <i class="fas fa-headset fa-2x text-warning mb-2"></i>
                            <h4 class="fw-bold mb-1 text-dark" x-text="totalCallAttempts">0</h4>
                            <span class="text-muted small fw-semibold">Logged Attempts</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card border shadow-sm h-100 bg-white text-dark rounded-3">
                        <div class="card-body text-center py-3 px-2">
                            <i class="fas fa-users fa-2x mb-2" style="color: #6f42c1;"></i>
                            <h4 class="fw-bold mb-1 text-dark" x-text="uniqueCallers">0</h4>
                            <span class="text-muted small fw-semibold">Unique Callers</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card border shadow-sm h-100 bg-white text-dark rounded-3">
                        <div class="card-body text-center py-3 px-2">
                            <i class="fas fa-phone-volume fa-2x text-info mb-2"></i>
                            <h4 class="fw-bold mb-1 text-dark" x-text="callTypes.length">0</h4>
                            <span class="text-muted small fw-semibold">Configured Types</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card border shadow-sm h-100 bg-white text-dark rounded-3">
                        <div class="card-body text-center py-3 px-2">
                            <i class="fas fa-table fa-2x mb-2" style="color: #0284c7;"></i>
                            <h4 class="fw-bold mb-1 text-dark" x-text="callTabs.length">0</h4>
                            <span class="text-muted small fw-semibold">Dashboard Tabs</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- General Design Philosophy Card -->
            <div class="card border shadow-sm mb-4 rounded-3">
                <div class="card-header bg-light border-bottom fw-bold py-3 px-4 text-dark">
                    <i class="fas fa-lightbulb text-warning me-2"></i> General Design Philosophy
                </div>
                <div class="card-body p-4">
                    <p class="text-secondary mb-3 w-100" style="font-size: 0.875rem; line-height: 1.6; max-width: 100%;">
                        The Call Log module is designed around the principles of native REDCap compatibility, automated lifecycle management, and transparent data persistence. Rather than acting as a black box or introducing custom external tables, the module operates directly within your project's existing structure so study teams maintain complete control, visibility, and native reporting access across every call:
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-3">
                            <div class="border rounded-3 p-3 h-100 bg-light bg-opacity-50">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <h6 class="fw-bold mb-0 text-dark">Standard Instruments</h6>
                                </div>
                                <div class="text-muted small" style="line-height: 1.5;">
                                    Call Logs operate as standard REDCap instruments. The repeating <code>call_log</code> form captures individual call attempts and notes, making data exportable and reportable via normal REDCap tools.
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="border rounded-3 p-3 h-100 bg-light bg-opacity-50">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                        <i class="fas fa-magic"></i>
                                    </div>
                                    <h6 class="fw-bold mb-0 text-dark">Automated Generation</h6>
                                </div>
                                <div class="text-muted small" style="line-height: 1.5;">
                                    Calls are generated automatically whenever possible: upon saving designated project forms or during the nightly cron evaluation based on study visit schedules and window rules.
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="border rounded-3 p-3 h-100 bg-light bg-opacity-50">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="rounded-circle bg-info bg-opacity-10 text-info d-inline-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                        <i class="fas fa-database"></i>
                                    </div>
                                    <h6 class="fw-bold mb-0 text-dark">Native Persistence</h6>
                                </div>
                                <div class="text-muted small" style="line-height: 1.5;">
                                    All tracking data is saved within REDCap itself—either in the repeating <code>call_log</code> attempts or in <code>call_log_metadata</code> as a structured metadata JSON object. No custom tables are used.
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="border rounded-3 p-3 h-100 bg-light bg-opacity-50">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-inline-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                        <i class="fas fa-sliders-h"></i>
                                    </div>
                                    <h6 class="fw-bold mb-0 text-dark">Call Type Assumptions</h6>
                                </div>
                                <div class="text-muted small" style="line-height: 1.5;">
                                    Each call type operates on built-in lifecycle assumptions—evaluating milestone dates, visit sequences, indicator fields, or completion criteria to determine exactly when calls become active, expire, or complete.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Setup Progress Checklist Card -->
            <div class="card border shadow-sm mb-4 rounded-3">
                <div class="card-header bg-light border-bottom fw-bold py-3 px-4 text-dark">
                    <i class="fas fa-tasks text-success me-2"></i> Module Setup Progress
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush rounded-3">
                        <li class="list-group-item d-flex justify-content-between align-items-start py-3 px-4">
                            <div class="d-flex align-items-start me-3">
                                <i class="fas fa-check-circle me-3 fs-5 mt-1" :class="setupChecklist.deployment ? 'text-success' : 'text-muted'"></i>
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">1. Deploy Call Log Instruments</h6>
                                    <div class="text-muted small" style="line-height: 1.5;">
                                        Adds the two instruments needed by this module to your REDCap project. The <code>call_log</code> repeating form is where callers record each individual call attempt (date/time, outcome, caller notes, etc.). The <code>call_log_metadata</code> form stores background tracking details and active call states.
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-3 mt-1">
                                <template x-if="!setupChecklist.deployment">
                                    <button type="button" @click="deployInstruments()" :disabled="deploying" class="btn btn-primary btn-sm px-3 py-1.5 fw-bold shadow-xs me-2">
                                        <i class="fas me-1" :class="deploying ? 'fa-spinner fa-spin' : 'fa-download'"></i>
                                        <span x-text="deploying ? 'Deploying...' : 'Deploy Instruments'">Deploy Instruments</span>
                                    </button>
                                </template>
                                <span class="badge checklist-badge py-2 fw-semibold" style="width: 130px;" :class="setupChecklist.deployment ? 'bg-success text-white' : 'bg-secondary text-white'" x-text="setupChecklist.deployment ? 'Deployed' : 'Pending'">Pending</span>
                            </div>
                        </li>

                        <li class="list-group-item d-flex justify-content-between align-items-start py-3 px-4">
                            <div class="d-flex align-items-start me-3">
                                <i class="fas me-3 fs-5 mt-1" :class="setupChecklist.eventRepeat ? 'fa-check-circle text-success' : (setupChecklist.deployment ? 'fa-exclamation-circle text-warning' : 'fa-check-circle text-muted')"></i>
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">2. Event Assignment & Repeatable Setup</h6>
                                    <div class="text-muted small mb-2" style="line-height: 1.5;">
                                        Make sure the <code>call_log</code> and <code>call_log_metadata</code> instruments are both assigned to only <strong>1 event</strong>, and that the <code>call_log</code> instrument has been enabled as <strong>repeatable</strong>. (Because callers make multiple call attempts over time, <code>call_log</code> must repeat so each attempt is saved separately; <code>call_log_metadata</code> should stay non-repeating).
                                    </div>
                                    <template x-if="setupChecklist.deployment">
                                        <div class="d-flex flex-wrap align-items-center gap-3 pt-1">
                                            <span class="small d-inline-flex align-items-center" :class="setupChecklist.eventConfig.singleEventValid ? 'text-success fw-semibold' : 'text-danger fw-semibold'">
                                                <i class="fas me-1" :class="setupChecklist.eventConfig.singleEventValid ? 'fa-check-circle' : 'fa-times-circle'"></i>
                                                <span x-text="setupChecklist.eventConfig.singleEventValid ? ('Single Event: ' + (setupChecklist.eventConfig.assignedEventName || 'Assigned')) : (setupChecklist.eventConfig.eventMessage || 'Event assignment issue')"></span>
                                            </span>
                                            <span class="small d-inline-flex align-items-center" :class="setupChecklist.eventConfig.repeatableValid ? 'text-success fw-semibold' : 'text-danger fw-semibold'">
                                                <i class="fas me-1" :class="setupChecklist.eventConfig.repeatableValid ? 'fa-check-circle' : 'fa-times-circle'"></i>
                                                <span x-text="setupChecklist.eventConfig.repeatableValid ? 'Call Log is repeatable' : 'Call Log is not repeatable'"></span>
                                            </span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-3 mt-1">
                                <template x-if="setupChecklist.deployment && !setupChecklist.eventRepeat && setupChecklist.eventConfig.singleEventValid && !setupChecklist.eventConfig.repeatableValid">
                                    <button type="button" @click="enableRepeatable()" :disabled="enablingRepeatable" class="btn btn-warning btn-sm px-3 py-1.5 fw-bold shadow-xs text-dark">
                                        <i class="fas me-1" :class="enablingRepeatable ? 'fa-spinner fa-spin' : 'fa-redo'"></i>
                                        <span x-text="enablingRepeatable ? 'Enabling...' : 'Enable Repeatable Now'">Enable Repeatable Now</span>
                                    </button>
                                </template>
                                <span class="badge checklist-badge py-2 fw-semibold" style="width: 130px;" :class="setupChecklist.eventRepeat ? 'bg-success text-white' : (setupChecklist.deployment ? 'bg-warning text-dark' : 'bg-secondary text-white')" x-text="setupChecklist.eventRepeat ? 'Configured' : (setupChecklist.deployment ? 'Action Required' : 'Pending')">Pending</span>
                            </div>
                        </li>

                        <li class="list-group-item d-flex justify-content-between align-items-start py-3 px-4">
                            <div class="d-flex align-items-start me-3">
                                <i class="fas fa-check-circle me-3 fs-5 mt-1" :class="setupChecklist.trigger ? 'text-success' : 'text-muted'"></i>
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">3. Select Generation Triggers</h6>
                                    <div class="text-muted small" style="line-height: 1.5;">
                                        Generation Triggers generate calls for your participants. Choose which data entry forms should trigger call generation whenever staff save a form. (The automated daily cron also checks participants against your call rules each night).
                                    </div>
                                </div>
                            </div>
                            <span class="badge checklist-badge py-2 fw-semibold flex-shrink-0 ms-3 mt-1" style="width: 130px;" :class="setupChecklist.trigger ? 'bg-success text-white' : 'bg-secondary text-white'" x-text="setupChecklist.trigger ? 'Configured' : 'Pending'">Pending</span>
                        </li>

                        <li class="list-group-item d-flex justify-content-between align-items-start py-3 px-4">
                            <div class="d-flex align-items-start me-3">
                                <i class="fas fa-check-circle me-3 fs-5 mt-1" :class="setupChecklist.calls ? 'text-success' : 'text-muted'"></i>
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">4. Define Unique Call Types</h6>
                                    <div class="text-muted small" style="line-height: 1.5;">
                                        Set up the specific rules for the kinds of calls your study needs to make. You can create <strong>Reminders</strong> ahead of upcoming visits, <strong>Follow Ups</strong> after baseline visits, <strong>Missed / Cancelled Visit (MCV)</strong> calls when visits are missed, <strong>Need to Schedule (NTS)</strong> calls for due visits, and <strong>New Entry</strong> or <strong>Adhoc</strong> calls.
                                    </div>
                                </div>
                            </div>
                            <span class="badge checklist-badge py-2 fw-semibold flex-shrink-0 ms-3 mt-1" style="width: 130px;" :class="setupChecklist.calls ? 'bg-success text-white' : 'bg-secondary text-white'" x-text="setupChecklist.calls ? 'Configured' : 'Pending'">Pending</span>
                        </li>

                        <li class="list-group-item d-flex justify-content-between align-items-start py-3 px-4">
                            <div class="d-flex align-items-start me-3">
                                <i class="fas fa-check-circle me-3 fs-5 mt-1" :class="setupChecklist.tabs ? 'text-success' : 'text-muted'"></i>
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">5. Configure Dashboard Call Tabs</h6>
                                    <div class="text-muted small" style="line-height: 1.5;">
                                        Organize how active calls appear to your team on the main Call List page. You can set up separate tabs for different caller queues, study visits, or priorities (such as "Recruitment", "Upcoming Reminders", or "Missed Visits"), and filter which call types show up on each tab.
                                    </div>
                                </div>
                            </div>
                            <span class="badge checklist-badge py-2 fw-semibold flex-shrink-0 ms-3 mt-1" style="width: 130px;" :class="setupChecklist.tabs ? 'bg-success text-white' : 'bg-secondary text-white'" x-text="setupChecklist.tabs ? 'Configured' : 'Pending'">Pending</span>
                        </li>
                    </ul>
                </div>
            </div>

        </div>

        <!-- Tab 2: General & Triggers -->
        <div class="tab-pane fade show active" x-show="activeTab === 'general'">
            <div class="card border shadow-sm mb-4 rounded-3">
                <div class="card-header bg-light border-bottom fw-bold py-3 px-4 text-dark">
                    <i class="fas fa-bolt text-warning me-2"></i> Generation Triggers & Display Options
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark mb-1">Trigger Call Log Generation On Form Save:</label>
                        <div class="setting-blurb mb-3">
                            Select project data entry forms that should trigger automated call generation whenever saved by research staff.
                        </div>
                        <div class="custom-multiselect-container" :class="{ 'is-open': open }" x-data="multiSelect(triggerSave)" @click.outside="open = false">
                            <div class="custom-multiselect-box" :class="{ 'is-open': open }" @click="open = !open">
                                <div class="d-flex flex-wrap align-items-center">
                                    <template x-if="triggerSave.length === 0">
                                        <span class="text-muted small">Select project forms...</span>
                                    </template>
                                    <template x-for="instId in triggerSave" :key="instId">
                                        <span class="multiselect-chip chip-blue">
                                            <span x-text="getInstrumentLabel(instId)"></span>
                                            <i class="fas fa-times close-icon" @click.stop="toggle(instId)"></i>
                                        </span>
                                    </template>
                                </div>
                                <i class="fas fa-chevron-down text-muted small ms-2"></i>
                            </div>
                            <div class="custom-multiselect-menu" x-show="open" x-transition>
                                <template x-for="inst in meta.instruments" :key="inst.id">
                                    <div class="custom-multiselect-item" @click.stop="toggle(inst.id)">
                                        <span x-text="`${inst.label || inst.name} (${inst.id})`"></span>
                                        <i class="fas" :class="isSelected(inst.id) ? 'fa-check-square text-primary fs-5' : 'fa-square text-muted opacity-50 fs-5'"></i>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 border-top pt-3">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input me-2 cursor-pointer" type="checkbox" id="cfg_same_day_mcv_nts" x-model="sameDayMcvNts">
                            <label class="form-check-label fw-bold text-dark cursor-pointer" for="cfg_same_day_mcv_nts">Show Missed/Cancelled Visit (MCV) and Need to Schedule (NTS) Calls Same Day</label>
                        </div>
                        <div class="setting-blurb text-muted small ps-4">
                            By default, generated calls appear starting the day after form save. Toggle this setting ON to present Missed/Cancelled Visit (MCV) and Need to Schedule (NTS) calls immediately on the same day.
                        </div>
                    </div>

                    <div class="mb-4 border-top pt-3">
                        <label class="form-label fw-bold text-dark mb-1" for="cfg_datetime_format">Call List Date/Time Format:</label>
                        <div class="setting-blurb text-muted small mb-3">
                            Specify the date and time format for dates shown on the Call List dashboard (e.g. expiration dates, contact windows, call history). Uses standard PHP date format syntax.
                        </div>
                        <div class="row g-3 align-items-center">
                            <div class="col-12 col-md-6 col-lg-5">
                                <label class="form-label small text-muted fw-semibold mb-1" for="cfg_datetime_format">Format String</label>
                                <input type="text" class="form-control font-monospace" id="cfg_datetime_format" x-model="datetimeFormat" placeholder="m/d/Y g:i A" style="height: 38px; font-size: 0.95rem;">
                            </div>
                            <div class="col-12 col-md-6 col-lg-5">
                                <label class="form-label small text-muted fw-semibold mb-1">Live Preview</label>
                                <div class="form-control bg-light text-dark border font-monospace d-flex align-items-center" style="height: 38px; font-size: 0.95rem; cursor: default; user-select: text;">
                                    <i class="fas fa-eye text-muted me-2 opacity-75"></i>
                                    <span class="fw-semibold text-dark text-truncate" x-text="formatPreview(datetimeFormat)"></span>
                                </div>
                            </div>
                        </div>
                        <div class="text-muted small mt-2" style="font-size: 0.8rem; line-height: 1.5;">
                            <span class="fw-semibold">Common tokens:</span>
                            <code>m</code> (month 01-12), <code>d</code> (day 01-31), <code>Y</code> (year 2026), <code>y</code> (year 26), <code>l</code> (full day, e.g. Sunday), <code>D</code> (short day, e.g. Sun), <code>F</code> (full month, e.g. September), <code>M</code> (short month, e.g. Sep), <code>g</code> (12h 1-12), <code>h</code> (12h 01-12), <code>H</code> (24h 00-23), <code>i</code> (minutes 00-59), <code>A</code> (AM/PM), <code>a</code> (am/pm).
                        </div>
                    </div>

                    <div class="mb-4 border-top pt-3">
                        <label class="form-label fw-bold text-dark mb-1">Participant Display Name Field:</label>
                        <div class="setting-blurb text-muted small mb-3">
                            Select the REDCap field that contains the participant's name (e.g. <code>first_name</code>, <code>full_name</code>, <code>pt_name</code>). When set, this value automatically displays in the standard <strong>Name</strong> column across all Call List tabs.
                        </div>
                        <div style="max-width: 450px;">
                            <div class="searchable-field-select position-relative" :class="{ 'is-open': open }" x-data="fieldSelect($data, 'displayNameField', '-- Select Name Field (Optional) --')" @click.outside="open = false">
                                <div class="form-select form-select-sm d-flex align-items-center justify-content-between cursor-pointer bg-white" @click="open = !open">
                                    <span class="small text-truncate" :class="val ? 'text-dark fw-semibold' : 'text-muted'" x-text="getFieldLabel(val) || placeholder"></span>
                                    <i class="fas fa-search text-muted small ms-2"></i>
                                </div>
                                <div class="searchable-field-menu shadow-lg p-2" x-show="open" x-transition style="display: none;">
                                    <input type="text" class="form-control form-control-sm mb-2" x-model="filter" placeholder="Type to search fields..." @click.stop>
                                    <div class="searchable-field-item text-muted small" @click="select('')">-- No Name Field (Leave Blank) --</div>
                                    <template x-for="f in filteredFields" :key="f.id">
                                        <div class="searchable-field-item small" :class="{ 'active fw-bold text-primary': val === f.id }" @click="select(f.id)">
                                            <span x-text="f.label || `${f.id} (${f.name})`"></span>
                                            <i class="fas fa-check text-primary" x-show="val === f.id"></i>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2 border-top pt-3">
                            <label class="form-label fw-bold text-dark mb-1">Include Call History On Instruments:</label>
                        <div class="setting-blurb mb-3">
                            Choose which data entry instruments will render the interactive Call History widget, giving study staff quick access to caller notes and attempt history directly on participant records.
                        </div>
                        <div class="custom-multiselect-container" :class="{ 'is-open': open }" x-data="multiSelect(callSummary)" @click.outside="open = false">
                            <div class="custom-multiselect-box" :class="{ 'is-open': open }" @click="open = !open">
                                <div class="d-flex flex-wrap align-items-center">
                                    <template x-if="callSummary.length === 0">
                                        <span class="text-muted small">Select project forms...</span>
                                    </template>
                                    <template x-for="instId in callSummary" :key="instId">
                                        <span class="multiselect-chip chip-green">
                                            <span x-text="getInstrumentLabel(instId)"></span>
                                            <i class="fas fa-times close-icon" @click.stop="toggle(instId)"></i>
                                        </span>
                                    </template>
                                </div>
                                <i class="fas fa-chevron-down text-muted small ms-2"></i>
                            </div>
                            <div class="custom-multiselect-menu" x-show="open" x-transition>
                                <template x-for="inst in meta.instruments" :key="inst.id">
                                    <div class="custom-multiselect-item" @click.stop="toggle(inst.id)">
                                        <span x-text="`${inst.label || inst.name} (${inst.id})`"></span>
                                        <i class="fas" :class="isSelected(inst.id) ? 'fa-check-square text-success fs-5' : 'fa-square text-muted opacity-50 fs-5'"></i>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Holiday Skip Rules Card -->
            <div class="card border shadow-sm mb-4 rounded-3">
                <div class="card-header bg-light border-bottom fw-bold py-3 px-4 text-dark">
                    <i class="fas fa-calendar-alt text-primary me-2"></i> Holiday Skip Rules
                </div>
                <div class="card-body p-4">
                    <div class="setting-blurb mb-3">
                        Holiday Skip Rules prevent automated call dates (such as Follow-up windows, Reminders, and Need to Schedule) from landing on days when staff are off. Calculated call dates that fall on enabled holidays or weekends automatically adjust to the preceding, or following, business day.
                    </div>
                    
                    <h6 class="fw-bold text-dark mb-2">Standard US Holidays</h6>
                    <p class="small text-muted mb-3">Select standard holidays to observe for this project:</p>
                    
                    <div class="row g-3 mb-4">
                        <template x-for="(label, key) in defaultHolidaysMap" :key="key">
                            <div class="col-md-6 col-lg-4">
                                <div class="holiday-card" @click="toggleHoliday(key)">
                                    <span class="holiday-title me-2" x-text="label"></span>
                                    <div class="form-check form-switch m-0 p-0 d-flex align-items-center">
                                        <input class="form-check-input" type="checkbox"
                                               :checked="enabledHolidays.includes(String(key))"
                                               @click.stop="toggleHoliday(key)">
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Custom Site Closures & Institutional Holidays</h6>
                            <p class="small text-muted mb-0">Add custom annual site closures (use MM-DD format, e.g. 12-26 for Day After Christmas or 11-03 for Staff Day):</p>
                        </div>
                        <button type="button" @click="addCustomHoliday()" class="btn btn-outline-primary btn-sm px-3 py-1.5 fw-semibold">
                            <i class="fas fa-plus me-1.5"></i> Add Custom Closure
                        </button>
                    </div>
                    <div class="d-flex flex-column gap-3 mt-3">
                        <template x-for="(ch, idx) in customHolidays" :key="idx">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold text-dark mb-1">Date (MM-DD)</label>
                                        <input type="text" class="form-control form-control-sm" x-model="ch.date" placeholder="e.g. 12-26">
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label small fw-semibold text-dark mb-1">Holiday / Closure Name</label>
                                        <input type="text" class="form-control form-control-sm" x-model="ch.name" placeholder="e.g. Winter Recess / Staff Day">
                                    </div>
                                    <div class="col-md-2 text-end pt-3">
                                        <button type="button" @click="removeCustomHoliday(idx)" class="btn btn-outline-danger btn-sm px-3 py-1.5" title="Remove Closure">
                                            <i class="fas fa-trash-alt me-1"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Subject Withdrawal Conditions Card -->
            <div class="card border shadow-sm mb-4 rounded-3">
                <div class="card-header bg-light border-bottom fw-bold py-3 px-4 text-dark">
                    <i class="fas fa-user-slash text-danger me-2"></i> Subject Withdrawal Conditions
                </div>
                <div class="card-body p-4">
                    <div class="setting-blurb mb-3">
                        Configure conditions under which subjects are permanently excluded from the call log. A subject is considered withdrawn when any specified event/field combination contains a <strong>truthy value</strong> (e.g., checked checkbox, non-zero number, or non-empty date).
                    </div>
                    <div class="d-flex flex-column gap-3 mb-3">
                        <template x-for="(rule, idx) in withdrawRules" :key="idx">
                            <div class="d-flex align-items-end gap-3 p-3 bg-light rounded border">
                                <div class="flex-grow-1" style="flex: 1 1 45%;">
                                    <label class="form-label small fw-bold text-dark mb-1">Event</label>
                                    <template x-if="meta.events && meta.events.length === 1">
                                        <select class="form-select form-select-sm bg-light text-muted fw-semibold" disabled>
                                            <option selected>🔒 Only one event exists on this project</option>
                                        </select>
                                    </template>
                                    <template x-if="!meta.events || meta.events.length > 1">
                                        <select class="form-select form-select-sm" x-model="rule.event">
                                            <option value="">-- All Events / Project Default --</option>
                                            <template x-for="evt in meta.events" :key="evt.id">
                                                <option :value="evt.id" x-text="`${evt.name} (${evt.unique})`"></option>
                                            </template>
                                        </select>
                                    </template>
                                </div>
                                <div class="flex-grow-1" style="flex: 1 1 45%;">
                                    <label class="form-label small fw-bold text-dark mb-1">Withdrawal Indicator Field</label>
                                    
                                    <!-- Searchable Field Select Component -->
                                    <div class="searchable-field-select position-relative" :class="{ 'is-open': open }" x-data="fieldSelect(rule, 'var', '-- Select or Type Field --')" @click.outside="open = false">
                                        <div class="form-select form-select-sm d-flex align-items-center justify-content-between cursor-pointer bg-white" @click="open = !open">
                                            <span class="small text-truncate" :class="val ? 'text-dark fw-semibold' : 'text-muted'" x-text="getFieldLabel(val) || placeholder"></span>
                                            <i class="fas fa-search text-muted small ms-2"></i>
                                        </div>
                                        <div class="searchable-field-menu shadow-lg p-2" x-show="open" x-transition>
                                            <input type="text" class="form-control form-control-sm mb-2" x-model="filter" placeholder="Type to search fields..." @click.stop>
                                            <div class="searchable-field-item text-muted small" @click="select('')">-- Clear Selection --</div>
                                            <template x-for="f in filteredFields" :key="f.id">
                                                <div class="searchable-field-item small" :class="{ 'active fw-bold text-primary': val === f.id }" @click="select(f.id)">
                                                    <span x-text="f.label || `${f.id} (${f.name})`"></span>
                                                    <i class="fas fa-check text-primary" x-show="val === f.id"></i>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                </div>
                                <div>
                                    <button type="button" @click="removeWithdrawRule(idx)" class="btn btn-outline-danger btn-sm border-0 px-2 py-1.5" title="Remove Condition">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="addWithdrawRule()" class="btn btn-outline-danger btn-sm px-3 py-1.5 fw-semibold">
                        <i class="fas fa-plus me-1.5"></i> Add Withdrawal Condition
                    </button>
                </div>
            </div>

            <!-- Row Expansion Area Fields Card -->
            <div class="card border shadow-sm mb-4 rounded-3">
                <div class="card-header bg-light border-bottom fw-bold py-3 px-4 text-dark">
                    <i class="fas fa-chevron-down text-info me-2"></i> Row Expansion Area Fields
                </div>
                <div class="card-body p-4">
                    <div class="setting-blurb mb-3">
                        Specify which project fields are displayed when callers click to expand a participant row on the Call List dashboard (e.g., alternate phone numbers, preferred call times, or secondary contact names).
                    </div>
                    <div class="d-flex flex-column gap-3 mb-3">
                        <template x-for="(exp, idx) in expandsFields" :key="idx">
                            <div class="d-flex align-items-end gap-3 p-3 bg-light rounded border">
                                <div class="flex-grow-1" style="flex: 1 1 35%;">
                                    <label class="form-label small fw-bold text-dark mb-1">Field</label>
                                    
                                    <!-- Searchable Field Select -->
                                    <div class="searchable-field-select position-relative" :class="{ 'is-open': open }" x-data="fieldSelect(exp, 'field', '-- Select or Type Field --')" @click.outside="open = false">
                                        <div class="form-select form-select-sm d-flex align-items-center justify-content-between cursor-pointer bg-white" @click="open = !open">
                                            <span class="small text-truncate" :class="val ? 'text-dark fw-semibold' : 'text-muted'" x-text="getFieldLabel(val) || placeholder"></span>
                                            <i class="fas fa-search text-muted small ms-2"></i>
                                        </div>
                                        <div class="searchable-field-menu shadow-lg p-2" x-show="open" x-transition>
                                            <input type="text" class="form-control form-control-sm mb-2" x-model="filter" placeholder="Type to search fields..." @click.stop>
                                            <div class="searchable-field-item text-muted small" @click="select('')">-- Clear Selection --</div>
                                            <template x-for="f in filteredFields" :key="f.id">
                                                <div class="searchable-field-item small" :class="{ 'active fw-bold text-primary': val === f.id }" @click="select(f.id)">
                                                    <span x-text="f.label || `${f.id} (${f.name})`"></span>
                                                    <i class="fas fa-check text-primary" x-show="val === f.id"></i>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                </div>
                                <div class="flex-grow-1" style="flex: 1 1 35%;">
                                    <label class="form-label small fw-bold text-dark mb-1">Display Label</label>
                                    <input type="text" class="form-control form-control-sm" x-model="exp.name" placeholder="e.g. Alternate Phone">
                                </div>
                                <div class="flex-grow-1" style="flex: 1 1 25%;">
                                    <label class="form-label small fw-bold text-dark mb-1">Default Value</label>
                                    <input type="text" class="form-control form-control-sm" x-model="exp.default" placeholder="e.g. N/A">
                                </div>
                                <div>
                                    <button type="button" @click="removeExpandsField(idx)" class="btn btn-outline-danger btn-sm border-0 px-2 py-1.5" title="Remove Field">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="addExpandsField()" class="btn btn-outline-primary btn-sm px-3 py-1.5 fw-semibold">
                        <i class="fas fa-plus me-1.5"></i> Add Expansion Field
                    </button>
                </div>
            </div>
        </div>

        <!-- Tab 3: Call Types -->
        <div class="tab-pane fade show active" x-show="activeTab === 'calls'">
            <div class="setting-blurb mb-3 w-100" style="max-width: 100%;">
                Define the unique call rules that govern your study outreach queues. Each call type represents a distinct contact protocol—such as welcoming new participants, sending appointment reminders, conducting windowed follow-ups, re-engaging missed visits, scheduling milestone encounters, or handling adhoc calls.
                <div class="mt-2 text-muted small">
                    <i class="fas fa-info-circle text-primary me-1"></i>
                    <strong>Multi-Event Architecture:</strong> For longitudinal projects, call types configured across multiple events are automatically scoped by event (e.g. <code>call_id|event_name</code>), allowing each study visit to maintain its own independent schedule, calling window, attempt history, and completion status.
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-dark">Unique Call Types</h5>
                <button type="button" @click="addCallType()" class="btn btn-primary btn-sm px-3 py-2 shadow-sm fw-bold">
                    <i class="fas fa-plus me-1.5"></i> Add Call Type
                </button>
            </div>

            <div class="d-flex flex-column gap-4 mb-4">
                <template x-for="(callType, index) in callTypes" :key="callType._uid || index">
                    <div class="card border shadow-sm rounded-3">
                        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center py-2.5 px-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary px-2 py-1 fs-7 fw-semibold" x-text="`Type #${index + 1}`"></span>
                                <span class="fw-bold text-dark fs-6" x-text="`${callType.id || 'call_1'} - ${callType.name || 'New Call Type'}`"></span>
                            </div>
                            <button type="button" @click="removeCallType(index)" class="btn btn-outline-danger btn-sm px-2 py-1.5">
                                <i class="fas fa-trash-alt me-1"></i> Remove Call Type
                            </button>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark mb-1">Call ID</label>
                                    <input type="text" class="form-control form-control-sm" x-model="callType.id" placeholder="e.g. call_1">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark mb-1">Call Name</label>
                                    <input type="text" class="form-control form-control-sm" x-model="callType.name" placeholder="e.g. Baseline Follow-up">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-dark mb-1" title="Ongoing call auto-expires after this time">Call Length</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control form-control-sm" x-model="callType.expectedDuration" placeholder="30" min="1" max="240">
                                        <span class="input-group-text small text-muted">min</span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-dark mb-1">Hide Attempts</label>
                                    <input type="number" class="form-control form-control-sm" x-model="callType.hideAfterAttempt" placeholder="e.g. 5">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-dark mb-1">Template Type</label>
                                    <select class="form-select form-select-sm" x-model="callType.template">
                                        <template x-for="(lbl, val) in meta.callTemplateOptions" :key="val">
                                            <option :value="val" x-text="lbl"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>

                            <!-- Template Extra Rules -->
                            <div class="mt-3 border-top pt-3">
                                <!-- New Entry -->
                                <div x-show="callType.template === 'new'">
                                    <div class="setting-blurb mb-3 w-100" style="max-width: 100%;">
                                        <strong>Purpose:</strong> Designed for initial participant outreach, screening welcomes, or intake onboarding calls upon study registration.
                                        <div class="mt-1 text-muted small">
                                            <strong>How it works:</strong> Triggers automatically when a new participant record is created or imported (while call metadata is empty). The call remains active on dashboard queues until completed by a caller, or until the configured number of expiration days elapses (leave blank if calls should never expire; enter <code>0</code> to expire the same day created). Call List tabs containing New Entry calls automatically display an <em>Expiration Date</em> column showing the exact date and days remaining (or <em>No Expiration</em> if left blank).
                                        </div>
                                    </div>
                                    <div class="row g-3 align-items-center mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">Days Until Expire</label>
                                            <input type="number" class="form-control form-control-sm" x-model="callType.newExpireDays" placeholder="e.g. 30">
                                        </div>
                                    </div>
                                </div>

                                <!-- Reminder -->
                                <div x-show="callType.template === 'reminder'">
                                    <div class="setting-blurb mb-3 w-100" style="max-width: 100%;">
                                        <strong>Purpose:</strong> Pre-visit appointment reminders to ensure participant attendance and preparation.
                                        <div class="mt-1 text-muted small">
                                            <strong>How it works:</strong> Monitors the configured appointment date field across selected events. Using study holiday and weekend rules, it calculates the call window start date (<code>Appointment Date - Days Before</code>). The call stays hidden until that window opens. If the reminder is open (not completed) and the appointment date arrives or passes, the call is marked <strong>expired</strong>. If the appointment date is cleared before any calls are logged, the reminder is removed; if calls were already logged, it completes to preserve history.
                                        </div>
                                    </div>
                                    <div class="row g-3 align-items-start mb-2">
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold text-dark mb-1">Target Date Field</label>
                                            <div class="searchable-field-select position-relative" :class="{ 'is-open': open }" x-data="fieldSelect(callType, 'reminderVariable', '-- Select Date Field --', true)" @click.outside="open = false">
                                                <div class="form-select form-select-sm d-flex align-items-center justify-content-between cursor-pointer bg-white" @click="open = !open">
                                                    <span class="small text-truncate" :class="val ? 'text-dark fw-semibold' : 'text-muted'" x-text="getFieldLabel(val) || placeholder"></span>
                                                    <i class="fas fa-search text-muted small ms-2"></i>
                                                </div>
                                                <div class="searchable-field-menu shadow-lg p-2" x-show="open" x-transition>
                                                    <input type="text" class="form-control form-control-sm mb-2" x-model="filter" placeholder="Type to search date fields..." @click.stop>
                                                    <div class="searchable-field-item text-muted small" @click="select('')">-- Clear Selection --</div>
                                                    <template x-for="f in filteredFields" :key="f.id">
                                                        <div class="searchable-field-item small" :class="{ 'active fw-bold text-primary': val === f.id }" @click="select(f.id)">
                                                            <span x-text="f.label || `${f.id} (${f.name})`"></span>
                                                            <i class="fas fa-check text-primary" x-show="val === f.id"></i>
                                                        </div>
                                                    </template>
                                                    <div class="p-2 text-muted small text-center" x-show="filteredFields.length === 0">
                                                        No matching date fields found
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold text-dark mb-1">Days Before Date</label>
                                            <input type="number" class="form-control form-control-sm" x-model="callType.reminderDays" placeholder="e.g. 3">
                                        </div>
                                        <div class="col-md-4" x-data="eventSelect(callType.reminderEvents)">
                                            <label class="form-label small fw-bold text-dark mb-1">Include Events</label>
                                            <template x-if="singleEvent">
                                                <select class="form-select form-select-sm bg-light text-muted fw-semibold" disabled>
                                                    <option selected>🔒 Only one event exists on this project</option>
                                                </select>
                                            </template>
                                            <template x-if="!singleEvent">
                                                <div class="custom-multiselect-container position-relative" :class="{ 'is-open': open }" @click.outside="open = false">
                                                    <div class="custom-multiselect-box" :class="{ 'is-open': open }" @click="open = !open">
                                                        <div class="d-flex flex-wrap align-items-center">
                                                            <template x-if="!callType.reminderEvents || callType.reminderEvents.length === 0">
                                                                <span class="text-muted small">Select Events...</span>
                                                            </template>
                                                            <template x-for="eId in callType.reminderEvents" :key="eId">
                                                                <span class="multiselect-chip chip-blue">
                                                                    <span x-text="getEventLabel(eId)"></span>
                                                                    <i class="fas fa-times close-icon" @click.stop="toggle(eId)"></i>
                                                                </span>
                                                            </template>
                                                        </div>
                                                        <i class="fas fa-chevron-down text-muted small ms-2"></i>
                                                    </div>
                                                    <div class="custom-multiselect-menu" x-show="open" x-transition>
                                                        <template x-for="evt in meta.events" :key="evt.id">
                                                            <div class="custom-multiselect-item" @click.stop="toggle(evt.id)">
                                                                <span x-text="`${evt.name} (${evt.unique})`"></span>
                                                                <i class="fas" :class="isSelected(evt.id) ? 'fa-check-square text-primary fs-5' : 'fa-square text-muted opacity-50 fs-5'"></i>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Follow Up -->
                                <div x-show="callType.template === 'followup'">
                                    <div class="setting-blurb mb-3 w-100" style="max-width: 100%;">
                                        <strong>Purpose:</strong> Post-encounter check-ins, adverse event tracking, or survey administration that must occur within an eligibility window relative to an anchor date.
                                        <div class="mt-1 text-muted small">
                                            <strong>How it works:</strong> Watches the configured <em>Anchor Date Field</em> on each selected event. It calculates a calling window starting <code>Anchor Date + Days After Date</code> and ending after the configured window length. Tabs containing Follow Up calls automatically display <em>Start Calling</em> and <em>Complete By</em> columns. If auto-remove is enabled, calls automatically expire once the window closes.
                                        </div>
                                    </div>
                                    <div class="row g-3 align-items-start mb-2">
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold text-dark mb-1">Anchor Date Field</label>
                                            <div class="searchable-field-select position-relative" :class="{ 'is-open': open }" x-data="fieldSelect(callType, 'followupDate', '-- Select Date Field --', true)" @click.outside="open = false">
                                                <div class="form-select form-select-sm d-flex align-items-center justify-content-between cursor-pointer bg-white" @click="open = !open">
                                                    <span class="small text-truncate" :class="val ? 'text-dark fw-semibold' : 'text-muted'" x-text="getFieldLabel(val) || placeholder"></span>
                                                    <i class="fas fa-search text-muted small ms-2"></i>
                                                </div>
                                                <div class="searchable-field-menu shadow-lg p-2" x-show="open" x-transition>
                                                    <input type="text" class="form-control form-control-sm mb-2" x-model="filter" placeholder="Type to search date fields..." @click.stop>
                                                    <div class="searchable-field-item text-muted small" @click="select('')">-- Clear Selection --</div>
                                                    <template x-for="f in filteredFields" :key="f.id">
                                                        <div class="searchable-field-item small" :class="{ 'active fw-bold text-primary': val === f.id }" @click="select(f.id)">
                                                            <span x-text="f.label || `${f.id} (${f.name})`"></span>
                                                            <i class="fas fa-check text-primary" x-show="val === f.id"></i>
                                                        </div>
                                                    </template>
                                                    <div class="p-2 text-muted small text-center" x-show="filteredFields.length === 0">
                                                        No matching date fields found
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold text-dark mb-1">Days After Date</label>
                                            <input type="number" class="form-control form-control-sm" x-model="callType.followupDays" placeholder="e.g. 7">
                                        </div>
                                        <div class="col-md-4" x-data="eventSelect(callType.followupEvents)">
                                            <label class="form-label small fw-bold text-dark mb-1">Include Events</label>
                                            <template x-if="singleEvent">
                                                <select class="form-select form-select-sm bg-light text-muted fw-semibold" disabled>
                                                    <option selected>🔒 Only one event exists on this project</option>
                                                </select>
                                            </template>
                                            <template x-if="!singleEvent">
                                                <div class="custom-multiselect-container position-relative" :class="{ 'is-open': open }" @click.outside="open = false">
                                                    <div class="custom-multiselect-box" :class="{ 'is-open': open }" @click="open = !open">
                                                        <div class="d-flex flex-wrap align-items-center">
                                                            <template x-if="!callType.followupEvents || callType.followupEvents.length === 0">
                                                                <span class="text-muted small">Select Events...</span>
                                                            </template>
                                                            <template x-for="eId in callType.followupEvents" :key="eId">
                                                                <span class="multiselect-chip chip-blue">
                                                                    <span x-text="getEventLabel(eId)"></span>
                                                                    <i class="fas fa-times close-icon" @click.stop="toggle(eId)"></i>
                                                                </span>
                                                            </template>
                                                        </div>
                                                        <i class="fas fa-chevron-down text-muted small ms-2"></i>
                                                    </div>
                                                    <div class="custom-multiselect-menu" x-show="open" x-transition>
                                                        <template x-for="evt in meta.events" :key="evt.id">
                                                            <div class="custom-multiselect-item" @click.stop="toggle(evt.id)">
                                                                <span x-text="`${evt.name} (${evt.unique})`"></span>
                                                                <i class="fas" :class="isSelected(evt.id) ? 'fa-check-square text-primary fs-5' : 'fa-square text-muted opacity-50 fs-5'"></i>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- MCV -->
                                <div x-show="callType.template === 'mcv'">
                                    <div class="setting-blurb mb-3 w-100" style="max-width: 100%;">
                                        <strong>Purpose:</strong> Rapid outreach for participants who missed, cancelled, or no-showed an appointment.
                                        <div class="mt-1 text-muted small">
                                            <strong>How it works:</strong> Monitors the appointment date field and designated indicator field. A call generates if the missed/cancelled indicator is flagged, or automatically if the appointment date has passed without the indicator being set (where the indicator flags attendance). By default, calls appear starting the day after the missed visit (giving clinics time to reschedule), unless same-day display is enabled. When attendance is confirmed or the missed flag is cleared, the call automatically completes.
                                        </div>
                                    </div>
                                    <div class="row g-3 align-items-start mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">Indicator Field</label>
                                            <div class="searchable-field-select position-relative" :class="{ 'is-open': open }" x-data="fieldSelect(callType, 'mcvIndicator', '-- Select Indicator Field --')" @click.outside="open = false">
                                                <div class="form-select form-select-sm d-flex align-items-center justify-content-between cursor-pointer bg-white" @click="open = !open">
                                                    <span class="small text-truncate" :class="val ? 'text-dark fw-semibold' : 'text-muted'" x-text="getFieldLabel(val) || placeholder"></span>
                                                    <i class="fas fa-search text-muted small ms-2"></i>
                                                </div>
                                                <div class="searchable-field-menu shadow-lg p-2" x-show="open" x-transition>
                                                    <input type="text" class="form-control form-control-sm mb-2" x-model="filter" placeholder="Type to search fields..." @click.stop>
                                                    <div class="searchable-field-item text-muted small" @click="select('')">-- Clear Selection --</div>
                                                    <template x-for="f in filteredFields" :key="f.id">
                                                        <div class="searchable-field-item small" :class="{ 'active fw-bold text-primary': val === f.id }" @click="select(f.id)">
                                                            <span x-text="f.label || `${f.id} (${f.name})`"></span>
                                                            <i class="fas fa-check text-primary" x-show="val === f.id"></i>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">Appointment Date Field</label>
                                            <div class="searchable-field-select position-relative" :class="{ 'is-open': open }" x-data="fieldSelect(callType, 'mcvDate', '-- Select Date Field --', true)" @click.outside="open = false">
                                                <div class="form-select form-select-sm d-flex align-items-center justify-content-between cursor-pointer bg-white" @click="open = !open">
                                                    <span class="small text-truncate" :class="val ? 'text-dark fw-semibold' : 'text-muted'" x-text="getFieldLabel(val) || placeholder"></span>
                                                    <i class="fas fa-search text-muted small ms-2"></i>
                                                </div>
                                                <div class="searchable-field-menu shadow-lg p-2" x-show="open" x-transition>
                                                    <input type="text" class="form-control form-control-sm mb-2" x-model="filter" placeholder="Type to search date fields..." @click.stop>
                                                    <div class="searchable-field-item text-muted small" @click="select('')">-- Clear Selection --</div>
                                                    <template x-for="f in filteredFields" :key="f.id">
                                                        <div class="searchable-field-item small" :class="{ 'active fw-bold text-primary': val === f.id }" @click="select(f.id)">
                                                            <span x-text="f.label || `${f.id} (${f.name})`"></span>
                                                            <i class="fas fa-check text-primary" x-show="val === f.id"></i>
                                                        </div>
                                                    </template>
                                                    <div class="p-2 text-muted small text-center" x-show="filteredFields.length === 0">
                                                        No matching date fields found
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4" x-data="eventSelect(callType.mcvEvents)">
                                            <label class="form-label small fw-bold text-dark mb-1">Include Events</label>
                                            <template x-if="singleEvent">
                                                <select class="form-select form-select-sm bg-light text-muted fw-semibold" disabled>
                                                    <option selected>🔒 Only one event exists on this project</option>
                                                </select>
                                            </template>
                                            <template x-if="!singleEvent">
                                                <div class="custom-multiselect-container position-relative" :class="{ 'is-open': open }" @click.outside="open = false">
                                                    <div class="custom-multiselect-box" :class="{ 'is-open': open }" @click="open = !open">
                                                        <div class="d-flex flex-wrap align-items-center">
                                                            <template x-if="!callType.mcvEvents || callType.mcvEvents.length === 0">
                                                                <span class="text-muted small">Select Events...</span>
                                                            </template>
                                                            <template x-for="eId in callType.mcvEvents" :key="eId">
                                                                <span class="multiselect-chip chip-blue">
                                                                    <span x-text="getEventLabel(eId)"></span>
                                                                    <i class="fas fa-times close-icon" @click.stop="toggle(eId)"></i>
                                                                </span>
                                                            </template>
                                                        </div>
                                                        <i class="fas fa-chevron-down text-muted small ms-2"></i>
                                                    </div>
                                                    <div class="custom-multiselect-menu" x-show="open" x-transition>
                                                        <template x-for="evt in meta.events" :key="evt.id">
                                                            <div class="custom-multiselect-item" @click.stop="toggle(evt.id)">
                                                                <span x-text="`${evt.name} (${evt.unique})`"></span>
                                                                <i class="fas" :class="isSelected(evt.id) ? 'fa-check-square text-primary fs-5' : 'fa-square text-muted opacity-50 fs-5'"></i>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- NTS -->
                                <div x-show="callType.template === 'nts'">
                                    <div class="setting-blurb mb-3 w-100" style="max-width: 100%;">
                                        <strong>Purpose:</strong> Scheduling milestone study encounters as participants progress sequentially through protocol events.
                                        <div class="mt-1 text-muted small">
                                            <strong>How it works:</strong> Evaluates the chronological event sequence in your project. When a prior milestone event has its attendance indicator flagged truthy, but the subsequent target event does not yet have an appointment scheduled, an NTS call is automatically generated for that target event. It appears next day (or same day if enabled), and auto-completes immediately once staff enter a date into the target appointment date field.
                                        </div>
                                    </div>
                                    <div class="row g-3 align-items-start mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">Indicator Field</label>
                                            <div class="searchable-field-select position-relative" :class="{ 'is-open': open }" x-data="fieldSelect(callType, 'ntsIndicator', '-- Select Indicator Field --')" @click.outside="open = false">
                                                <div class="form-select form-select-sm d-flex align-items-center justify-content-between cursor-pointer bg-white" @click="open = !open">
                                                    <span class="small text-truncate" :class="val ? 'text-dark fw-semibold' : 'text-muted'" x-text="getFieldLabel(val) || placeholder"></span>
                                                    <i class="fas fa-search text-muted small ms-2"></i>
                                                </div>
                                                <div class="searchable-field-menu shadow-lg p-2" x-show="open" x-transition>
                                                    <input type="text" class="form-control form-control-sm mb-2" x-model="filter" placeholder="Type to search fields..." @click.stop>
                                                    <div class="searchable-field-item text-muted small" @click="select('')">-- Clear Selection --</div>
                                                    <template x-for="f in filteredFields" :key="f.id">
                                                        <div class="searchable-field-item small" :class="{ 'active fw-bold text-primary': val === f.id }" @click="select(f.id)">
                                                            <span x-text="f.label || `${f.id} (${f.name})`"></span>
                                                            <i class="fas fa-check text-primary" x-show="val === f.id"></i>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">Target Date Field</label>
                                            <div class="searchable-field-select position-relative" :class="{ 'is-open': open }" x-data="fieldSelect(callType, 'ntsDate', '-- Select Date Field --', true)" @click.outside="open = false">
                                                <div class="form-select form-select-sm d-flex align-items-center justify-content-between cursor-pointer bg-white" @click="open = !open">
                                                    <span class="small text-truncate" :class="val ? 'text-dark fw-semibold' : 'text-muted'" x-text="getFieldLabel(val) || placeholder"></span>
                                                    <i class="fas fa-search text-muted small ms-2"></i>
                                                </div>
                                                <div class="searchable-field-menu shadow-lg p-2" x-show="open" x-transition>
                                                    <input type="text" class="form-control form-control-sm mb-2" x-model="filter" placeholder="Type to search date fields..." @click.stop>
                                                    <div class="searchable-field-item text-muted small" @click="select('')">-- Clear Selection --</div>
                                                    <template x-for="f in filteredFields" :key="f.id">
                                                        <div class="searchable-field-item small" :class="{ 'active fw-bold text-primary': val === f.id }" @click="select(f.id)">
                                                            <span x-text="f.label || `${f.id} (${f.name})`"></span>
                                                            <i class="fas fa-check text-primary" x-show="val === f.id"></i>
                                                        </div>
                                                    </template>
                                                    <div class="p-2 text-muted small text-center" x-show="filteredFields.length === 0">
                                                        No matching date fields found
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4" x-data="eventSelect(callType.ntsEvents)">
                                            <label class="form-label small fw-bold text-dark mb-1">Include Events</label>
                                            <template x-if="singleEvent">
                                                <select class="form-select form-select-sm bg-light text-muted fw-semibold" disabled>
                                                    <option selected>🔒 Only one event exists on this project</option>
                                                </select>
                                            </template>
                                            <template x-if="!singleEvent">
                                                <div class="custom-multiselect-container position-relative" :class="{ 'is-open': open }" @click.outside="open = false">
                                                    <div class="custom-multiselect-box" :class="{ 'is-open': open }" @click="open = !open">
                                                        <div class="d-flex flex-wrap align-items-center">
                                                            <template x-if="!callType.ntsEvents || callType.ntsEvents.length === 0">
                                                                <span class="text-muted small">Select Events...</span>
                                                            </template>
                                                            <template x-for="eId in callType.ntsEvents" :key="eId">
                                                                <span class="multiselect-chip chip-blue">
                                                                    <span x-text="getEventLabel(eId)"></span>
                                                                    <i class="fas fa-times close-icon" @click.stop="toggle(eId)"></i>
                                                                </span>
                                                            </template>
                                                        </div>
                                                        <i class="fas fa-chevron-down text-muted small ms-2"></i>
                                                    </div>
                                                    <div class="custom-multiselect-menu" x-show="open" x-transition>
                                                        <template x-for="evt in meta.events" :key="evt.id">
                                                            <div class="custom-multiselect-item" @click.stop="toggle(evt.id)">
                                                                <span x-text="`${evt.name} (${evt.unique})`"></span>
                                                                <i class="fas" :class="isSelected(evt.id) ? 'fa-check-square text-primary fs-5' : 'fa-square text-muted opacity-50 fs-5'"></i>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Adhoc -->
                                <div x-show="callType.template === 'adhoc'">
                                    <div class="setting-blurb mb-3 w-100" style="max-width: 100%;">
                                        <strong>Purpose:</strong> Unplanned, participant-initiated, or ad-hoc outreach (e.g. medication questions, coordinator callbacks, lost-to-followup re-engagement).
                                        <div class="mt-1 text-muted small">
                                            <strong>How it works:</strong> Created manually by study team members via the <em>New Adhoc Call</em> button or programmatically via the external API (<code>action: newAdhoc</code>). Callers can schedule a future callback date/time, which keeps the call hidden until that time arrives. Tabs with adhoc calls show dedicated <em>Reason</em> and <em>Preferred Callback</em> columns.
                                        </div>
                                    </div>
                                    <div class="row g-3 align-items-start mb-2">
                                        <div class="col-md-8">
                                            <label class="form-label small fw-bold text-dark mb-1">Adhoc Reason Code Map</label>
                                            <textarea class="form-control form-control-sm font-monospace" rows="4" x-model="callType.adhocReason" placeholder="1, General&#10;2, Followup&#10;3, Urgent"></textarea>
                                            <small class="text-muted d-block mt-1">Format: <code>code, Label Name</code> (one reason per line).</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Visit -->
                                <div x-show="callType.template === 'visit'">
                                    <div class="setting-blurb mb-3 w-100" style="max-width: 100%;">
                                        <strong>Purpose:</strong> Protocol-mandated remote phone encounters (e.g. 6-month phone check-ins) where call notes are logged directly during encounter checkout.
                                        <div class="mt-1 text-muted small">
                                            <strong>How it works:</strong> Generates when a designated visit indicator field is marked truthy. The visit indicator is typically a "Digital check-in" field or form completion used to generate the log. If an optional auto-remove date field is configured, the call will automatically expire when that date passes; otherwise it completes when logged by staff.
                                        </div>
                                    </div>
                                    <div class="row g-3 align-items-start mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-dark mb-1">Visit Indicator Field</label>
                                            <div class="searchable-field-select position-relative" :class="{ 'is-open': open }" x-data="fieldSelect(callType, 'visitIndicator', '-- Select Indicator Field --')" @click.outside="open = false">
                                                <div class="form-select form-select-sm d-flex align-items-center justify-content-between cursor-pointer bg-white" @click="open = !open">
                                                    <span class="small text-truncate" :class="val ? 'text-dark fw-semibold' : 'text-muted'" x-text="getFieldLabel(val) || placeholder"></span>
                                                    <i class="fas fa-search text-muted small ms-2"></i>
                                                </div>
                                                <div class="searchable-field-menu shadow-lg p-2" x-show="open" x-transition>
                                                    <input type="text" class="form-control form-control-sm mb-2" x-model="filter" placeholder="Type to search fields..." @click.stop>
                                                    <div class="searchable-field-item text-muted small" @click="select('')">-- Clear Selection --</div>
                                                    <template x-for="f in filteredFields" :key="f.id">
                                                        <div class="searchable-field-item small" :class="{ 'active fw-bold text-primary': val === f.id }" @click="select(f.id)">
                                                            <span x-text="f.label || `${f.id} (${f.name})`"></span>
                                                            <i class="fas fa-check text-primary" x-show="val === f.id"></i>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6" x-data="eventSelect(callType.visitEvents)">
                                            <label class="form-label small fw-bold text-dark mb-1">Include Events</label>
                                            <template x-if="singleEvent">
                                                <select class="form-select form-select-sm bg-light text-muted fw-semibold" disabled>
                                                    <option selected>🔒 Only one event exists on this project</option>
                                                </select>
                                            </template>
                                            <template x-if="!singleEvent">
                                                <div class="custom-multiselect-container position-relative" :class="{ 'is-open': open }" @click.outside="open = false">
                                                    <div class="custom-multiselect-box" :class="{ 'is-open': open }" @click="open = !open">
                                                        <div class="d-flex flex-wrap align-items-center">
                                                            <template x-if="!callType.visitEvents || callType.visitEvents.length === 0">
                                                                <span class="text-muted small">Select Events...</span>
                                                            </template>
                                                            <template x-for="eId in callType.visitEvents" :key="eId">
                                                                <span class="multiselect-chip chip-blue">
                                                                    <span x-text="getEventLabel(eId)"></span>
                                                                    <i class="fas fa-times close-icon" @click.stop="toggle(eId)"></i>
                                                                </span>
                                                            </template>
                                                        </div>
                                                        <i class="fas fa-chevron-down text-muted small ms-2"></i>
                                                    </div>
                                                    <div class="custom-multiselect-menu" x-show="open" x-transition>
                                                        <template x-for="evt in meta.events" :key="evt.id">
                                                            <div class="custom-multiselect-item" @click.stop="toggle(evt.id)">
                                                                <span x-text="`${evt.name} (${evt.unique})`"></span>
                                                                <i class="fas" :class="isSelected(evt.id) ? 'fa-check-square text-primary fs-5' : 'fa-square text-muted opacity-50 fs-5'"></i>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Call Script Section -->
                            <div class="call-script-section mt-4">
                                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                                    <div>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas fa-file-alt text-primary fs-6"></i>
                                            <span class="fw-bold text-dark fs-6">Call Script</span>
                                            <span class="badge bg-light text-secondary border px-2 py-0.5 small fw-semibold">Rich Text</span>
                                        </div>
                                        <div class="text-muted small mt-0.5">
                                            Rich text talking points, questions, and protocol reminders displayed to callers. Supports custom tags, smart variables, and piping.
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button" 
                                                @click="confirmLoadStarterScript(callType)" 
                                                class="btn-script-action btn-script-starter" 
                                                title="Load standard starter script for this template">
                                            <i class="fas fa-magic"></i>
                                            <span>Starter Script</span>
                                        </button>
                                        <button type="button" 
                                                @click="callType.guideOpen = !callType.guideOpen" 
                                                class="btn-script-action btn-script-guide" 
                                                :class="{ 'active': callType.guideOpen }">
                                            <i class="fas" :class="callType.guideOpen ? 'fa-book-open' : 'fa-info-circle'"></i>
                                            <span x-text="callType.guideOpen ? 'Hide Tag Guide' : 'Tag Guide'"></span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Variable Insertion Toolbar -->
                                <div class="call-script-toolbar">
                                    <span class="small fw-bold text-secondary me-1 text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.04em;">Insert Tag:</span>
                                    
                                    <!-- Custom Call Log Pipes (Double Braces) -->
                                    <span class="script-tag-chip chip-custom" @click="insertScriptTag(callType, '{{participant_name}}')" title="Participant Name">
                                        {{participant_name}}
                                    </span>
                                    <span class="script-tag-chip chip-custom" @click="insertScriptTag(callType, '{{call_name}}')" title="Call Type Name">
                                        {{call_name}}
                                    </span>
                                    <span class="script-tag-chip chip-custom" @click="insertScriptTag(callType, '{{call_date}}')" title="Target / Scheduled Date">
                                        {{call_date}}
                                    </span>
                                    <span class="script-tag-chip chip-custom" @click="insertScriptTag(callType, '{{call_time}}')" title="Target / Scheduled Time">
                                        {{call_time}}
                                    </span>
                                    <span class="script-tag-chip chip-custom" @click="insertScriptTag(callType, '{{expected_duration}}')" title="Call Length in Minutes">
                                        {{expected_duration}}
                                    </span>
                                    <span class="script-tag-chip chip-custom" @click="insertScriptTag(callType, '{{attempt_num}}')" title="Current Call Attempt Number">
                                        {{attempt_num}}
                                    </span>
                                    <span class="script-tag-chip chip-custom" @click="insertScriptTag(callType, '{{reason}}')" title="Adhoc Selected Reason">
                                        {{reason}}
                                    </span>

                                    <div class="vr my-1 mx-1 opacity-25"></div>

                                    <!-- REDCap Smart Variables (Single Brackets) -->
                                    <span class="script-tag-chip chip-smart" @click="insertScriptTag(callType, '[user-fullname]')" title="REDCap Smart Variable: Logged in user's full name">
                                        [user-fullname]
                                    </span>
                                    <span class="script-tag-chip chip-smart" @click="insertScriptTag(callType, '[record-name]')" title="REDCap Smart Variable: Record ID">
                                        [record-name]
                                    </span>
                                    <span class="script-tag-chip chip-smart" @click="insertScriptTag(callType, '[event-name]')" title="REDCap Smart Variable: Current event name">
                                        [event-name]
                                    </span>
                                    <span class="script-tag-chip chip-smart" @click="insertScriptTag(callType, '[project-id]')" title="REDCap Smart Variable: Project ID">
                                        [project-id]
                                    </span>

                                    <div class="vr my-1 mx-1 opacity-25"></div>

                                    <!-- Searchable Project Field Dropdown -->
                                    <div class="position-relative d-inline-block" @click.outside="callType.fieldPickerOpen = false">
                                        <button type="button" 
                                                class="chip-field-btn"
                                                @click="callType.fieldPickerOpen = !callType.fieldPickerOpen">
                                            <i class="fas fa-plus-circle me-1"></i> Project Field...
                                            <i class="fas fa-caret-down ms-1.5 opacity-75"></i>
                                        </button>
                                        <div class="searchable-field-menu shadow-lg p-2" 
                                             x-show="callType.fieldPickerOpen" 
                                             x-transition 
                                             style="position: absolute; top: 100%; left: 0; z-index: 1050; min-width: 260px; max-width: 320px; max-height: 280px; overflow-y: auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px;">
                                            <input type="text" 
                                                   class="form-control form-control-sm mb-2" 
                                                   x-model="callType.fieldPickerFilter" 
                                                   placeholder="Search project fields..." 
                                                   @click.stop>
                                            <template x-for="f in (meta.fields || []).filter(field => !callType.fieldPickerFilter || (field.label || field.id).toLowerCase().includes(callType.fieldPickerFilter.toLowerCase()))" :key="f.id">
                                                <div class="searchable-field-item small py-1 px-2 cursor-pointer hover-bg-light rounded text-truncate" 
                                                     @click="insertScriptTag(callType, '[' + f.id + ']'); callType.fieldPickerOpen = false;"
                                                     :title="f.name || f.id">
                                                    <span class="fw-bold font-monospace text-primary" x-text="`[${f.id}]`"></span>
                                                    <span class="text-muted ms-1" x-text="f.name ? `- ${f.name}` : ''"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Collapsible Tag Reference Guide -->
                                <div class="call-script-guide-card mb-3" x-show="callType.guideOpen" x-transition>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-book-open text-primary me-1.5"></i> Script Piping & Tag Reference Guide</h6>
                                        <button type="button" @click="callType.guideOpen = false" class="btn btn-sm btn-link text-muted p-0 text-decoration-none">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <span class="fw-bold text-dark d-block mb-1 small">Custom Call Log Tags (<code>{{variable}}</code>)</span>
                                            <table class="call-script-guide-table">
                                                <thead>
                                                    <tr>
                                                        <th>Tag</th>
                                                        <th>Description</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td><code>{{participant_name}}</code></td>
                                                        <td>Participant's full name from configured display field</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>{{call_name}}</code></td>
                                                        <td>Call type display name (e.g. Baseline Follow-up)</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>{{call_date}}</code> / <code>{{call_time}}</code></td>
                                                        <td>Target appointment or scheduled date & time</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>{{expected_duration}}</code></td>
                                                        <td>Expected duration in minutes (e.g. 30)</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>{{attempt_num}}</code></td>
                                                        <td>Ordinal attempt number for active call (e.g. 1st, 2nd)</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>{{reason}}</code></td>
                                                        <td>Selected reason description for adhoc calls</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <span class="fw-bold text-dark d-block mb-1 small">REDCap Piping & Smart Variables</span>
                                            <table class="call-script-guide-table">
                                                <thead>
                                                    <tr>
                                                        <th>Syntax</th>
                                                        <th>Description</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td><code>[user-fullname]</code></td>
                                                        <td>Logged-in REDCap user's full name</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>[record-name]</code></td>
                                                        <td>Current participant record identifier</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>[event-name]</code></td>
                                                        <td>Current event label or arm identifier</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>[project-id]</code></td>
                                                        <td>REDCap Project ID number</td>
                                                    </tr>
                                                    <tr>
                                                        <td><code>[field_name]</code></td>
                                                        <td>Any field piped from the participant's record</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <div class="alert alert-light border py-1.5 px-2 mt-2 mb-0 small text-muted">
                                                <i class="fas fa-lightbulb text-warning me-1"></i>
                                                <strong>Tip:</strong> Use the rich text toolbar to format talking points with bold text, bulleted lists, and colors for critical questions.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- TinyMCE Textarea -->
                                <textarea :id="`call_script_${callType._uid || index}`"
                                          class="form-control form-control-sm call-script-editor"
                                          style="height: 220px; width: 100%;"
                                          x-model="callType.script"
                                          placeholder="Enter talking points, greetings, or instructions for this call type..."></textarea>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Tab 4: Dashboard Tabs -->
        <div class="tab-pane fade show active" x-show="activeTab === 'dashboard'">
            <div class="setting-blurb mb-3">
                Configure segmented tabs for the main Call List dashboard. Each tab presents specific call types and custom participant fields.
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-dark">Dashboard Call Tabs</h5>
                <button type="button" @click="addCallTab()" class="btn btn-success btn-sm px-3 py-2 shadow-sm fw-bold">
                    <i class="fas fa-plus me-1.5"></i> Add Call Tab
                </button>
            </div>

            <div class="d-flex flex-column gap-4 mb-4">
                <template x-for="(tab, tabIdx) in callTabs" :key="tabIdx">
                    <div class="card border shadow-sm rounded-3 call-tab-card"
                         :class="{ 'dragging': draggedTabIdx === tabIdx }"
                         draggable="true"
                         @dragstart="dragTabStart($event, tabIdx)"
                         @dragover.prevent
                         @drop="dropTab($event, tabIdx)"
                         @dragend="dragTabEnd()">
                        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center py-2.5 px-3">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-grip-vertical text-muted drag-handle me-1 cursor-grab" title="Drag to reorder tab"></i>
                                <span class="badge bg-secondary px-2 py-1 fs-7 fw-semibold" x-text="`Tab #${tabIdx + 1}`">Tab #1</span>
                                <span class="fw-bold text-success fs-6" x-text="tab.name || 'New Tab'"></span>
                            </div>
                            <button type="button" @click="removeCallTab(tabIdx)" class="btn btn-outline-danger btn-sm px-2 py-1.5">
                                <i class="fas fa-trash-alt me-1"></i> Remove Tab
                            </button>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark mb-1">Tab Name</label>
                                    <input type="text" class="form-control form-control-sm" x-model="tab.name" placeholder="e.g. Active Calls">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark mb-1">Included Call IDs</label>
                                    
                                    <!-- Included Call IDs Custom Multi-Select Dropdown -->
                                    <div class="custom-multiselect-container position-relative" :class="{ 'is-open': open }" x-data="multiSelect(tab.callsIncluded)" @click.outside="open = false">
                                        <div class="custom-multiselect-box" :class="{ 'is-open': open }" @click="open = !open">
                                            <div class="d-flex flex-wrap align-items-center">
                                                <template x-if="!tab.callsIncluded || tab.callsIncluded.length === 0">
                                                    <span class="text-muted small">Select Call IDs...</span>
                                                </template>
                                                <template x-for="cId in tab.callsIncluded" :key="cId">
                                                    <span class="multiselect-chip chip-green">
                                                        <span x-text="cId"></span>
                                                        <i class="fas fa-times close-icon" @click.stop="toggle(cId)"></i>
                                                    </span>
                                                </template>
                                            </div>
                                            <i class="fas fa-chevron-down text-muted small ms-2"></i>
                                        </div>
                                        <div class="custom-multiselect-menu" x-show="open" x-transition>
                                            <template x-if="getAvailableCallIds().length === 0">
                                                <div class="p-2 text-muted small">No call types defined yet. Add call types in the Call Types tab first.</div>
                                            </template>
                                            <template x-for="cId in getAvailableCallIds()" :key="cId">
                                                <div class="custom-multiselect-item" @click.stop="toggle(cId)">
                                                    <span x-text="cId"></span>
                                                    <i class="fas" :class="isSelected(cId) ? 'fa-check-square text-success fs-5' : 'fa-square text-muted opacity-50 fs-5'"></i>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <!-- Auto Extra Columns Info Box -->
                            <div class="p-3 bg-subtle-blue border rounded-3 mb-4" x-show="tab.callsIncluded && tab.callsIncluded.length > 0">
                                <div class="fw-bold small text-primary mb-1">
                                    <i class="fas fa-info-circle me-1"></i> Auto-Included Columns for Selected Call Types (<span x-text="tab.callsIncluded.length">0</span>):
                                </div>
                                <ul class="mb-0 small text-secondary ps-3">
                                    <template x-for="(infoItem, iIdx) in getTabExtraInfo(tab.callsIncluded)" :key="iIdx">
                                        <li class="mb-1" x-html="infoItem"></li>
                                    </template>
                                </ul>
                            </div>

                            <!-- Custom Display Fields -->
                            <div class="border-top pt-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0">
                                            <i class="fas fa-table me-1.5 text-primary"></i> Custom Display Fields for Tab
                                        </h6>
                                        <small class="text-muted">Add custom record fields to present as additional table columns on this dashboard tab.</small>
                                    </div>
                                    <button type="button" @click="addTabField(tabIdx)" class="btn btn-outline-primary btn-sm px-3 py-1.5 fw-semibold">
                                        <i class="fas fa-plus me-1.5"></i> Add Custom Field to Tab
                                    </button>
                                </div>
                                
                                <div class="d-flex flex-column gap-3 mb-2">
                                    <template x-for="(fRow, fIdx) in tab.fields" :key="fIdx">
                                        <div class="p-3 bg-light rounded-3 border">
                                            <div class="row g-3 align-items-end">
                                                <div class="col-md-3">
                                                    <label class="form-label small fw-semibold text-dark mb-1">Field</label>
                                                    
                                                    <!-- Searchable Field Select -->
                                                    <div class="searchable-field-select position-relative" :class="{ 'is-open': open }" x-data="fieldSelect(fRow, 'field', '-- Select Field --')" @click.outside="open = false">
                                                        <div class="form-select form-select-sm d-flex align-items-center justify-content-between cursor-pointer bg-white" @click="open = !open">
                                                            <span class="small text-truncate" :class="val ? 'text-dark fw-semibold' : 'text-muted'" x-text="getFieldLabel(val) || placeholder"></span>
                                                            <i class="fas fa-search text-muted small ms-2"></i>
                                                        </div>
                                                        <div class="searchable-field-menu shadow-lg p-2" x-show="open" x-transition>
                                                            <input type="text" class="form-control form-control-sm mb-2" x-model="filter" placeholder="Type to search fields..." @click.stop>
                                                            <div class="searchable-field-item text-muted small" @click="select('')">-- Clear Selection --</div>
                                                            <template x-for="f in filteredFields" :key="f.id">
                                                                <div class="searchable-field-item small" :class="{ 'active fw-bold text-primary': val === f.id }" @click="select(f.id)">
                                                                    <span x-text="f.label || `${f.id} (${f.name})`"></span>
                                                                    <i class="fas fa-check text-primary" x-show="val === f.id"></i>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>

                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label small fw-semibold text-dark mb-1">Display Label</label>
                                                    <input type="text" class="form-control form-control-sm" x-model="fRow.name" placeholder="Custom Label">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label small fw-semibold text-dark mb-1">Default Value</label>
                                                    <input type="text" class="form-control form-control-sm" x-model="fRow.default" placeholder="Default">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label small fw-semibold text-dark mb-1">Record Link</label>
                                                    <select class="form-select form-select-sm" x-model="fRow.link">
                                                        <template x-for="(lbl, val) in meta.fieldLinkOptions" :key="val">
                                                            <option :value="val" x-text="lbl"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div class="col-md-2" :style="{ visibility: fRow.link === 'instrument' ? 'visible' : 'hidden' }">
                                                    <label class="form-label small fw-semibold text-dark mb-1">Target Instrument</label>
                                                    <select class="form-select form-select-sm" x-model="fRow.linkedInstrument">
                                                        <option value="">-- Select Instrument --</option>
                                                        <template x-for="inst in meta.instruments" :key="inst.id">
                                                            <option :value="inst.id" x-text="`${inst.label || inst.name} (${inst.id})`"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div class="col-md-1 text-end">
                                                    <button type="button" @click="removeTabField(tabIdx, fIdx)" class="btn btn-outline-danger btn-sm border-0 px-2 py-1.5" title="Remove Field">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Tab 5: Workflow -->
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

        <!-- Tab 6: Reports & Analytics -->
        <div class="tab-pane fade show active" x-show="activeTab === 'reports'" x-cloak>
            
            <!-- Controls Bar -->
            <div class="card border shadow-sm mb-4 rounded-3 bg-white">
                <div class="card-body py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="fw-bold text-dark small"><i class="fas fa-filter text-primary me-1.5"></i> Timeframe:</span>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn" :class="reportTimeframe === '7d' ? 'btn-primary fw-bold' : 'btn-outline-secondary'" @click="setReportTimeframe('7d')">Last 7 Days</button>
                            <button type="button" class="btn" :class="reportTimeframe === '30d' ? 'btn-primary fw-bold' : 'btn-outline-secondary'" @click="setReportTimeframe('30d')">Last 30 Days</button>
                            <button type="button" class="btn" :class="reportTimeframe === '90d' ? 'btn-primary fw-bold' : 'btn-outline-secondary'" @click="setReportTimeframe('90d')">Last 90 Days</button>
                            <button type="button" class="btn" :class="reportTimeframe === 'all' ? 'btn-primary fw-bold' : 'btn-outline-secondary'" @click="setReportTimeframe('all')">All Time</button>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="button" @click="loadReportsData()" class="btn btn-outline-primary btn-sm px-3 shadow-sm d-inline-flex align-items-center" :disabled="reportsLoading">
                            <i class="fas fa-sync-alt me-1.5" :class="{ 'fa-spin': reportsLoading }"></i>
                            <span>Refresh</span>
                        </button>
                        <button type="button" @click="exportReportsCsv()" class="btn btn-outline-success btn-sm px-3 shadow-sm d-inline-flex align-items-center" :disabled="reportsLoading || !reportsData">
                            <i class="fas fa-file-csv me-1.5"></i>
                            <span>Export CSV</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loading Spinner -->
            <div x-show="reportsLoading && !reportsData" class="text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
                <div class="text-muted fw-semibold">Crunching report analytics & caller stats...</div>
            </div>

            <!-- Reports Content -->
            <div x-show="reportsData" x-transition>
                
                <!-- KPI Overview Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="card border shadow-sm h-100 bg-white rounded-3">
                            <div class="card-body py-3 px-3">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="text-muted small fw-semibold text-uppercase tracking-wider">Logged Attempts</span>
                                    <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                        <i class="fas fa-headset"></i>
                                    </div>
                                </div>
                                <h3 class="fw-bold mb-1 text-dark" x-text="totalReportAttempts">0</h3>
                                <span class="text-muted small">Call instances logged</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="card border shadow-sm h-100 bg-white rounded-3">
                            <div class="card-body py-3 px-3">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="text-muted small fw-semibold text-uppercase tracking-wider">Completed Calls</span>
                                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                        <i class="fas fa-check-double"></i>
                                    </div>
                                </div>
                                <h3 class="fw-bold mb-1 text-success" x-text="reportsData?.queueSummary?.completedCalls || 0">0</h3>
                                <span class="text-muted small" x-text="(reportsData?.queueSummary?.completionPercentage || 0) + '% overall completion rate'"></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="card border shadow-sm h-100 bg-white rounded-3">
                            <div class="card-body py-3 px-3">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="text-muted small fw-semibold text-uppercase tracking-wider">Expired Reminders</span>
                                    <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                        <i class="fas fa-calendar-times"></i>
                                    </div>
                                </div>
                                <h3 class="fw-bold mb-1 text-danger" x-text="reportsData?.queueSummary?.expiredReminders || 0">0</h3>
                                <span class="text-muted small">Passed without outreach</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="card border shadow-sm h-100 bg-white rounded-3">
                            <div class="card-body py-3 px-3">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="text-muted small fw-semibold text-uppercase tracking-wider">Hourly Cron Health</span>
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                         :class="reportsData?.cronDiagnostics?.temporalCron?.status === 'healthy' ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-warning'"
                                         style="width: 34px; height: 34px;">
                                        <i class="fas" :class="reportsData?.cronDiagnostics?.temporalCron?.status === 'healthy' ? 'fa-check' : 'fa-exclamation-triangle'"></i>
                                    </div>
                                </div>
                                <h5 class="fw-bold mb-1 text-dark" x-text="reportsData?.cronDiagnostics?.temporalCron?.status === 'healthy' ? 'Active & Healthy' : (reportsData?.cronDiagnostics?.temporalCron?.status === 'warning' ? 'Needs Attention' : 'Idle')">Active</h5>
                                <span class="text-muted small" x-text="'Last run: ' + (reportsData?.cronDiagnostics?.temporalCron?.lastRunFormatted || 'Never')"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Layout: Left = Caller Productivity, Right = Queue Breakdown & Cron -->
                <div class="row g-4 mb-4">
                    
                    <!-- Caller Productivity Table -->
                    <div class="col-lg-8">
                        <div class="card border shadow-sm rounded-3 h-100 bg-white">
                            <div class="card-header bg-light border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                                <div class="fw-bold text-dark d-flex align-items-center gap-2">
                                    <i class="fas fa-users text-primary"></i>
                                    <span>Caller Productivity & Effort</span>
                                </div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" x-text="(sortedCallers.length) + ' Team Members'"></span>
                            </div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-hover align-middle mb-0 small">
                                    <thead class="table-light text-muted">
                                        <tr>
                                            <th class="ps-4" style="cursor: pointer;" @click="sortReportBy('displayName')">
                                                Team Member <i class="fas fa-sort ms-1 opacity-50"></i>
                                            </th>
                                            <th class="text-center" style="cursor: pointer;" @click="sortReportBy('attempts')">
                                                Attempts <i class="fas fa-sort ms-1 opacity-50"></i>
                                            </th>
                                            <th class="text-center" style="cursor: pointer;" @click="sortReportBy('completed')">
                                                Completed <i class="fas fa-sort ms-1 opacity-50"></i>
                                            </th>
                                            <th class="text-center" style="cursor: pointer;" @click="sortReportBy('completionRate')">
                                                Success Rate <i class="fas fa-sort ms-1 opacity-50"></i>
                                            </th>
                                            <th class="text-center" style="cursor: pointer;" @click="sortReportBy('voicemails')">
                                                Voicemails <i class="fas fa-sort ms-1 opacity-50"></i>
                                            </th>
                                            <th class="text-center pe-4" style="cursor: pointer;" @click="sortReportBy('callbacksScheduled')">
                                                Callbacks Set <i class="fas fa-sort ms-1 opacity-50"></i>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-if="sortedCallers.length === 0">
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted">
                                                    <i class="fas fa-inbox fa-2x mb-2 opacity-50 d-block"></i>
                                                    No call attempts or completions logged for this timeframe.
                                                </td>
                                            </tr>
                                        </template>
                                        <template x-for="user in sortedCallers" :key="user.username">
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                                             :class="user.isAutomation ? 'bg-secondary' : 'bg-primary'"
                                                             style="width: 28px; height: 28px; font-size: 0.75rem;">
                                                            <span x-text="user.isAutomation ? 'R' : user.displayName.charAt(0).toUpperCase()"></span>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold text-dark" x-text="user.displayName"></div>
                                                            <span class="text-muted" style="font-size: 0.75rem;" x-text="user.isAutomation ? 'Automated Logic' : '@' + user.username"></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center fw-semibold" x-text="user.attempts"></td>
                                                <td class="text-center fw-semibold text-success" x-text="user.completed"></td>
                                                <td class="text-center">
                                                    <div class="d-flex align-items-center justify-content-center gap-1.5">
                                                        <div class="progress flex-grow-1" style="height: 6px; max-width: 60px;">
                                                            <div class="progress-bar bg-success" role="progressbar" :style="'width: ' + user.completionRate + '%'"></div>
                                                        </div>
                                                        <span class="fw-bold" x-text="user.completionRate + '%'"></span>
                                                    </div>
                                                </td>
                                                <td class="text-center text-muted" x-text="user.voicemails"></td>
                                                <td class="text-center pe-4 text-muted" x-text="user.callbacksScheduled"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Call Queue & Pipeline Distribution -->
                    <div class="col-lg-4">
                        <div class="card border shadow-sm rounded-3 mb-4 bg-white">
                            <div class="card-header bg-light border-bottom py-3 px-4 fw-bold text-dark">
                                <i class="fas fa-chart-pie text-primary me-2"></i> Queue Status Distribution
                            </div>
                            <div class="card-body p-4">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small fw-bold mb-1">
                                        <span class="text-primary"><i class="fas fa-clock me-1"></i> Active / Open</span>
                                        <span x-text="reportsData?.queueSummary?.activeCalls || 0"></span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-primary" :style="'width: ' + ((reportsData?.queueSummary?.activeCalls / (reportsData?.queueSummary?.totalCalls || 1)) * 100) + '%'"></div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small fw-bold mb-1">
                                        <span class="text-success"><i class="fas fa-check-circle me-1"></i> Completed</span>
                                        <span x-text="reportsData?.queueSummary?.completedCalls || 0"></span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-success" :style="'width: ' + ((reportsData?.queueSummary?.completedCalls / (reportsData?.queueSummary?.totalCalls || 1)) * 100) + '%'"></div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small fw-bold mb-1">
                                        <span class="text-danger"><i class="fas fa-times-circle me-1"></i> Expired</span>
                                        <span x-text="reportsData?.queueSummary?.expiredCalls || 0"></span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-danger" :style="'width: ' + ((reportsData?.queueSummary?.expiredCalls / (reportsData?.queueSummary?.totalCalls || 1)) * 100) + '%'"></div>
                                    </div>
                                </div>

                                <div class="pt-3 border-top d-flex justify-content-between text-muted small">
                                    <span>Average Attempts per Call:</span>
                                    <span class="fw-bold text-dark" x-text="reportsData?.queueSummary?.avgAttemptsPerCall || '0'"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Template Breakdown Table -->
                        <div class="card border shadow-sm rounded-3 bg-white">
                            <div class="card-header bg-light border-bottom py-3 px-4 fw-bold text-dark">
                                <i class="fas fa-layer-group text-primary me-2"></i> Breakdown by Call Template
                            </div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0 small">
                                    <thead class="table-light text-muted">
                                        <tr>
                                            <th class="ps-3">Template</th>
                                            <th class="text-center">Active</th>
                                            <th class="text-center">Done</th>
                                            <th class="text-center pe-3">Expired</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(tplStats, tplKey) in (reportsData?.queueSummary?.byTemplate || {})" :key="tplKey">
                                            <tr>
                                                <td class="ps-3 fw-semibold text-capitalize text-dark" x-text="tplKey === 'mcv' ? 'Missed Visit' : (tplKey === 'nts' ? 'Need to Schedule' : tplKey)"></td>
                                                <td class="text-center text-primary fw-bold" x-text="tplStats.active"></td>
                                                <td class="text-center text-success" x-text="tplStats.complete"></td>
                                                <td class="text-center pe-3 text-danger" x-text="tplStats.expired"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Automation & Cron Diagnostics Section -->
                <div class="card border shadow-sm rounded-3 bg-white mb-4">
                    <div class="card-header bg-light border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                        <div class="fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="fas fa-robot text-primary"></i>
                            <span>Automation & Cron Diagnostics</span>
                        </div>
                        <span class="text-muted small"><i class="fas fa-info-circle me-1"></i> Background lifecycle health</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4 mb-4">
                            <!-- Hourly Temporal Cron Diagnostic -->
                            <div class="col-md-6">
                                <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas fa-history text-primary"></i>
                                            <h6 class="fw-bold mb-0 text-dark">Hourly Temporal Lifecycle Cron</h6>
                                        </div>
                                        <span class="badge" :class="reportsData?.cronDiagnostics?.temporalCron?.status === 'healthy' ? 'bg-success text-white' : 'bg-warning text-dark'"
                                              x-text="reportsData?.cronDiagnostics?.temporalCron?.status === 'healthy' ? 'Healthy' : 'Warning'"></span>
                                    </div>
                                    <div class="small text-muted mb-2">Expiring reminders, attendance-based MCV completions, and new record intake.</div>
                                    <div class="d-flex justify-content-between small py-1 border-bottom">
                                        <span class="text-muted">Last Execution:</span>
                                        <span class="fw-bold text-dark" x-text="reportsData?.cronDiagnostics?.temporalCron?.lastRunFormatted || 'Never'"></span>
                                    </div>
                                    <div class="d-flex justify-content-between small py-1 border-bottom">
                                        <span class="text-muted">Execution Duration:</span>
                                        <span class="fw-bold text-dark" x-text="(reportsData?.cronDiagnostics?.temporalCron?.duration || '0') + 's'"></span>
                                    </div>
                                    <div class="d-flex justify-content-between small py-1">
                                        <span class="text-muted">Records Evaluated / Updated:</span>
                                        <span class="fw-bold text-dark" x-text="(reportsData?.cronDiagnostics?.temporalCron?.recordsEvaluated || 0) + ' eval / ' + (reportsData?.cronDiagnostics?.temporalCron?.callsUpdated || 0) + ' updated'"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Daily Sync Cron Diagnostic -->
                            <div class="col-md-6">
                                <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas fa-sync text-primary"></i>
                                            <h6 class="fw-bold mb-0 text-dark">Daily Synchronization & Audit Cron</h6>
                                        </div>
                                        <span class="badge" :class="reportsData?.cronDiagnostics?.dailySync?.status === 'healthy' ? 'bg-success text-white' : 'bg-warning text-dark'"
                                              x-text="reportsData?.cronDiagnostics?.dailySync?.status === 'healthy' ? 'Healthy' : 'Warning'"></span>
                                    </div>
                                    <div class="small text-muted mb-2">Comprehensive sweep of all templates across every project record.</div>
                                    <div class="d-flex justify-content-between small py-1 border-bottom">
                                        <span class="text-muted">Last Execution:</span>
                                        <span class="fw-bold text-dark" x-text="reportsData?.cronDiagnostics?.dailySync?.lastRunFormatted || 'Never'"></span>
                                    </div>
                                    <div class="d-flex justify-content-between small py-1 border-bottom">
                                        <span class="text-muted">Execution Duration:</span>
                                        <span class="fw-bold text-dark" x-text="(reportsData?.cronDiagnostics?.dailySync?.duration || '0') + 's'"></span>
                                    </div>
                                    <div class="d-flex justify-content-between small py-1">
                                        <span class="text-muted">Records Evaluated / Generated:</span>
                                        <span class="fw-bold text-dark" x-text="(reportsData?.cronDiagnostics?.dailySync?.recordsEvaluated || 0) + ' eval / ' + (reportsData?.cronDiagnostics?.dailySync?.callsGenerated || 0) + ' generated'"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Automated Logs -->
                        <div class="fw-bold small text-dark mb-2"><i class="fas fa-list-alt text-secondary me-1.5"></i> Recent Automation Activity Log</div>
                        <div class="table-responsive border rounded-3">
                            <table class="table table-sm table-hover align-middle mb-0 small">
                                <thead class="table-light text-muted">
                                    <tr>
                                        <th class="ps-3" style="width: 140px;">Timestamp</th>
                                        <th style="width: 90px;">Record</th>
                                        <th style="width: 130px;">Action</th>
                                        <th>Call / Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-if="!reportsData?.cronDiagnostics?.recentSystemLogs || reportsData?.cronDiagnostics?.recentSystemLogs?.length === 0">
                                        <tr>
                                            <td colspan="4" class="text-center py-3 text-muted">No recent system automation logs recorded.</td>
                                        </tr>
                                    </template>
                                    <template x-for="log in (reportsData?.cronDiagnostics?.recentSystemLogs || [])" :key="log.logId">
                                        <tr>
                                            <td class="ps-3 text-muted" x-text="log.timestamp"></td>
                                            <td><span class="badge bg-light text-dark border" x-text="'#' + log.record"></span></td>
                                            <td>
                                                <span class="badge" 
                                                      :class="log.action === 'call_generated' ? 'bg-primary-subtle text-primary border border-primary-subtle' : 
                                                              (log.action === 'call_auto_completed' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border') "
                                                      x-text="log.action || 'system_event'"></span>
                                            </td>
                                            <td>
                                                <span class="fw-semibold text-dark" x-text="log.callName ? log.callName + ' — ' : ''"></span>
                                                <span class="text-muted" x-text="log.reason || log.message"></span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>
