<?php
/**
 * Tab 2: General & Triggers
 */
?>
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
                    <?= renderSearchableFieldSelect('$data', 'displayNameField', '-- Select Name Field (Optional) --', false, '-- No Name Field (Leave Blank) --') ?>
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
                            <?= renderSearchableFieldSelect('rule', 'var', '-- Select or Type Field --') ?>
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
                            <?= renderSearchableFieldSelect('exp', 'field', '-- Select or Type Field --') ?>
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
