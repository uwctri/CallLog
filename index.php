<?php
// Full Call List Page
/** @var \UWMadison\CallLog\CallLog $module */
$tabsConfig = $module->tabsConfig['config'] ?? [];
?>
<div class="call-list-dashboard px-3 py-3" style="max-width: 1750px;" x-data="callListDashboard">
    <!-- Header Card -->
    <div class="d-flex flex-wrap align-items-center justify-content-between pb-3 mb-4 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary text-white rounded-3 shadow-sm d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                <i class="fas fa-phone-alt text-white" style="font-size: 1.65rem;"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h3 class="fw-bold mb-0" style="color: #0f172a;">Call List Dashboard</h3>
                </div>
                <span class="text-muted small">Participant contact management, callback requests, and active call logs</span>
            </div>
        </div>
    </div>

    <?php if (empty($tabsConfig)) { ?>
        <!-- Setup Required Card -->
        <div class="card my-4 mx-auto border-0 shadow-sm" style="max-width: 750px; border-radius: 12px; background-color: #ffffff; border: 1px solid #cbd5e1 !important;">
            <div class="card-header py-3 px-4 bg-light border-bottom" style="border-radius: 12px 12px 0 0;">
                <h5 class="mb-0 fw-bold d-flex align-items-center" style="color: #0f172a;">
                    <i class="fas fa-sliders-h me-2 text-primary"></i> No Dashboard Call Tabs Configured
                </h5>
            </div>
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle p-3 mb-2" style="width: 64px; height: 64px;">
                        <i class="fas fa-list-ol fa-2x"></i>
                    </span>
                </div>
                <h5 class="fw-bold mb-2" style="color: #0f172a;">Setup Required for Call List</h5>
                <p class="text-muted mb-4 mx-auto" style="max-width: 500px; font-size: 0.95rem; line-height: 1.6;">
                    The Call List dashboard requires at least one configured <strong>Call Tab</strong> to organize and display active participant calls.
                </p>
                <a href="<?= $module->getUrl('config.php'); ?>" class="btn btn-primary text-white px-4 py-2 fw-semibold shadow-sm" style="color: #ffffff !important;">
                    <i class="fas fa-cog me-1 text-white"></i> Configure Call Log Settings
                </a>
            </div>
        </div>
    <?php } else { ?>
        <!-- Main Call List UI Container -->
        <div class="call-list-card card border-0 shadow-sm" style="border-radius: 10px; background-color: #ffffff; border: 1px solid #cbd5e1 !important;">
            
            <?php if (count($tabsConfig) > 1) { ?>
                <!-- Segmented Tabs Navigation -->
                <div class="card-header p-2 border-bottom" style="background-color: #f1f5f9; border-radius: 10px 10px 0 0;">
                    <ul class="nav nav-pills call-list-nav-pills gap-1" role="tablist">
                        <?php foreach ($tabsConfig as $index => $tab) { ?>
                            <li class="nav-item call-tab" role="presentation">
                                <button type="button" class="nav-link call-link fw-semibold px-3 py-2 me-1"
                                        :class="{ 'active': activeTab === '<?php echo htmlspecialchars($tab['tab_id']); ?>' }"
                                        @click="selectTab('<?php echo htmlspecialchars($tab['tab_id']); ?>')">
                                    <span><?php echo htmlspecialchars($tab['tab_name']); ?></span>
                                </button>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>

            <div class="tab-content">
                <?php foreach ($tabsConfig as $tab_index => $tab) { ?>
                    <div id="<?php echo htmlspecialchars($tab["tab_id"]); ?>"
                         class="tab-pane fade show active"
                         x-show="activeTab === '<?php echo htmlspecialchars($tab['tab_id']); ?>'">
                        
                        <!-- Toolbar Bar -->
                        <div class="card-header bg-white border-bottom py-3 px-4">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div>
                                    <h4 class="fw-bold mb-0 text-dark d-flex align-items-center">
                                        <?php echo htmlspecialchars($tab["tab_name"]); ?>
                                    </h4>
                                    <?php if (!empty($tab["description"])) { ?>
                                        <span class="text-muted small"><?php echo htmlspecialchars($tab["description"]); ?></span>
                                    <?php } ?>
                                </div>

                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <div class="input-group input-group-sm search-input-group" style="width: 220px;">
                                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                                        <input type="search" class="form-control customSearch border-start-0 ps-0" placeholder="Search records...">
                                    </div>

                                    <select class="form-select form-select-sm caller-filter-select" x-model="activeCallerFilter" style="width: auto; max-width: 220px;">
                                        <option value="">-- All Callers / Users --</option>
                                        <template x-for="caller in availableCallers" :key="caller">
                                            <option :value="caller" x-text="caller"></option>
                                        </template>
                                    </select>

                                    <button type="button" @click="toggleHiddenCalls()" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
                                        <i class="fas me-1" :class="hideCalls ? 'fa-eye-slash' : 'fa-eye'"></i>
                                        <span x-text="hideCalls ? 'Show Hidden Calls' : 'Hide Retiring Calls'">Toggle Hidden Calls</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Data Table Container -->
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-hover align-middle mb-0 callTable" style="width:100%">
                            </table>
                        </div>

                    </div>
                <?php } ?>
            </div>

        </div>
    <?php } ?>
</div>