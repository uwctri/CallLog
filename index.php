<?php
// *************************
// Metadata Report Page
if (isset($_GET['metaReport'])) {
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

<?php
    return;
}
// *************************
// Full Call List Page
?>
<link rel="stylesheet" href="<?= $module->getURL('css/list.css'); ?>">
<script src="<?= $module->getURL('js/call_list.js'); ?>"></script>
<script>
    CallLog.usernameLists = <?= json_encode($module->getUserNameListConfig()); ?>;
    CallLog.eventNameMap = <?= json_encode($module->getEventNameMap()); ?>;
</script>
<?php
$startTime = microtime(true);

// Load our init tab config, no actual data here
list($noData, $tabs, $noData, $timeTaken) = $module->loadCallListData(true);
?>
<script>
    CallLog.tabs = <?= json_encode($tabs); ?>;
    $(document).ready(function() {
        if (CallLog.configError) {
            Swal.fire({
                icon: 'error',
                title: 'Call Log config Issue',
                text: 'The Call Log External Module requries the Call Long, and Call Metadata instruments to exist on one event and the former to be enable as a repeatable instrument. Please invesitage and resovle.',
            });
            return;
        }

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
                language: {
                    emptyTable: "No calls to display"
                },
                columns: CallLog.colConfig[tab_id],
                createdRow: (row, data, index) => $(row).addClass('dataTablesRow'),
                sDom: 'ltpi'
            });

        });

        // Insert search box, must happen after table init
        $('.dataTables_length').after(
            "<div class='dataTables_filter customSearch'><label>Search:<input type='search'></label></div>");

        // Exactly what it looks like
        CallLog.fn.setupLocalSettings();

        // Everything is built out, show the body now
        $(".card").fadeIn();

        // Enable click to expand for all rows
        CallLog.fn.setupClickToExpand();

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