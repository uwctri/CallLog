<?php
// Full Call List Page
/** @var \UWMadison\CallLog\CallLog $module */
$projectId = (int)($_GET['pid'] ?? 0);
if (empty($module->tabsConfig) && $projectId) {
    $module->tabsConfig = $module->getConfigService()->getTabConfig($projectId);
}
$tabsConfig = $module->tabsConfig['config'] ?? [];
$cookieState = null;
if (!empty($_COOKIE["call_log_dashboard_{$projectId}"])) {
    $rawCookie = $_COOKIE["call_log_dashboard_{$projectId}"];
    $cookieState = json_decode(urldecode($rawCookie), true) ?: json_decode($rawCookie, true);
}
$savedTab = $cookieState['tab'] ?? '';
$validTabIds = array_column($tabsConfig, 'tab_id');
$activeTabId = (!empty($savedTab) && in_array($savedTab, $validTabIds, true)) 
    ? $savedTab 
    : ($tabsConfig[0]['tab_id'] ?? '');
?>
<div class="call-list-dashboard pr-3 py-3" style="max-width: 1750px;" x-data="callListDashboard">
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
                                <button type="button" class="nav-link call-link fw-semibold px-3 py-2 me-1 <?= ($tab['tab_id'] === $activeTabId) ? 'active' : '' ?>"
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
                         class="call-list-tab-pane tab-pane show active"
                         x-show="activeTab === '<?php echo htmlspecialchars($tab['tab_id']); ?>'"
                         style="<?= ($tab['tab_id'] === $activeTabId) ? '' : 'display: none;' ?>">
                        
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

                                <div class="d-flex align-items-center gap-2 flex-wrap" :class="{ 'opacity-50 pointer-events-none': !dataLoaded }">
                                    <div class="custom-search-wrap position-relative" style="width: 220px;">
                                        <i class="fas fa-search search-icon text-muted"></i>
                                        <input type="search" class="form-control form-control-sm customSearch" placeholder="Search calls..." :disabled="!dataLoaded || (!displayedData['<?php echo htmlspecialchars($tab['tab_id']); ?>'] || displayedData['<?php echo htmlspecialchars($tab['tab_id']); ?>'].length === 0)">
                                    </div>

                                    <select class="form-select form-select-sm caller-filter-select" x-model="activeCallerFilter" :disabled="!dataLoaded || (!displayedData['<?php echo htmlspecialchars($tab['tab_id']); ?>'] || displayedData['<?php echo htmlspecialchars($tab['tab_id']); ?>'].length === 0)" style="width: auto; max-width: 220px;">
                                        <option value="">-- All Callers / Users --</option>
                                        <template x-for="caller in availableCallers" :key="caller">
                                            <option :value="caller" x-text="caller"></option>
                                        </template>
                                    </select>

                                    <button type="button" @click="toggleHiddenCalls()" :title="hideCalls ? 'Show Hidden Calls' : 'Hide Retiring Calls'" :aria-label="hideCalls ? 'Show Hidden Calls' : 'Hide Retiring Calls'" :disabled="!dataLoaded || (!displayedData['<?php echo htmlspecialchars($tab['tab_id']); ?>'] || displayedData['<?php echo htmlspecialchars($tab['tab_id']); ?>'].length === 0)" class="btn btn-sm d-inline-flex align-items-center justify-content-center toggle-hidden-calls-btn">
                                        <i class="fas" :class="hideCalls ? 'fa-eye-slash' : 'fa-eye'"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Card Body Container -->
                        <div class="card-body p-0">
                            <!-- Loading State Placeholder -->
                            <div x-show="!dataLoaded" class="call-list-loading-placeholder py-5 text-center">
                                <div class="placeholder-icon-circle bg-primary-subtle text-primary border-0">
                                    <i class="fas fa-circle-notch fa-spin fa-2x"></i>
                                </div>
                                <h5 class="fw-bold text-dark mb-1">Loading Calls...</h5>
                                <p class="text-muted small mb-0 mx-auto" style="max-width: 440px;">
                                    Retrieving active participant call records and schedule windows...
                                </p>
                            </div>

                            <!-- Empty Tab Placeholder (when no calls at all exist for this tab) -->
                            <div x-show="dataLoaded && (!displayedData['<?php echo htmlspecialchars($tab['tab_id']); ?>'] || displayedData['<?php echo htmlspecialchars($tab['tab_id']); ?>'].length === 0)" class="call-list-empty-placeholder py-5 text-center" style="display: none;">
                                <div class="placeholder-icon-circle bg-light text-success">
                                    <i class="fas fa-clipboard-check fa-2x"></i>
                                </div>
                                <h5 class="fw-bold text-dark mb-1">No Calls on this Tab</h5>
                                <p class="text-muted small mb-3 mx-auto" style="max-width: 460px; line-height: 1.6;">
                                    There are currently no active calls queued for <strong><?php echo htmlspecialchars($tab["tab_name"]); ?></strong>. Any newly generated calls or scheduled appointments will appear here automatically.
                                </p>
                                <div>
                                    <button type="button" @click="refreshTableData()" class="btn btn-sm px-3 shadow-xs empty-refresh-btn" :disabled="isRefreshing">
                                        <i class="fas fa-sync-alt me-1" :class="{ 'fa-spin': isRefreshing }"></i> Refresh
                                    </button>
                                </div>
                            </div>

                            <!-- Data Table Container -->
                            <div x-show="dataLoaded && (displayedData['<?php echo htmlspecialchars($tab['tab_id']); ?>'] && displayedData['<?php echo htmlspecialchars($tab['tab_id']); ?>'].length > 0)" class="table-responsive" style="display: none;">
                                <!-- Columns Unlocked Notification Banner -->
                                <div x-show="isTabUnlocked('<?php echo htmlspecialchars($tab['tab_id']); ?>')" x-cloak class="columns-unlocked-banner py-1 px-3 border-bottom" style="display: none;">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-arrows-alt text-warning"></i>
                                        <span><strong>Column reordering active:</strong> Drag column headers to reposition them. Right-click any header for more options.</span>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" @click="toggleLockColumns('<?php echo htmlspecialchars($tab['tab_id']); ?>')">
                                        <i class="fas fa-lock me-1"></i> Lock Columns
                                    </button>
                                </div>

                                <table class="table table-hover align-middle mb-0 callTable" style="width:100%">
                                </table>
                            </div>
                        </div>

                    </div>
                <?php } ?>
            </div>

            <!-- Call List Footer: Most Recent Data Pull Time -->
            <div class="card-footer py-2 px-3 d-flex flex-wrap align-items-center justify-content-between text-muted small call-list-footer" style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 10px 10px;">
                <div class="d-flex align-items-center gap-2">
                    <i class="far fa-clock text-secondary last-pull-icon"></i>
                    <span>Most recent data pull: <strong class="text-dark last-pull-time" x-text="lastDataPullText || 'Loading...'"></strong></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" @click="refreshTableData()" class="btn btn-sm btn-link text-decoration-none text-secondary p-0 d-inline-flex align-items-center gap-1" :disabled="isRefreshing" title="Refresh call data now">
                        <i class="fas fa-sync-alt" :class="{ 'fa-spin': isRefreshing }"></i>
                        <span x-text="isRefreshing ? 'Refreshing...' : 'Refresh'"></span>
                    </button>
                </div>
            </div>

        </div>
    <?php } ?>
</div>