# Aplistorical: Amplitude to Posthog migration tool
_“Aplistorical” sounds like “ahistorical” because it prevents you to be lacking historical perspective or context._ 😬

This tool helps you migrate your historical data from Amplitude to Posthog.

It is based on Laravel so you can use it as a normal Laravel project or inside a Docker image using `sail` commands.

## Important notes before start
**This tool is currently in beta**. We have done some tests in production environments and everything was fine. But, we can’t ensure you this will happen in your particular scenario. Please, **check the complete migration in a testing environment before doing it in the production one**.

**Ensure you have been set the same timezone in both your Amplitude and Posthog projects** to avoid having discrepancies caused by timezone offsets.



## Amplitude to Posthog migration process
3 steps are needed to perform a complete migration process.

1. Download all data from Amplitude.
2. Translate events from Amplitude format to Posthog format.
3. Upload events to your Posthog project.

**This is how you’ll do it:**

### 1. Create a Migration Job
A Migration Job is a set of configurations related to the migration for one Amplitude project to one Posthog project. With a Migration Job you’ll define from what date to start retrieving data from Amplitude and when to stop.

Also, a Migration Job stores the API keys and other information needed to do all work.

**To create a migration job:**
```
(php or sail) artisan aplistorical:createMigrationJob
```

You’ll be asked for all the information needed to create the Migration Job.

You can also specify all the information using parameters and options. See bellow  `(or use the -h option)`  to a full description of this command.

### 2. Download all files using the Amplitude export API
By using the command `(php or sail) artisan aplistorical:getFromAmplitude {jobIb}` command, a backup of all your Amplitude events will be downloaded and stored in the `storage/app/migrationJobs/{jobIb}/down/` folder.

Depending on your events volume and the time range, this task takes a while for downloading and unzipping all files. 

Showing a progress bar is currently in the “ToDo” list but you can check the `laravel.log` file for debug information.

### 3. Process all files and send events to Posthog
Since you have all files downloaded and unzipped, you should process them to translate Amplitude events to Posthog events and send them to a Posthog server.

You can start the process by using:
```
(php or sail) artisan aplistorical:processFolder {jobId}
```

This command process all files downloaded from Amplitude, translates all events stored in them and sends the events to Posthog in batches defined by the `--destination-batch=XXXX` option for the `aplistorical:createMigrationJob`command.

Every single file is deleted after be processed.

**Note:** failed batches are stored in `storage/app/migrationsJobs/{jobId}/up/failedSends.json` so you can review it and resend using your own code or any tool like  ([Postman](https://www.postman.com/)).


## Mapped properties
Every Amplitude event is translated to a Posthog event using this properties concordance.

| Amplitude JSON property | Posthog JSON property |
| --- | --- |
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

You can set `--user-properies-mode` to `root` or `property` to put Amplitude's user_properties as Posthog event properties (at root level or under user_properties inside the event properties, respectively).

We also use `$pageview` as the `event` in Posthog when the `event_type`equals `Viewed Page`, `PageVisited`or `pagevisited` in Amplitude.


## Artisan Commands

### 🖲 aplistorical:createMigrationJob
Use this command to create a Migration Job by providing date from, date to and all the information to connect with both source and destination. 

You will receive a Migration Job ID that should be used for downloading and processing events.

#### Usage:
```
aplistorical:createMigrationJob [options] [--] [<dateFrom> [<dateTo> [<jobName> [<sourceDriver> [<destinationDriver>]]]]]
```

**Arguments:**
| Argument | Description |
| --- | --- |
 `dateFrom` | Start date in format YYYYMMDD”T”HH (Ex. 20211018T00) A complete day is between T00 and T23 |
| `dateTo` | End date in format YYYYMMDDTHH (Ex. 20211018”T”23) A complete day is between T00 and T23 |
| `jobName` |  Job name … _[default: “UntitledMigration”]_
| `sourceDriver` | Defines the data source driver. Currently only Amplitude is supported _[default: “amplitude”]_ |
| `destinationDriver` | Defines the destination driver. Currently only Posthog is supported _[default: “posthog”]_ |


**Options:**
| Option | Description |
| --- | --- |
| `--aak[=AAK]` | Amplitude API Key. |
| `--ask[=ASK]` | Amplitude Secret Key |
| `--preserve-sources` | Do not delete downloaded files after processing it |
| `--ppk[=PPK]` | Posthog Project API Key |
| `--piu[=PIU]` | Posthog Instance Url |
| `--preserve-translations` | Store translated events into a backup file |
| `--do-not-parallelize` | Disable parallel translation jobs. Note: parallelizing is currently not supported. |
| `--destination-batch[=DESTINATION-BATCH]` | How many events should be sent per destination API call. *_Default: 1000_* |
| `--sleep-interval[=SLEEP-INTERVAL]` | Sleeping time in milliseconds between destination batches. _Default is 1000_ |
| `--ignore=eventName` | Do not migrate this specific event name. You can include as many as you want. |
| `--event-properties-mode=mode` | Put Amplitude's user_properties as event properties at root level (`root`) or under user_properties (`property`) |
| `--ssl-strict` | Do not ignore SSL certificate issues when connecting with both source and destination. |
| `-h`, `--help`  | Display help for the given command. When no command is given display help for the list command |
| `-q`, `--quiet`  | Do not output any message |
| `-V`, `--version`  | Display this application version |

### 🖲 aplistorical:getFromAmplitude
Connects with Amplitude and downloads all data for a specified Migration Job.

#### Usage:
```
aplistorical:getFromAmplitude <jobId>
```

**Arguments:**
| Argument | Description |
| --- | --- |
 `jobId` | Job id to download files from Amplitude |



**Options:**
| Option | Description |
| --- | --- |
| `-h`, `--help`  | Display help for the given command. When no command is given display help for the list command |
| `-q`, `--quiet`  | Do not output any message |
| `-V`, `--version`  | Display this application version |


### 🖲 aplistorical:processFolder
Takes, translates and uploads all downloaded files for a specified Migration Job 

#### Usage:
```
aplistorical:processFolder <jobId>
```

**Arguments:**
| Argument | Description |
| --- | --- |
 `jobId` | JobId to identify source folder and update project status |

**Options:**
| Option | Description |
| --- | --- |
| `--limit=X`  | Limit the number of files processed in this execution. |
| `-h`, `--help`  | Display help for the given command. When no command is given display help for the list command |
| `-q`, `--quiet`  | Do not output any message |
| `-V`, `--version`  | Display this application version |

## Useful Links
What is Posthog? 👉 [PostHog - Open-Source Product Analytics](https://posthog.com/)

Learn how to deply a clean Laravel Sail installation 👉 [Installation - Laravel - The PHP Framework For Web Artisans](https://laravel.com/docs/8.x/installation#your-first-laravel-project)

Laravel Sail Docs 👉 [Laravel Sail - Laravel - The PHP Framework For Web Artisans](https://laravel.com/docs/8.x/sail)

What is Metricool? 👉 [Metricool](https://metricool.com/)


## Contributing
Any feedback, help, or contribution will be welcome.

I’m not a professional programmer, so it will be easy to find mistakes and not optimized code. Your contributions will become my learning. Thanks!

You can find the critical code in these files:
* **app/Aplistorical/SdAmplitude.php** Code for downloading files from Amplitude.
* **app/Aplistorical/Amplitude2Posthog.php** Code for translations and uploading events to Posthog.
* **app/Console/Commands/** Console artisan commands (createMigrationJob, getFromAmplitude, processFolder).
* **app/Models/MigrationJobs.php** Model for MigrationJobs.
* database/migrations/ Migrations for creating de database table.

**Note:** Aplistorical stores the migration jobs into a SQLite file placed in `storage/aplistorical/db/database.sqlite` . Please, make sure this file exists or create it by this command.

```
touch storage/aplistorical/db/database.sqlite
(sail or php) artisan migrate:fresh
```



## Credits
<p align="center">
  <img src="https://metricool.com/wp-content/uploads/metricool-logo-reduced-21.png" />
</p>

This tool is created and maintained by Víctor Campuzano, Head of Growth at [Metricool](https://metricool.com) .
