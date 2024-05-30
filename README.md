# Extension & Core Updates Notification Plugin

This Joomla plugin checks for updates of extensions & Joomla! Core and sends an eMail once available.

The code is based on the core plg_task_updatenotification plugin and https://github.com/zero-24/plg_task_extensionupdates

## Configuration

### Initial setup the plugin

- [Download the latest version of the plugin](https://github.com/brbrbr/plg_task_extensionupdates/releases/latest)
- Install the plugin using `Upload & Install`
- Enable the plugin `Task - ExtensionUpdates` from the plugin manager
- Setup the new Task Plugin `System -> Scheduled Tasks -> New -> ExtensionUpdates`
-- Add one or more recipients. These must be Super Users. If no recipient is set (or none of the selected recipients is a Super User anymore) all Super Users with *Receive System Emails* enabled will receive an email
-- With *Send Once* to *Yes* emails will only be send once. Untill the list of extension-update changes. Otherwise an email is send on each Task Execution
- Disable the Core update notifications task if present.
Now the inital setup is completed, please make sure that the cron has been fully setup in the best cases it should use the WebCron setting.

## Minimum Requirements
- Joomla 5.1
- PHP 8.1


## Issues / Pull Requests

If you have found an Issue, have a question or you would like to suggest changes regarding this extension?
[Open an issue in this repo](https://github.com/brbrbr/plg_task_extensionupdates/issues/new) or submit a pull request with the proposed changes.

## Release steps

- `build/build.sh`
- `git commit -am 'prepare release Extension & Core Updates 1.0.8'`
- `git tag -s '1.0.8' -m 'Extension & Core Updates  1.0.8'`
- `git push origin --tags`
- `gh release create 1.0.8 --notes "" --title "Extension & Core Updates Task Plugin 1.0.8" 'build/plg_task_extensionupdates.zip#Extension & Core Updates Task Plugin 1.0.8'` 
- `git push origin master`


