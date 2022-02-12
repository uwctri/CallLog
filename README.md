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

* We collect a history of requests for "No calls today", but don't display that data anywhere.
* Custom page for configuration. The current one is slow due to styling the amount of config on a typical project
* One click deploy of call log instrument to the project
* Create a solution for customizing the display of the call log button
* Create a generic solution for call scripts on the call log
* Create a generic solution to add checkboxes / custom options to the Call Log without breaking or risk breaking other things
* Switching between tabs on the call log should clear any entered data and display warning about data lost
* Make window rounding optional for any call type
* Currently we allow for the scheduled call backs to override the withdrawn status of subjects. Switch this to a toggle feature with default on.
* Currently many configs are copied to the metadata of a call. Could we instead load the config and refrence it via template name to avoid this bloat? That would mean a potiental re-pull of data and recalculation of ranges. 

* Nice to Have
    * Consolidated call back tabs or area to aggregate calls across multiple projects.
    * Consider showing if a call back was set in the call summary table
    * Statistics page
