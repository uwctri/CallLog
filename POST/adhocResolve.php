<!--
This page is intended to be posted to by an outside script or DET to resolve an existing adhoc call on a record(s)
The URL is of the form:
https://ctri-redcap.dom.wisc.edu/redcap/redcap_v10.2.1/ExternalModules/?prefix=CTRI_Custom_CallLog&page=adhocResolve&pid=NNN&adhocCode=NNN&recordList=NNN
-->

<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>

<?php
foreach ( explode(',',$_GET['recordList']) as $record ) {
    $module->resolveAdhoc($_GET['pid'],trim($record),$_GET['adhocCode']);
}
?>

<div class="projhdr"><i class="fas fa-phone"></i> Resolving Ad-hoc calls </div>
<p>Done</p>