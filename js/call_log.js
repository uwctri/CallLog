CTRICallLog.html = CTRICallLog.html || {};

CTRICallLog.html.wrapper = `
<tr style="border: 1px solid #ddd"><td colspan="2">
<div class="card-header">
    <ul class="nav nav-tabs card-header-tabs">
    </ul>
</div>
</td></tr>`;

CTRICallLog.html.tab = `
<li class="nav-item call-tab">
    <a class="nav-link mr-1" href="#" data-call-id="CALLID">TABNAME</a>
</li>`;

CTRICallLog.html.historic = `
<tr><td colspan="2">
    <div class="alert alert-danger mb-0">
        <div class="container row">
            <div class="col-1"><i class="fas fa-exclamation-triangle h4 mt-1"></i></div>
            <div class="col-10 mt-2 text-center"><b>This is a historic call log that you probably shouldn't be on.</b></div>
            <div class="col-1"><i class="fas fa-exclamation-triangle h4 mt-1"></i></div>
        </div>
    </div>
</td><tr>`;

CTRICallLog.html.noCalls = `
<tr><td colspan="2">
    <div class="yellow">
        <div class="container row">
            <div class="col m-2 text-center"><b>This subject has no active calls.</b></div>
        </div>
    </div>
</td><tr>`;

CTRICallLog.html.notes = `
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
</td>`;

CTRICallLog.html.adhoc = `<button type="button" class="btn btn-primaryrc btn-sm position-absolute adhocButton" data-toggle="modal" data-target="#MODALID">TEXT</button>`;

CTRICallLog.html.adhocModal = `
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
                <form class="form-horizontal"><fieldset>
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
                                    <input name="callTime" type="text" class="form-control maxWidthOverride">
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
                </fieldset></form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primaryrc callModalSave">Save Call & Exit</button>
            </div>
        </div>
    </div>
</div>
`;

CTRICallLog.optionalCSS = `
<style>
    .formHeader {
        background-color: #675186;
        color: #FBE5D6;
        font-weight: normal;
        font-size: 2.5rem;
        text-align: center;
    }
    .formSxnHeader {
        background-color: #67B094;
        color: #f7ecec;
        font-weight: normal;
        font-size: 2rem;
        text-align: center;
    }
</style>`;

CTRICallLog.functions = {};

CTRICallLog.functions.callAdminEdit = function () {
    $("*[class='@HIDDEN']").show();
    $("#__SUBMITBUTTONS__-tr").hide();
}

CTRICallLog.functions.saveMetadata = function () {
    $.ajax({
        method: 'POST',
        url: CTRICallLog.metadataPOST,
        data: {
            record: getParameterByName('id'),
            metadata: JSON.stringify(CTRICallLog.metadata)
        },
        error: (jqXHR, textStatus, errorThrown) => console.log(textStatus + " " +errorThrown),
        success: (data) => console.log(data)
    });
}

CTRICallLog.functions.saveCalldata = function (instance, dataVar, dataVal, isCheckbox) {
    isCheckbox = !!isCheckbox;
    if ( isCheckbox ) 
        dataVal = JSON.stringify(dataVal);
    $.ajax({
        method: 'POST',
        url: CTRICallLog.calldataPOST,
        data: {
            record: getParameterByName('id'),
            instance: instance,
            dataVar: dataVar,
            dataVal: dataVal,
            isCheckbox: isCheckbox
        },
        error: (jqXHR, textStatus, errorThrown) => console.log(textStatus + " " +errorThrown),
        success: (data) => console.log(data)
    });
}

CTRICallLog.functions.UpdateCallTypeEndDates = function (call_type, days) {
    $.each(CTRICallLog.metadata, function(callid, data) {
        if ( !callid.includes(call_type) ) 
            return;
        CTRICallLog.metadata[callid]['end'] = formatDate( ( new Date(CTRICallLog.metadata[callid]['start']+"T00:00" ).addDays(days) ), 'y-MM-dd');
    });
}

function sendToCallList() {
    location.href = $("#external_modules_panel a:contains('Call List')").prop('href');
}

function getPreviousCalldatetime( callID ) {
    if ( !CTRICallLog.metadata[callID] || isEmpty(CTRICallLog.metadata[callID].instances) )
        return "";
    let data = CTRICallLog.data[ CTRICallLog.metadata[callID].instances.slice(-1)[0]  ];
    if ( !data ) 
        return "";
    return formatDate(new Date(data['call_open_date']+"T00:00:00"),'MM-dd-y') + " " +conv24to12(data['call_open_time']);
}

function getPreviousCallTasks( callID ) {
    if ( !CTRICallLog.metadata[callID] || isEmpty(CTRICallLog.metadata[callID].instances) )
        return [];
    let data = CTRICallLog.data[ CTRICallLog.metadata[callID].instances.slice(-1)[0]  ];
    if ( !data )
        return [];
    let arr = [];
    for (const [key, value] of Object.entries(data['call_task_remaining'])) {
        if ( value == "1" ) 
            arr.push(key);
    }
    return arr;
}

function getPreviousCallNotes( callID ) {
    if ( !CTRICallLog.metadata[callID] || isEmpty(CTRICallLog.metadata[callID].instances) )
        return [];
    let notes = [];
    $.each( CTRICallLog.metadata[callID].instances, function(_,instance) {
        let data = CTRICallLog.data[instance];
        if ( !data ) 
            return;
        notes.push( {
            'dt': formatDate(new Date(data['call_open_date']+"T00:00:00"),'MM-dd-y') + " " +conv24to12(data['call_open_time']),
            'text': data['call_notes'],
            'user': data['call_open_user_full_name']
        });
    });
    return notes;
}

function updateCallNotes( callID ) {
    $('.notesOld').val("")
    $('.notesNew').val( $('textarea[name=call_notes]').val() );
    $.each( getPreviousCallNotes(callID), function() {
        if ( this.text == "" )
            return;
        $('.notesOld').val(`${this.dt} ${this.user}: ${this.text} \n\n${$('.notesOld').val()}`.trim());
    });
}

function getWeekNumber(d) {
    d = typeof d == 'object' ? d : new Date(d);
    d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay()||7));
    var yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
    var weekNo = Math.ceil(( ( (d - yearStart) / 86400000) + 1)/7);
    return [d.getUTCFullYear(), weekNo];
}

function getLeaveMessage(callID) {
    let meta = CTRICallLog.metadata[callID]
    if ( meta.voiceMails >= meta.maxVoiceMails )
        return 'No';
    if ( meta.maxVMperWeek >= meta.voiceMails )
        return 'Yes';
    let thisWeek = getWeekNumber(new Date())[1];
    return CTRICallLog.metadata[callID].instances.map(
            x=>getWeekNumber(CTRICallLog.data[x]['call_open_date']
        ) == thisWeek ).filter(x=>x).length >= meta.maxVMperWeek ? 'No' : 'Yes';
}

function buildNotesArea() {
    $("#call_notes-tr td").hide();
    $("#call_notes-tr").append(CTRICallLog.html.notes);
    $(".panel-left").resizable({
        handleSelector: ".splitter",
        resizeHeight: false,
        create: (event, ui) => $('.ui-icon-gripsmall-diagonal-se').remove()
    });
    $('.notesNew').on('change', () => $('textarea[name=call_notes]').val( $('.notesNew').val() ));
}

$(document).ready(function () {
    
    // Check if user can be here
    if ( isEmpty(CTRICallLog.metadata) ) {
        if ( isEmpty(CTRICallLog.adhoc.config) ) 
            sendToCallList();// If the call log is un-used so far kick them to the Call List page
        else 
            setTimeout( () => $("#call_hdr_details-tr").nextAll('tr').addBack().hide(), 100 ); // dodge branching logic
    }
    
    // Load some Default CSS if none exists 
    if ( $('.formHeader').css('text-align') != 'center' )
        $('head').append(CTRICallLog.optionalCSS);
    
    // Hide a few things for style
    $("#formtop-div").hide();
    $("td.context_msg").hide();
    $(`#${CTRICallLog.static.instrumentLower}_complete-sh-tr`).hide();
    $(`#${CTRICallLog.static.instrumentLower}_complete-tr`).hide();
    
    //Build out the Notes area
    buildNotesArea();
    
    // If we are on a completed version of the Call Log show a warning, update the details and leave
    if ( $(`select[name=${CTRICallLog.static.instrumentLower}_complete]`).val() != "0" ) {
        $(".formtbody").prepend(CTRICallLog.html.historic);
        $("#__SUBMITBUTTONS__-tr").hide();
        // Fill out call details
        let id = CTRICallLog.data[getParameterByName('instance')]['call_id'];
        let data = CTRICallLog.data[getParameterByName('instance')];
        $("#CallLogCurrentCall").text(CTRICallLog.metadata[id]['name']);
        $("td:contains(Current Caller)").next().text(data['call_open_user_full_name']);
        $("#CallLogCurrentTime").text(formatDate(new Date(data['call_open_date']+"T00:00:00"),'MM-dd-y') + " " +conv24to12(data['call_open_time']));
        $("#CallLogPreviousTime").text("Historic");
        updateCallNotes(id);
        return;
    }
    
    // Build out the tabs
    $("#questiontable tr[id]").first().before(CTRICallLog.html.wrapper);
    $.each( CTRICallLog.metadata, function(callID, callData) {
        // Hide completed calls, blank call IDs (errors), future calls, and follow-ups that were never completed (auto remove)
        if( this.complete || (callID[0] == "_") || (callID == "") || 
            (callData.start && (callData.start > today)) || 
            (callData.autoRemove && callData.end && (callData.end < today)) )
            return;
        $(".card-header-tabs").append(CTRICallLog.html.tab.
            replace('CALLID',callID).replace('TABNAME',callData.name||"Unknown"));
    });
    
    // If no tabs then we have no calls. 
    if ( $(".nav-link").length == 0 ) {
        setTimeout( function(){ 
            $("#call_hdr_details-tr").nextAll('tr').addBack().hide();
            $(".formtbody").append(CTRICallLog.html.noCalls);
            $("#formSaveTip").remove();
        },100 ); // dodge branching logic
    }
    
    // Check if their is any data at all. We need the record to exist to continue
    if ( Object.keys(CTRICallLog.data).length == 0 )
        return
    
    //Build out ad-hoc buttons 
    $.each( CTRICallLog.adhoc.config, function(index, adhoc) {
        $("div.card-header").after(CTRICallLog.html.adhoc.replace('TEXT','New '+adhoc.name).replace('MODALID',adhoc.id));
        $(".adhocButton").first().css('transform', 'translate('+(790-$(".adhocButton").first().outerWidth())+'px,-34px)');
        $(".formtbody tr").first().append(CTRICallLog.html.adhocModal.replace('MODALID', adhoc.id).replace('MODAL TITLE','New '+adhoc.name));
        $.each(adhoc.reasons, (code,value) => $(`#${adhoc.id} select[name=reason]`).append(`<option value="${code}">${value}</option>`) );
        $(`#${adhoc.id} input[name=callDate]`).datepicker();
        $(`#${adhoc.id} input[name=callTime]`).flatpickr({
            enableTime: true,
            noCalendar: true,
            dateFormat: "G:i K",
            allowInput: true,
            static: true
        });
        $(`#${adhoc.id} .callModalSave`).on('click', function() {
            let date = $(`#${adhoc.id} input[name=callDate]`).val(); //redcap format function
            date = date ? formatDate(new Date(date), 'y-MM-dd') : "";
            $.ajax({
                method: 'POST',
                url: CTRICallLog.adhoc.post,
                data: {
                    record: getParameterByName('id'),
                    id: adhoc.id,
                    date: date, 
                    time: conv12to24($(`#${adhoc.id} input[name=callTime]`).val()),
                    reason: $(`#${adhoc.id} select[name=reason]`).val(),
                    notes: $(`#${adhoc.id} textarea[name=notes]`).val(),
                    reporter: CTRICallLog.userNameMap[$("#username-reference").text()]
                },
                error: (jqXHR, textStatus, errorThrown) => console.log(textStatus + " " +errorThrown),
                success: function(data){
                    // Data is thrown out
                    window.onbeforeunload = function() { };
                    window.location = (window.location+"").replace('index','record_home'); 
                }
            });
            $(`#${adhoc.id}`).modal('hide');
        });
    });
    
   // If we have calls then build out the call summary table
    buildCallSummaryTable()
    
    // Fill in Call Details on Tab Change
    $("#CallLogCurrentTime").text( $("input[name=call_open_date]").val() + " " + conv24to12($("input[name=call_open_time]").val()) );
    $(".nav-link").on('click', function() {
        $(".nav-link.active").removeClass('active');
        $(this).addClass('active');
        let id = $(this).data('call-id');
        let call = CTRICallLog.metadata[id];
        $("#CallLogCurrentCall").text(call.name);
        $("#CallLogLeaveAMessage").text( getLeaveMessage(id) );
        $("#CallLogPreviousTime").text( call.instances.length == 0 ? 'None' : getPreviousCalldatetime(id));
        $("input[name=call_attempt]").val( call.instances.length + 1 ).blur();
        $("input[name=call_id]").val( id ).blur();
        $("select[name=call_template]").val( call['template'] ).change();
        $("input[name=call_event_name]").val( id.split('|')[1] || "" );
        updateCallNotes(id)
    });
    
    // Update Remaining Call Tasks (Call Outcome changes or Tab changes)
    $("input[name^=call_outcome], .nav-link").on('click', function() {
        if( $("input[name$=call_task_remaining]").is(':visible') ) {
            let tasks = getPreviousCallTasks( $("input[name=call_id]").val() );
            $.each( tasks, function() {
                $(`input[name$=call_task_remaining][code=${this}]`).click();
            });
        }
    });
    
    if ( getParameterByName('showReturn') ) {
        setInterval(function() {
            $("#formSaveTip .btn-group").hide();
        }, 100);
        $("#__SUBMITBUTTONS__-div .btn-group").hide();
        $("#__SUBMITBUTTONS__-div #submit-btn-saverecord").clone(true).off().attr('onclick','goToCallList()').prop('id','goto-call-list').addClass('ml-1').text('Save & Go To Call List').insertAfter("#__SUBMITBUTTONS__-div #submit-btn-saverecord");
    }
    
    // Select the correct tab based on URL or default
    function selectTab() { 
        if ( getParameterByName('call_id') )
            $(`.nav-link[data-call-id=${decodeURI(getParameterByName('call_id')).replace(/\|/g,"\\|").replace(/\:/g,"\\:").replace(/\s/g,"\\ ")}]`).click();
        else
            $(".nav-link:visible").first().click();
    }
    selectTab();
    setInterval(function(){ //Watchdog, some call logs were being saved without a call id
        if ( $("input[name=call_id]").val() == "" )
            selectTab();
    }, 1000);
    
    // Call ID missing failsafe.
    setTimeout(function() {
        if ( ($(".nav-link").length > 0) && ($("input[name=call_id]").val() == "") )
            Swal.fire({
                icon: 'warning',
                title: 'Issue Configuring Call Log',
                text: "There was an issue determining what call this log is for. Please refresh the page. If this issue persists contact the REDCap administrator.",
            });
    }, 5000);
    
    // Force Call Incomplete when call back is requested
    $("input[name$=call_requested_callback]").on('click', function() {
        $("input[name^=call_outcome][value=1]").prop('checked',false).prop('disabled',$(this).is(":checked")).click();
    });
    
    // Prevent save without call outcome
    $("#call_outcome-tr").find('input,a').on('click', function() {
        if ( $("input[name=call_outcome]").val() == "" ) {
            $("#submit-btn-saverecord").prop('disabled',true).css('pointer-events','none');
            $("#goto-call-list").prop('disabled',true).css('pointer-events','none');
            $("#submit-btn-dropdown").parent().find('button').prop('disabled',true).css('pointer-events','none');
        } else {
            $("#submit-btn-saverecord").prop('disabled',false).css('pointer-events','inherit');
            $("#goto-call-list").prop('disabled',false).css('pointer-events','inherit');
            $("#submit-btn-dropdown").parent().find('button').prop('disabled',false).css('pointer-events','inherit');
        }
    });
    $("#call_outcome-tr input").first().click()
    
    // Flag as complete always
    $("select[name=call_log_complete]").val('2');
});