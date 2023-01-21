<?php
// *************************
// Metadata Report Page
if (isset($_GET['metaReport'])) {
?>
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
<?php
    return;
}
// *************************
// Full Call List Page
?>
<div class="projhdr"><i class="fas fa-phone"></i> Call List</div>
<div class="card" style="display:none">
    <?php if (count($module->tabsConfig['config']) > 1) { ?>
        <div class="card-header tab-header">
            <ul class="nav nav-tabs card-header-tabs">
                <?php foreach ($module->tabsConfig['config'] as $tab) { ?>
                    <li class="nav-item call-tab">
                        <a class="nav-link call-link" data-toggle="tab" data-tabid="<?php echo $tab['tab_id'] ?>" href="#<?php echo $tab['tab_id'] ?>"><?php echo $tab['tab_name'] ?></a>
                    </li>
                <?php } ?>
            </ul>
        </div>
        <div class="tab-content">
        <?php } ?>
        <?php foreach ($module->tabsConfig['config'] as $tab_index => $tab) { ?>
            <div id="<?php echo $tab["tab_id"] ?>" class="tab-pane">
                <div class="card-header">
                    <div class="row header-row">
                        <div class="col-6">
                            <h4><?php echo $tab["tab_name"] ?></h4>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-light btn-sm toggleHiddenCalls">Toggle Hidden Calls</button>
                        </div>
                    </div>
                    <?php if (!empty($tab["description"])) { ?>
                        <div class="row">
                            <div class="col">
                                <h6 class="mb-0">{$tab["description"]}</h6>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div class="card-body table-responsive">
                    <div class="row fit">
                        <div class="col">
                            <table class="callTable" style="width:100%">
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
        <?php if (count($module->tabsConfig['config']) > 1) { ?>
        </div>
    <?php } ?>
</div>