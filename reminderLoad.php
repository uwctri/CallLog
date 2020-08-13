<?php
foreach ( explode(',',$_GET['recordList']) as $record ) {
    $module->metadataReminder($_GET['pid'],trim($record));
}
?>

<div class="projhdr"><i class="fas fa-phone"></i> Checking for New Reminder Call </div>
<p>Done</p>