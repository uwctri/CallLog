<?php
// Call Log Custom Configuration Interface
$projectId = isset($_GET['pid']) ? (int)$_GET['pid'] : (defined('PROJECT_ID') ? (int)PROJECT_ID : 0);

if (!function_exists('renderSearchableFieldSelect')) {
    /**
     * Reusable helper to render a searchable field select dropdown in Alpine.js
     */
    function renderSearchableFieldSelect(string $targetObj, string $propKey, string $placeholder = '-- Select Field --', bool $isDateOnly = false, string $clearLabel = '-- Clear Selection --'): string {
        $dateArg = $isDateOnly ? ', true' : '';
        $cleanPlaceholder = htmlspecialchars($placeholder, ENT_QUOTES);
        $cleanClearLabel = htmlspecialchars($clearLabel, ENT_QUOTES);
        $searchPlaceholder = $isDateOnly ? 'Type to search date fields...' : 'Type to search fields...';
        
        return '<div class="searchable-field-select position-relative" :class="{ \'is-open\': open }" x-data="fieldSelect(' . $targetObj . ', \'' . $propKey . '\', \'' . $cleanPlaceholder . '\'' . $dateArg . ')" @click.outside="open = false">
            <div class="form-select form-select-sm d-flex align-items-center justify-content-between cursor-pointer bg-white" @click="open = !open">
                <span class="small text-truncate" :class="val ? \'text-dark fw-semibold\' : \'text-muted\'" x-text="getFieldLabel(val) || placeholder"></span>
                <i class="fas fa-search text-muted small ms-2"></i>
            </div>
            <div class="searchable-field-menu shadow-lg p-2" x-show="open" x-transition style="display: none;">
                <input type="text" class="form-control form-control-sm mb-2" x-model="filter" placeholder="' . $searchPlaceholder . '" @click.stop>
                <div class="searchable-field-item text-muted small" @click="select(\'\')">' . $cleanClearLabel . '</div>
                <template x-for="f in filteredFields" :key="f.id">
                    <div class="searchable-field-item small" :class="{ \'active fw-bold text-primary\': val === f.id }" @click="select(f.id)">
                        <span x-text="f.label || `${f.id} (${f.name})`"></span>
                        <i class="fas fa-check text-primary" x-show="val === f.id"></i>
                    </div>
                </template>
                <div class="p-2 text-muted small text-center" x-show="filteredFields.length === 0">
                    No matching fields found
                </div>
            </div>
        </div>';
    }
}
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
        <?php
        $tplDir = __DIR__ . '/src/templates';
        include $tplDir . '/tab_status.php';
        include $tplDir . '/tab_general.php';
        include $tplDir . '/tab_call_types.php';
        include $tplDir . '/tab_dashboard.php';
        include $tplDir . '/tab_workflow.php';
        include $tplDir . '/tab_reports.php';
        ?>
    </div>
</div>
