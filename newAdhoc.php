<!--
This page is intended to be posted to by an outside script or DET to make a singe new adhoc call on a record
The URL is of the form:
https://ctri-redcap.dom.wisc.edu/redcap/redcap_v10.3.2/ExternalModules/?prefix=CTRI_Custom_CallLog&page=newAdhoc
    &pid=NNN&adhocCode=NNN&record=NNN&type=NNN&fudate=NNN&futime=NNN
-->

<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>

<?php
$module->metadataAdhoc( $_GET['pid'], $_GET['record'], [
    'id' => $_GET['type'],
    'date' => $_GET['fudate'],
    'time' => $_GET['futime'],
    'reason' => $_GET['adhocCode'],
    'reporter' => 'SAE Form'
]);
?>

<div class="projhdr"><i class="fas fa-phone"></i> Creating Ad-hoc call </div>
<p>Done</p>