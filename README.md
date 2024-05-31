# Extension & Core Updates Notification Plugin

This Joomla plugin checks for updates of extensions & Joomla! Core and sends an eMail once available.

The code is based on the core plg_task_updatenotification plugin and https://github.com/zero-24/plg_task_extensionupdates

## Configuration

### Initial setup the plugin

- [Download the latest version of the plugin](https://github.com/brbrbr/plg_task_extensionupdates/releases/latest)
- Install the plugin using `Upload & Install`
- The plugin Should be enabled automaticly `Task - ExtensionUpdates`
- Setup the new Task Plugin `System -> Scheduled Tasks -> New -> All Updates Notification`
-- Add one or more recipients. These must be Super Users. If no recipient is set (or none of the selected recipients is a Super User anymore) all Super Users with *Receive System Emails* enabled will receive an email
-- With *Send Once* to *Yes* emails will only be send once. Untill the list of extension-update changes. Otherwise an email is send on each Task Execution
- Disable the Core update notifications task if present.

Now the inital setup is completed, please make sure that the cron has been fully setup. Either using *Lazy Scheduler* or *Web Cron* in the  *Scheduled Tasks Configuration*


## Minimum Requirements
- Joomla 5.1
- PHP 8.1


## Issues / Pull Requests

If you have found an Issue, have a question or you would like to suggest changes regarding this extension?
[Open an issue in this repo](https://github.com/brbrbr/plg_task_extensionupdates/issues/new) or submit a pull request with the proposed changes.




