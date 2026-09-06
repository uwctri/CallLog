<?php
// Call Log Custom Configuration Interface
$projectId = isset($_GET['pid']) ? (int)$_GET['pid'] : (defined('PROJECT_ID') ? (int)PROJECT_ID : 0);
?>
<div class="container-fluid py-3 px-4 call-config-dashboard m-0" style="max-width: 1300px;" x-data="callLogConfig">
    
    <!-- Top Action Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h3 class="fw-bold text-dark">
                <i class="fas fa-cog text-primary me-2"></i> Call Log Configuration
            </h3>
            <p class="text-muted small mb-0">Manage rules, unique call types, dashboard tabs, and instrument deployments.</p>
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
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        
        <!-- Tab 1: Quick Setup & Status -->
        <div class="tab-pane fade show active" x-show="activeTab === 'status'">
            
            <!-- Stats Row -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border shadow-sm h-100 bg-white text-dark rounded-3">
                        <div class="card-body text-center py-4">
                            <i class="fas fa-phone-alt fa-2x text-primary mb-2"></i>
                            <h4 class="fw-bold mb-1 text-dark" x-text="totalGeneratedCalls">0</h4>
                            <span class="text-muted small fw-semibold">Total Generated Calls</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border shadow-sm h-100 bg-white text-dark rounded-3">
                        <div class="card-body text-center py-4">
                            <i class="fas fa-phone-volume fa-2x text-info mb-2"></i>
                            <h4 class="fw-bold mb-1 text-dark" x-text="callTypes.length">0</h4>
                            <span class="text-muted small fw-semibold">Configured Call Types</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border shadow-sm h-100 bg-white text-dark rounded-3">
                        <div class="card-body text-center py-4">
                            <i class="fas fa-table fa-2x text-success mb-2"></i>
                            <h4 class="fw-bold mb-1 text-dark" x-text="callTabs.length">0</h4>
                            <span class="text-muted small fw-semibold">Configured Dashboard Tabs</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border shadow-sm h-100 bg-white text-dark rounded-3">
                        <div class="card-body text-center py-4">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h4 class="fw-bold mb-1 text-dark">24 Hours</h4>
                            <span class="text-muted small fw-semibold">Cron Evaluation Interval</span>
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
                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                            <div class="d-flex align-items-center me-3">
                                <i class="fas fa-check-circle me-3 fs-5" :class="setupChecklist.deployment ? 'text-success' : 'text-muted'"></i>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">1. Deploy Call Log Instruments</h6>
                                    <small class="text-muted">Deploy <code>call_log</code> and <code>call_log_metadata</code> instruments from <code>call.csv</code>.</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <template x-if="!setupChecklist.deployment">
                                    <button type="button" @click="deployInstruments()" :disabled="deploying" class="btn btn-primary btn-sm px-3 py-1.5 fw-bold shadow-xs me-2">
                                        <i class="fas me-1" :class="deploying ? 'fa-spinner fa-spin' : 'fa-download'"></i>
                                        <span x-text="deploying ? 'Deploying...' : 'Deploy Instruments'">Deploy Instruments</span>
                                    </button>
                                </template>
                                <span class="badge px-3 py-2 fw-semibold" :class="setupChecklist.deployment ? 'bg-success text-white' : 'bg-secondary text-white'" x-text="setupChecklist.deployment ? 'Deployed' : 'Pending'">Pending</span>
                            </div>
                        </li>

                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle me-3 fs-5" :class="setupChecklist.trigger ? 'text-success' : 'text-muted'"></i>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">2. Select Generation Triggers</h6>
                                    <small class="text-muted">Select form saves that trigger automated participant call evaluation.</small>
                                </div>
                            </div>
                            <span class="badge px-3 py-2 fw-semibold" :class="setupChecklist.trigger ? 'bg-success text-white' : 'bg-secondary text-white'" x-text="setupChecklist.trigger ? 'Configured' : 'Pending'">Pending</span>
                        </li>

                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle me-3 fs-5" :class="setupChecklist.calls ? 'text-success' : 'text-muted'"></i>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">3. Define Unique Call Types</h6>
                                    <small class="text-muted">Create unique call rules (MCV, NTS, Reminders, Follow-ups, Ad-hoc).</small>
                                </div>
                            </div>
                            <span class="badge px-3 py-2 fw-semibold" :class="setupChecklist.calls ? 'bg-success text-white' : 'bg-secondary text-white'" x-text="setupChecklist.calls ? 'Configured' : 'Pending'">Pending</span>
                        </li>

                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle me-3 fs-5" :class="setupChecklist.tabs ? 'text-success' : 'text-muted'"></i>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">4. Configure Dashboard Call Tabs</h6>
                                    <small class="text-muted">Add at least one tab to group and present calls on the Call List dashboard.</small>
                                </div>
                            </div>
                            <span class="badge px-3 py-2 fw-semibold" :class="setupChecklist.tabs ? 'bg-success text-white' : 'bg-secondary text-white'" x-text="setupChecklist.tabs ? 'Configured' : 'Pending'">Pending</span>
                        </li>

                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle me-3 fs-5" :class="setupChecklist.withdraw ? 'text-info' : 'text-muted'"></i>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">5. Subject Withdrawal Rules (Optional)</h6>
                                    <small class="text-muted">Set permanent subject withdrawal event and field rules to exclude subjects.</small>
                                </div>
                            </div>
                            <span class="badge px-3 py-2 fw-semibold" :class="setupChecklist.withdraw ? 'bg-info text-white' : 'bg-secondary text-white'" x-text="setupChecklist.withdraw ? 'Active' : 'Optional'">Optional</span>
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

                    <div class="mb-2 border-top pt-3">
                        <label class="form-label fw-bold text-dark mb-1">Include Call Summary Table On Instruments:</label>
                        <div class="setting-blurb mb-3">
                            Choose which data entry instruments will render the interactive Call Summary widget, giving study staff quick access to caller notes and attempt history directly on participant records.
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
            <div class="setting-blurb mb-3">
                Define unique call rules to power participant call queues. Assign unique call IDs, friendly display names, template types, and max attempt limits before calls auto-retire.
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-dark">Unique Call Types</h5>
                <button type="button" @click="addCallType()" class="btn btn-primary btn-sm px-3 py-2 shadow-sm fw-bold">
                    <i class="fas fa-plus me-1.5"></i> Add Call Type
                </button>
            </div>

            <div class="d-flex flex-column gap-4 mb-4">
                <template x-for="(callType, index) in callTypes" :key="index">
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
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark mb-1">Hide After Attempts</label>
                                    <input type="number" class="form-control form-control-sm" x-model="callType.hideAfterAttempt" placeholder="e.g. 5">
                                </div>
                                <div class="col-md-3">
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
                                    <div class="setting-blurb mb-3">New Entry calls trigger automatically when a participant record is created or imported.</div>
                                    <div class="row g-3 align-items-center mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">Days Until Expire</label>
                                            <input type="number" class="form-control form-control-sm" x-model="callType.newExpireDays" placeholder="e.g. 30">
                                        </div>
                                    </div>
                                </div>

                                <!-- Reminder -->
                                <div x-show="callType.template === 'reminder'">
                                    <div class="setting-blurb mb-3">Reminder calls appear on the call log a configured number of days before a target date and are automatically removed on the target date.</div>
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
                                    <div class="setting-blurb mb-3">Any tab with a follow up call in it will show the Lower and Upper windows of the call as the last two columns titled 'Start Calling' and 'Complete by'.</div>
                                    <div class="row g-3 align-items-start mb-2">
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold text-dark mb-1">Baseline Date Field</label>
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
                                    <div class="setting-blurb mb-3">Any tab with a Missed/Cancelled visit call will show the Missed Appt Date/Time as the last column.</div>
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
                                    <div class="setting-blurb mb-3">Need to Schedule calls are generated for an event when the previous event has a truthy indicator, but the current event has no scheduled date.</div>
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
                                    <div class="setting-blurb mb-3">Ad-hoc calls are added by an end user on the call log screen. Any tab with an adhoc call in it will show the Reason and Preferred call back date as the last two columns.</div>
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
                                    <div class="setting-blurb mb-3">Scheduled Phone Visits are for phone-based encounters that just need a way to record a call log at checkout time. They are not typically used on the call log.</div>
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

    </div>
</div>
