<?php
/**
 * Tab 4: Dashboard Tabs
 */
?>
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
                                            <?= renderSearchableFieldSelect('fRow', 'field', '-- Select Field --') ?>
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
