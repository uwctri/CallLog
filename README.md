# Call Log - Redcap External Module

## What does it do?

The Call Log Redcap External Module is resonsible for the generation of calls associated with a redcap record, listing those calls on a convient call list, and tracking progress of those calls to completion. The call log EM currently supports five call types, any reasonable number of organizing tabs for the call list, and loading calls via a get or post request sent from an external script.

## Installing

This EM isn't yet available to install via redcap's EM database so you'll need to install to your modules folder (i.e. `redcap/modules/call_log_v1.0.0`) manually.

## Configuration

Configuration for this module is extensive and complex. Full documentation will exist on offical public release, but this has no current planned date.

Basic setup:

* Deploy the instrument csv to the project you want to use the EM on, you will probably want to deploy this instrument to a "Study Management" event. The call log should exist only on one event and be enabled as repeatable.
* Review the EM config
* The end user is expected to complete a call log after every attemp to reach a subject. It is recommended that you link end-users to the call log at the end of each event that would make sense.

## Feature Requests & Issues

* Fix the NTS issue
* Major JS Cleanup
* Imrpove text on the config page
* Speed up the config page?
* We collect a history of requests for "No calls today", but don't display that data anywhere.
* Switching between tabs on the call log should clear any entered data and display warning about data lost
* Currently many configs are copied to the metadata of a call. Could we instead load the config and refrence it via template name to avoid this bloat? That would mean a potiental re-pull of data and recalculation of ranges.
* Improve metadata report page

## API

Several actions exist for the API, all use a POST to the same end point.

`POST redcap/api/?type=module&prefix=call_log&page=api&NOAUTH`

Action: newAdhoc - Create a new adhoc call for a subject

|**Body Parameter** |             **Description**             |   **Type**   |  **Required?**  |
|:-----------------:|:---------------------------------------:|:------------:|:----------------:
|   token           |   User's API token                      |  string      |    Yes          |
|   action          |   "newAdhoc"                            |  string      |    Yes          |
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
|   code            |   Adhoc code (reason)                   |  string      |    Yes          |
|   record          |   Record ID                             |  string      |    No           |
|   record_list     |   Above, but as a list                  |  array       |    No           |

Action: newEntry - Trigger when a new record is created via outside script to create New Call Logs

|**Body Parameter** |             **Description**             |   **Type**   |  **Required?**  |
|:-----------------:|:---------------------------------------:|:------------:|:----------------:
|   token           |   User's API token                      |  string      |    Yes          |
|   action          |   "newEntry"                            |  string      |    Yes          |
|   record          |   Record ID                             |  string      |    No           |
|   record_list     |   Above, but as a list                  |  array       |    No           |

Action: schedule - Trigger when a record is scheduled to run needed metadata functions

|**Body Parameter** |             **Description**             |   **Type**   |  **Required?**  |
|:-----------------:|:---------------------------------------:|:------------:|:----------------:
|   token           |   User's API token                      |  string      |    Yes          |
|   action          |   "schedule"                            |  string      |    Yes          |
|   record          |   Record ID                             |  string      |    No           |
|   record_list     |   Above, but as a list                  |  array       |    No           |
