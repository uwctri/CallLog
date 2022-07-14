<?php
$startTime = microtime(true);

// Load our init tab config, no actual data here
list($noData, $tabs, $noData, $timeTaken) = $module->loadCallListData(true);
?>
<script>
    CallLog.tabs = <?php echo json_encode($tabs); ?>;

    // Useful only for debugging
    CallLog.callTemplates = <?php echo json_encode($module->loadCallTemplateConfig()); ?>;

    $(document).ready(function() {

        // Setup search, must happen before table init
        CallLog.fn.setupSearch();

        // Main table build out
        $('.callTable').each(function(index, el) {

            let tab_id = $(el).closest('.tab-pane').prop('id');
            CallLog.childRows[tab_id] = "";
            CallLog.colConfig[tab_id] = CallLog.fn.createColConfig(index, tab_id);

            // Init the table
            $(el).DataTable({
                lengthMenu: [
                    [25, 50, 100, -1],
                    [25, 50, 100, "All"]
                ],
                columns: CallLog.colConfig[tab_id],
                createdRow: (row, data, index) => $(row).addClass('dataTablesRow'),
                sDom: 'ltpi'
            });

        });

        // Insert search box, must happen after table init
        $('.dataTables_length').after(
            "<div class='dataTables_filter customSearch'><label>Search:<input type='search'></label></div>");

        // Exactly what it looks like
        CallLog.fn.setupCookies();

        // Everything is built out, show the body now
        $(".card").fadeIn();

        // Enable click to expand for all rows
        CallLog.fn.setupClickToExpand();

        // Enable Tooltips for the call-back column
        $('*[data-toggle="tooltip"]').tooltip();

        // Refresh the data occasionally
        setInterval(CallLog.fn.refreshTableData, CallLog.pageRefresh);

        // Load the initial data
        CallLog.fn.toggleCallBackCol();
        CallLog.fn.refreshTableData();
        $(".dataTables_empty").text('Loading...')

        console.log("Page first loaded in " + <?= json_encode(round(((microtime(true) - $startTime)), 5)) ?> + " seconds");
    });
</script>

<div class="projhdr"><i class="fas fa-phone"></i> Call List</div>
<div class="card" style="display:none">
    <?php if (count($tabs['config']) > 1) { ?>
        <div class="card-header tab-header">
            <ul class="nav nav-tabs card-header-tabs">
                <?php foreach ($tabs['config'] as $tab) { ?>
                    <li class="nav-item call-tab">
                        <a class="nav-link call-link" data-toggle="tab" data-tabid="<?php echo $tab['tab_id'] ?>" href="#<?php echo $tab['tab_id'] ?>"><?php echo $tab['tab_name'] ?></a>
                    </li>
                <?php } ?>
            </ul>
        </div>
        <div class="tab-content">
        <?php } ?>
        <?php foreach ($tabs['config'] as $tab_index => $tab) { ?>
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
        <?php if (count($tabs['config']) > 1) { ?>
        </div>
    <?php } ?>
</div>