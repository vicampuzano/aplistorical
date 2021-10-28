# Aplistorical README.MD

## Amplitude to Posthog migration process
### Create a Migration Job
A Migration Job is a set of configurations related to the migration for one Amplitude project to one Posthog project. With a Migration Job you’ll define from what date to start retrieving data from Amplitude and when to stop.

Also, a Migration Job stores the API keys and other information needed to do all work.

**To create a migration job:**
```
sail artisan aplistorical:createMigrationJob
```

You’ll be asked for all the information needed to create the Migration Job.

You can also specify all the information using parameters and options. See bellow  `(or use the -h option)`  to a full description of this command.

### Download all files using the Amplitude export API
By using the command `sail artisan aplistorical:getFromAmplitude {jobIb}` command, a backup of all your Amplitude events will be downloaded and stored in the `storage/app/migrationJobs/{jobIb}/down/` folder.

Depending on your events volume and the time range, this task takes a while for downloading and unzipping all files. 

Showing a progress bar is currently in the “ToDo” list but you can check the `laravel.log` file for debug information.

### Process all files and send events to Posthog
Since you have all files downloaded and unzipped, you should process them to translate Amplitude events to Posthog events and send them to a Posthog server.

You can start the process by using:
```
sail artisan aplistorical:processFolder {jobId}
```

This command process all files downloaded from Amplitude, translates all events stored in them and sends the events to Posthog in batches defined by the `—destination-batch=XXXX` option for the `aplistorical:createMigrationJob`command.

Every single file is deleted after be processed.

**Note:** failed batches are stored in `storage/app/migrationsJobs/{jobId}/up/failedSends.json`so you can review it and resend using your own code or any tool like ([Postman](https://www.postman.com/)).


## Mapped properties
Every Amplitude event is translated to a Posthog event using this properties concordance.

| Amplitude JSON property | Posthog JSON property |
| — | —|
| `user_id` | `distinct_id` |
| `event_time` | `timestamp` , `properties.$time`  and `properties.$timestamp`|
| `event_type` | `event` |
| `ip_address` | `$ip` |
| `library` | `$lib` |
| `version_name` | `$lib_version` |
| `device_id` | `properties.device_id` |
| `os` | `properties.$os_version` |
| `event_properties.language` | `properties.locale` |
| `event_properties.*` | `properties.*` |
| `user_properties.*` | `properties.$set.*` |

### Considerations
We create a single $idenfity event in Posthog for **every distinct user on a single file** using the traits includes in the `user_properties`properties of this user’s 1st event in this file.

We also use `$pageview`as the `event`in Posthog when the `event_type`equals `Viewed Page`, `PageVisited`or `pagevisited`in Amplitude.


## Artisan Commands

### aplistorical:createMigrationJob





## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
