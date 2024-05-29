# ExtensionUpdates Plugin

This Joomla plugin checks for updates of extensions and sends an eMail once available, the code is based on the core plg_task_updatenotification plugin.

## Configuration

### Initial setup the plugin

- [Download the latest version of the plugin](https://github.com/brbrbr/plg_task_extensionupdates/releases/latest)
- Install the plugin using `Upload & Install`
- Enable the plugin `Task - ExtensionUpdates` from the plugin manager
- Setup the new Task Plugin `System -> Scheduled Tasks -> New -> ExtensionUpdates`

Now the inital setup is completed, please make sure that the cron has been fully setup in the best cases it should use the WebCron setting.

### Update Server

Please note that my update server only supports the latest version running the latest version of Joomla and atleast PHP 8.1.
Any other plugin version I may have added to the download section don't get updates using the update server.

## Issues / Pull Requests

You have found an Issue, have a question or you would like to suggest changes regarding this extension?
[Open an issue in this repo](https://github.com/brbrbr/plg_task_extensionupdates/issues/new) or submit a pull request with the proposed changes.



## Release steps

- `build/build.sh`
- `git commit -am 'prepare release ExtensionUpdates 4'`
- `git tag -s '1.0.4' -m 'ExtensionUpdates 1.0.4'`
- `git push origin --tags`
- `gh release create 1.0.4 build/plg_task_extensionupdates.zip`
- `git push origin master`

