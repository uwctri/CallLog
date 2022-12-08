<?php
$module->initializeJavascriptModuleObject();
?>
<link rel="stylesheet" href="<?= $module->getURL('css/reports.css'); ?>">
<script>
    <?= $module->getJavascriptModuleObjectName(); ?>.config = <?= json_encode($module->loadReportConfig()); ?>;
</script>

<div class="projhdr"><i class="fas fa-receipt"></i> Call Metadata Reports</div>
<div class="container float-left" style="max-width:800px">
    <div class="row p-2">
        <p>This simple reports shows all open calls per record for easy QA. Withdrawn subjects are excluded.</p>
    </div>
    <div id="tableWrapper" class="row p-2">
        <div class="col-12">
            <table style="width:100%" class="table"></table>
        </div>
    </div>
</div>
<script src="<?= $module->getURL('js/reports.js'); ?>"></script>