CTRICallLog.html = {};
CTRICallLog.css = {};

CTRICallLog.html.wrapper = `
<tr style="border: 1px solid #ddd"><td colspan="2">
<div class="card-header">
    <ul class="nav nav-tabs card-header-tabs">
    </ul>
</div>
</td></tr>`;

CTRICallLog.html.tab = `
<li class="nav-item call-tab">
    <a class="nav-link" href="#" data-call-id="CALLID">TABNAME</a>
</li>`;

CTRICallLog.html.warning = `
<tr><td colspan="2">
    <div class="alert alert-danger mb-0">
        <div class="container row">
            <div class="col-1"><i class="fas fa-exclamation-triangle h4 mt-1"></i></div>
            <div class="col-10 mt-2 text-center"><b>This is a historic call log that you shouldn't be on.</b></div>
            <div class="col-1"><i class="fas fa-exclamation-triangle h4 mt-1"></i></div>
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

CTRICallLog.css.optional = `
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

CTRICallLog.css.standard = `
<style>
    .CallLogDetailTable td {
        font-size: 1.3em;
        padding: .3rem;
        min-width: 100px;
    }
    .card-header {
        background-color: white;
        width: 800px;
        border: none;
    }
    .nav-link.active {
        background-color: #f5f5f5!important;
    }
    .nav-link {
        border: 1px solid #ddd!important;
        background-color: white;
    }
    .nav-item {
        transform: translate(0, 1px);
    }
    .notesRow {
        border-bottom: 1px solid #DDDDDD;
        padding-left: 0.25rem;
        padding-right: 0;
    }
    .notesRow .container {
        padding-right: 0;
        height: 250px;
    }
    .panel-container textarea{
        border:none;
        resize: none!important;
        width:100%;
        height:100%
    }
    .panel-container {
        display: flex;
        flex-direction: row;
        border: 1px solid silver;
        overflow: hidden;
        xtouch-action: none;
        margin-right: 0.25rem;
    }
    .panel-left {
        flex: 0 0 auto;
        width: 300px;
        min-height: 200px;
        min-width: 186px;
        max-width:550px!important;
        white-space: nowrap;
    }
    .splitter {
        flex: 0 0 auto;
        width: 3px;  
        background-color: #535353;
        min-height: 200px;
        cursor: col-resize;  
    }
    .panel-right {
        flex: 1 1 auto;
        min-height: 200px;
    }
    .panel-right textarea {
        background-color: white;
    }
    .panel-left textarea {
        background-color: #eee
    }
</style>
`;

function sendToCallList() {
    let pid = location.href.split('pid=')[1].split('&')[0];
    location.href = location.href.split('DataEntry')[0]+'ExternalModules/?prefix='+CTRICallLog.modulePrefix+'&page=index&pid='+pid;
}

function getPreviousCalldatetime( callID ) {
    if ( !CTRICallLog.metadata[callID] || isEmpty(CTRICallLog.metadata[callID].instances) )
        return "";
    let data = CTRICallLog.data[ CTRICallLog.metadata[callID].instances.slice(-1)[0]  ];
    return formatDate(new Date(data['call_open_date']),'MM-dd-y') + " " +conv24to12(data['call_open_time']);
}

function getPreviousCallTasks( callID ) {
    if ( !CTRICallLog.metadata[callID] || isEmpty(CTRICallLog.metadata[callID].instances) )
        return [];
    let data = CTRICallLog.data[ CTRICallLog.metadata[callID].instances.slice(-1)[0]  ];
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
        notes.push( {
            'dt': formatDate(new Date(data['call_open_date']),'MM-dd-y') + " " +conv24to12(data['call_open_time']),
            'text': data['call_notes'],
            'user': data['call_open_user_full_name']
        });
    });
    return notes;
}

$(document).ready(function () {
    if ( $('.formHeader').css('text-align') != 'center' )
        $('head').append(CTRICallLog.css.optional);
    $('head').append(CTRICallLog.css.standard);
    
    // Hide a few things for style
    $("#formtop-div").hide();
    $("td.context_msg").hide();
    $(`#${CTRICallLog.static.instrumentLower}_complete-sh-tr`).hide();
    $(`#${CTRICallLog.static.instrumentLower}_complete-tr`).hide();
    
    // Check if user can be here
    if ( isEmpty(CTRICallLog.metadata) ) 
        sendToCallList();// If the call log is un-used so far kick them to the Call List page
    
    // If we are on a completed version of the Call Log show a warning
    if ( $(`select[name=${CTRICallLog.static.instrumentLower}_complete]`).val() != "0" )
        $(".formtbody").prepend(CTRICallLog.html.warning);
    
    //Build out the tabs
    $("#questiontable tr[id]").first().before(CTRICallLog.html.wrapper);
    $.each( CTRICallLog.metadata, function(callID, callData) {
        if( this.complete || callID[0] == "_" )
            return;
        $(".card-header-tabs").append(CTRICallLog.html.tab.
            replace('CALLID',callID).replace('TABNAME',callData.name));
    });
    
    //Build out the Notes area
    $("#call_notes-tr td").hide();
    $("#call_notes-tr").append(CTRICallLog.html.notes);
    $(".panel-left").resizable({
        handleSelector: ".splitter",
        resizeHeight: false,
        create: function(event, ui) {
            $('.ui-icon-gripsmall-diagonal-se').remove();
        }
    });
    $('.notesNew').on('change', function() {
        $('textarea[name=call_notes]').val( $('.notesNew').val() );
    });
    
    // Fill in Call Details on Tab Change
    $("#CallLogCurrentTime").text( $("input[name=call_open_date]").val() + " " + conv24to12($("input[name=call_open_time]").val()) );
    $(".nav-link").on('click', function() {
        $(".nav-link.active").removeClass('active');
        $(this).addClass('active');
        let id = $(this).data('call-id');
        let call = CTRICallLog.metadata[id];
        let t = call.name.split('|');
        $("#CallLogCurrentCall").text((t[0]+" - "+(CTRICallLog.eventNameMap[t[1]]||"remove")+" - "+(t[3]||"remove")).replace(/-\sremove/g,'').trim());
        $("#CallLogAttemptNumber").text( call.instances.length + 1 );
        $("#CallLogLeaveAMessage").text( call.voiceMails >= call.maxVoiceMails ? 'No' : 'Yes' );
        $("#CallLogPreviousTime").text( call.instances.length == 0 ? 'None' : getPreviousCalldatetime(id));
        $("input[name=call_id]").val( id );
        $("select[name=call_template]").val( call['template'] );
        $("input[name=call_event_name]").val( (new URL(location.href)).searchParams.get('callEvent') || "" );
        
        //Update Notes
        $('.notesOld').val("")
        $('.notesNew').val( $('textarea[name=call_notes]').val() );
        $.each( getPreviousCallNotes(id), function() {
            if ( this.text == "" )
                return;
            $('.notesOld').val(`${this.dt} ${this.user}: ${this.text} \n\n${$('.notesOld').val()}`.trim());
        });
    });
    
    // Update Remaining Call Tasks (Call Outcome changes or Tab changes)
    $("input[name^=call_outcome], .nav-link").on('click', function() {
        if( $("input[name$=call_task_remaining]").is(':visible') ) {
            let tasks = getPreviousCallTasks( $("input[name=call_id]").val() );
            $.each( tasks, function() {
                $(`input[name$=call_task_remaining][code=${this}]`).prop('checked',true);
            });
        }
    });
    
    // Select the correct tab based on URL or default
    const url = (new URL(location.href)).searchParams
    if ( url.get('callID') )
        $(`.nav-link[data-call-id=${url.get('callID')}]`).click();
    else
        $(".nav-link").first().click();
    
    // Force Call Incomplete when call back is requested
    $("input[name$=call_requested_callback]").on('click', function() {
        $("input[name^=call_outcome][value=1]").prop('checked',false).prop('disabled',$(this).is(":checked")).click();
    });
    
    // Note: the call_log_complete field is set in the hook
    
});

