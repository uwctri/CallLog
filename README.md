# Call Log - Redcap External Module

## What does it do?

The Call Log Redcap External Module is resonsible for the generation of calls associated with a redcap record, listing those calls on a convient call list, and tracking progress of those calls to completion. The call log EM currently supports seven call types, any reasonable number of organizing tabs for the call list, and some actions via an API that may be done by external scripts. The EM is highly opinionated.

## Installing

You can install the module from the REDCap EM repo or drop it directly in your modules folder (i.e. `redcap/modules/call_log_v1.0.0`) manually.

## Configuration

Once the module is enabled on your project, open **Call Log Settings** in the left sidebar to configure the workflow. Settings for this EM are extensive, don't feel overwhelmed, we've tried to add documentation to the settings page to explain config options.

### 1. Deploy Required Instruments
The module relies on two instruments:
- `call_log`: Repeating instrument used by staff to log attempts, outcomes, notes, and callbacks.
- `call_log_metadata`: Single-instance instrument that stores call queue state behind the scenes.

Click **Deploy Instruments** on the settings page if they aren't already present in your project, and ensure `call_log` is enabled as a repeating form in Project Setup.

### 2. Define Call Types
Add the calls your team needs to make under the **Call Types** section:
- **Call ID & Name**: Internal identifier and display title (e.g. `intake_call`, "Baseline Intake").
- **Template**: How the call triggers—New Record, Reminder, Follow-up, Appointment/MCV, NTS, or Adhoc.
- **Timing & Windows**: Set expiration days, expected call duration, and auto-hide/attempt limits.

### 3. Build Dashboard Tabs
The dashboard requires at least one tab to display calls:
- **Included Calls**: Assign which Call IDs appear on the tab.
- **Columns**: Choose the REDCap fields displayed in the table (e.g., name, phone number, study ID) and expandable row details.

### 4. Project Rules & Phone Display
- **Phone Fields**: Pick the phone variables from your project so contact info surfaces in the header and quick-dial buttons.
- **Withdrawal Rules**: Map any drop-out or completion fields so inactive participants are automatically removed from active call queues.

## API

`POST redcap/api/`

Action: newAdhoc - Create a new adhoc call for a subject

|**Body Parameter** |             **Description**             |   **Type**   |  **Required?**  |
|:-----------------:|:---------------------------------------:|:------------:|:----------------:
|   token           |   User's API token                      |  string      |    Yes          |
|   action          |   "newAdhoc"                            |  string      |    Yes          |
|   prefix          |   "call_log                             |  string      |    Yes          |
|   type            |   Unique call type for the adhoc        |  string      |    Yes          |
|   date            |   Date to contact subject (Y-M-D)       |  string      |    No           |
|   time            |   Time to contact subject (HH:MM)       |  string      |    No           |
|   reason          |   Adhoc code                            |  string      |    Yes          |
|   reporter        |   Username of reporter or freetext      |  string      |    No           |
|   record          |   Record ID                             |  string      |    Yes          |

Action: resolveAdhoc - Resolve all adhoc calls with a

|**Body Parameter** |             **Description**             |   **Type**   |  **Required?**  |
|:-----------------:|:---------------------------------------:|:------------:|:----------------:
|   token           |   User's API token                      |  string      |    Yes          |
|   action          |   "resolveAdhoc"                        |  string      |    Yes          |
|   prefix          |   "call_log                             |  string      |    Yes          |
|   code            |   Adhoc code (reason)                   |  string      |    Yes          |
|   record          |   Record ID                             |  string      |    No           |
|   record_list     |   Above, but as a list                  |  array       |    No           |

Action: generate - Trigger the Call Log Cron and generate any new call logs

|**Body Parameter** |             **Description**             |   **Type**   |  **Required?**  |
|:-----------------:|:---------------------------------------:|:------------:|:----------------:
|   token           |   User's API token                      |  string      |    Yes          |
|   action          |   "generate"                            |  string      |    Yes          |
|   prefix          |   "call_log                             |  string      |    Yes          |