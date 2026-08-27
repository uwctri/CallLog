# Call Log - Redcap External Module

## What does it do?

The Call Log Redcap External Module is resonsible for the generation of calls associated with a redcap record, listing those calls on a convient call list, and tracking progress of those calls to completion. The call log EM currently supports seven call types, any reasonable number of organizing tabs for the call list, and some actions via an API that may be done by external scripts. The EM is highly opinionated.

## Installing

This EM isn't yet available to install via redcap's EM database so you'll need to install to your modules folder (i.e. `redcap/modules/call_log_v1.0.0`) manually.

## Configuration

Configuration for this module is extensive and complex. Full documentation will exist on offical public release.

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