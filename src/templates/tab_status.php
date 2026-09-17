<?php
/**
 * Tab 1: Quick Setup & Status
 */
?>
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
