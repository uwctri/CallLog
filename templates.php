<template id="CallLog">

    <!-- Call List -->

    <i id="noCallsToday" class="fas fa-info-circle float-left infocircle" data-toggle="tooltip" data-placement="left" title="A provider requested that this subject not be contacted today."></i>

    <i id="atMaxAttempts" class="fas fa-info-circle float-left infocircle" data-toggle="tooltip" data-placement="left" title="This subject has been called the maximum number of times today."></i>

    <div id="callBack">
        <i class="fas fa-stopwatch mr-1" data-toggle="tooltip" data-placement="left" title="Subject's requested callback time"></i>
        DISPLAYDATE
        <span class="callbackRequestor" data-toggle="tooltip" data-placement="left" title="Callback set by REQUESTEDBY">LETTER</span>
    </div>

    <span id="phoneIcon" style="font-size:2em;color:#dc3545;">
        <i class="fas fa-phone-square-alt" data-toggle="tooltip" data-placement="left" title="This subject may already be in a call."></i>
    </span>

    <span id="manyTabIcons" style="font-size:1em;color:#808080">
        <i class="fas fa-solid fa-users" data-toggle="tooltip" data-placement="left" title="This subject is listed on other call tabs:&#010;LIST"></i>
    </span>

    <!-- Every Record Page -->

    <div id="callStartedWarning" class="alert alert-danger" style="text-align:center" role="alert">
        <br>
        <div class="row">
            <div class="col-1"><i class="fas fa-exclamation-triangle h2 mt-1"></i></div>
            <div class="col-10 h6">
                This subject's record was recently opened from the Call List by USERNAME.
                <br>
                They may currently be on the phone with the subject.
            </div>
            <div class="col-1"><i class="fas fa-exclamation-triangle h2 mt-1"></i></div>
        </div>
        <br>
    </div>

    <!-- Summary Table -->

    <div id="callClosed">
        <br><span style='float:right'><b>Call Log Closed</b></span>
    </div>

    <div id="callHistoryTable" class="callHistoryContainer">
        <table class="callSummaryTable compact" style="width:100%">
        </table>
    </div>

    <div id="callHistorySettings">
        <div class="row">
            <div class="col">
                You can toggle the complete flag for all calls on this subject below.
            </div>
        </div>
        <br>
        <div class="row">
            <label class="col-9" for="callToggle">Call Name</label>
            <div class="col-3">Complete</div>
        </div>
    </div>

    <div id="callHistoryRow" class="row">
        <label class="col-9 text-left" for="callToggle">CALLNAME</label>
        <div class="col-3">
            <label class="switch">
                <input type="checkbox" data-call="CALLID" class="callMetadataEdit" checked>
                <span class="slider round"></span>
            </label>
        </div>
    </div>

    <a id="deleteLog" class="deleteInstance"><i class="fas fa-times"></i></a>

    <i id="settingsButton" class="fas fa-ellipsis-v callSummarySettings"></i>

    <!-- Call Log -->
    <table id="callWrapper">
        <tr style="border: 1px solid #ddd">
            <td colspan="2">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                    </ul>
                </div>
            </td>
        </tr>
    </table>

    <li id="callLogTab" class="nav-item call-tab">
        <a class="nav-link mr-1 callTab" href="#" data-call-id="CALLID">TABNAME</a>
    </li>

    <table id="historicDisplay">
        <tr>
            <td colspan="2">
                <div class="alert alert-danger mb-0">
                    <div class="container row">
                        <div class="col-1"><i class="fas fa-exclamation-triangle h4 mt-1"></i></div>
                        <div class="col-10 mt-2 text-center"><b>This is a historic call log that you probably shouldn't be on.</b></div>
                        <div class="col-1"><i class="fas fa-exclamation-triangle h4 mt-1"></i></div>
                    </div>
                </div>
            </td>
        <tr>
    </table>

    <table id="noCallsDisplay">
        <tr>
            <td colspan="2">
                <div class="yellow">
                    <div class="container row">
                        <div class="col m-2 text-center"><b>This subject has no active calls.</b></div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table id="notesEntry">
        <td class="col-7 notesRow" colspan="2" style="background-color:#f5f5f5">
            <div class="container">
                <div class="row mb-3 mt-2 font-weight-bold"> Notes </div>
                <div class="row panel-container">
                    <div class="panel-left">
                        <textarea class="notesOld" readonly placeholder="Previous notes will display here"></textarea>
                    </div>
                    <div class="splitter"></div>
                    <div class="panel-right">
                        <textarea class="notesNew" placeholder="Enter any notes here"></textarea>
                    </div>
                </div>
            </div>
        </td>
    </table>

    <button id="adhocBtn" type="button" class="btn btn-primaryrc btn-sm position-absolute adhocButton" data-toggle="modal" data-target="#MODALID">TEXT</button>

    <div id="adhocModal">
        <div class="modal fade" id="MODALID" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"> MODAL TITLE </h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form class="form-horizontal">
                            <fieldset>
                                <div class="form-group">
                                    <label class="col h6">Reason For Call</label>
                                    <div class="col">
                                        <select name="reason" class="form-control">
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col h6">Prefered Call Date & Time</label>
                                    <div class="col">
                                        <div class="form-group row">
                                            <div class="col">
                                                <input name="callDate" type="text" class="form-control maxWidthOverride">
                                            </div>
                                            <div class="col">
                                                <input name="callTime" type="time" class="form-control maxWidthOverride">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col h6">Notes</label>
                                    <div class="col">
                                        <textarea class="form-control" name="notes" placeholder="Elaborate on the issue/question if possible"></textarea>
                                    </div>
                                </div>
                            </fieldset>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primaryrc callModalSave">Save Call & Exit</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</template>