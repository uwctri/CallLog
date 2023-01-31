# Call Log - Redcap External Module

## What does it do?

The Call Log Redcap External Module is resonsible for the generation of calls associated with a redcap record, listing those calls on a convient call list, and tracking progress of those calls to completion. The call log EM currently supports seven call types, any reasonable number of organizing tabs for the call list, and some actions via an API that may be done by external scripts. The EM is highly opinionated, but offers some level of flexability via the API.

## Installing

This EM isn't yet available to install via redcap's EM database so you'll need to install to your modules folder (i.e. `redcap/modules/call_log_v1.0.0`) manually.

## Configuration

Configuration for this module is extensive and complex. Full documentation will exist on offical public release.

* Deploy the instrument via the module configuration modal. Assign the two created instruments (Call Log and Metadata) to ONE event each. The metadata form is never intended to be visited by an end-user, it can be placed on un-used hidden event. The Call Log will be used to record instances of interacting with a subject on the phone. It may be a good idea to strucutre your project wit a "Study Management" event that represents used-only-once and special forms, if you do then then that event is a good place for both instruemnts.
* Review the module's config. You will need atleast one unique call and one call tab.
* The end user is expected to complete a call log after every attemp to reach a subject. It is recommended that you link end-users to the call log at the end of each event if appropriate (i.e. with an HTML button).

## Ongoing Issues

* Major code cleanup needed
* Improve text on the config page and documentation in general
* We collect a history of requests for "No calls today", but don't display that data anywhere.
* Metadata report page is currently trivial and visible to everyone
* Currently many configs are copied to the metadata of a call. Could we instead load the config and refrence it via template name to avoid this bloat? That would mean a potiental re-pull of data and recalculation of ranges.

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
