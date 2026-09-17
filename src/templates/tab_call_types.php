<?php
/**
 * Tab 3: Call Types
 */
?>
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
                                    <?= renderSearchableFieldSelect('callType', 'reminderVariable', '-- Select Date Field --', true) ?>
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
                                    <?= renderSearchableFieldSelect('callType', 'followupDate', '-- Select Date Field --', true) ?>
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
                                    <?= renderSearchableFieldSelect('callType', 'mcvIndicator', '-- Select Indicator Field --') ?>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-dark mb-1">Appointment Date Field</label>
                                    <?= renderSearchableFieldSelect('callType', 'mcvDate', '-- Select Date Field --', true) ?>
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
                                    <?= renderSearchableFieldSelect('callType', 'ntsIndicator', '-- Select Indicator Field --') ?>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-dark mb-1">Target Date Field</label>
                                    <?= renderSearchableFieldSelect('callType', 'ntsDate', '-- Select Date Field --', true) ?>
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
                                    <?= renderSearchableFieldSelect('callType', 'visitIndicator', '-- Select Indicator Field --') ?>
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
