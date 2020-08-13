<?php
foreach ( explode(',',$_GET['recordList']) as $record ) {
    $module->metadataNewEntry($_GET['pid'],trim($record));
}
?>

<div class="projhdr"><i class="fas fa-phone"></i> Bulk Loading New Entry Calls</div>
<p>Done</p>