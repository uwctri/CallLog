<!--
This page is intended to be posted to by an outside script after scheduling occurs. It loads reminder calls and missed/cancelled calls for any number of records
The URL is of the form:
https://ctri-redcap.dom.wisc.edu/redcap/redcap_v10.2.1/ExternalModules/?prefix=CTRI_Custom_CallLog&page=scheduleLoad&pid=59&recordList=0
-->

<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>

<?php
foreach ( explode(',',$_GET['recordList']) as $record ) {
    $module->metadataReminder($_GET['pid'],trim($record));
    $module->metadataMissedCancelled($_GET['pid'],trim($record));
    $module->metadataNeedToSchedule($_GET['pid'],trim($record));
}
?>

<div class="projhdr"><i class="fas fa-phone"></i> Checking for New Reminder & Missed/Cancelled Visit Calls </div>
<p>Done</p>