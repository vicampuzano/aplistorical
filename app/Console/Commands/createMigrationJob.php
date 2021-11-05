<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\MigrationJobs;

class createMigrationJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aplistorical:createMigrationJob
        {dateFrom? : Start date in format YYYYMMDD"T"HH (Ex. 20211018T00) A complete day is between T00 and T23}
        {dateTo? : End date in format YYYYMMDDTHH (Ex. 20211018"T"23) A complete day is between T00 and T23} 
        {jobName=UntitledMigration : Job name ... } 
        {sourceDriver=amplitude : Defines the data source driver. Currently only Amplitude is supported} 
        {destinationDriver=posthog : Defines the destination driver. Currently only Posthog is supported} 

        {--ignore=* : Do not migrate this specific event name. You can include as many as you want. }

        {--aak= : Amplitude API Key} 
        {--ask= : Amplitude Secret Key} 
        {--preserve-sources : Do not delete downloaded files after process it } 
        {--user-properties-mode= : Use root to put user_properties as event properties. Use propertie to put user_properties under user_properties inside the event properties. } 

        {--ppk= : Posthog Project API Key} 
        {--piu= : Posthog Instance Url} 
        {--preserve-translations : Store translated events into a backup file } 
        {--do-not-parallelize : Disable parallel translation jobs. Note: parallelizing is currently not supported. } 

        {--destination-batch= : How many events should be sent per destination API call}
        {--sleep-interval= : Sleeping time in milliseconds between destination batches }     

        {--ssl-strict : Do not ignore SSL certificate issues when connecting with both source and destination} 
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Use this command to create a Migration Job by providing date from, date to and all the information to connect with both source and destination. 
        You will receive a Migration Job ID that should be used for downloading and processing events.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $sourceConfig = array();
        $destinationConfig = array();

        if (($this->argument('sourceDriver') === null) || (strtolower($this->argument('sourceDriver')) !== 'amplitude')) {
            // To do: mostrar mensaje de error
        }
        if (($this->argument('destinationDriver')) || (strtolower($this->argument('destinationDriver')) !== 'posthog')) {
            // To do: mostrar mensaje de error
        }

        if ($this->argument('dateFrom') === null) {
            $askFrom = $this->ask('Please, provide FROM date to start the migration. Format: YYYYMMDD"T"HH Ex: 20211018T00 .');
            $sourceConfig["dateFrom"] = $this->validateDateHour($askFrom);
        } else {
            $sourceConfig["dateFrom"] = $this->validateDateHour($this->argument('dateFrom'));
        }
        if (!$sourceConfig['dateFrom']) {
            $this->error('Date from must be in this format 20211231T00.. T00 to T23.');
            return -1;
        }
        if ($this->argument('dateTo') === null) {
            $askTo = $this->ask('Please, provide TO date & hour to END the migration. Format: YYYYMMDD"T"HH Ex: 20211018T00 .');
            $sourceConfig["dateTo"] = $this->validateDateHour($askTo);
        } else {
            $sourceConfig["dateTo"] = $this->validateDateHour($this->argument('dateTo'));
        }
        if (!$sourceConfig['dateTo']) {
            $this->error('Date To must be in this format 20211231T00.. T00 to T23.');
            return -1;
        }
        if ($this->option('aak') === null) {
            $sourceConfig["aak"] = $this->secret('Please, provide Amplitude API Key.');
        } else {
            $sourceConfig["aak"] = $this->option('aak');
        }
        if ($this->option('ask') === null) {
            $sourceConfig["ask"] = $this->secret('Please, provide Amplitude Secret Key.');
        } else {
            $sourceConfig["ask"] = $this->option('ask');
        }
        if ($this->option('ppk') === null) {
            $destinationConfig["ppk"] = $this->secret('Please, provide a PostHog project API Key.');
        } else {
            $destinationConfig["ppk"] = $this->option('ppk');
        }
        if ($this->option('piu') === null) {
            $destinationConfig["piu"] = $this->ask('Please, provide a PostHog Instance Url.');
        } else {
            $destinationConfig["piu"] = $this->option('piu');
        }
        $destinationConfig["ssl_strict"] = ($this->option('ssl-strict') === null ? true : false);
        if ($this->option('user-properties-mode')!==null && ($this->option('user-properties-mode')==='root' || $this->option('user-properties-mode')==='property')) {
            $destinationConfig['userPropertiesMode'] = $this->option('user-properties-mode');
        }

        if (is_array($this->option('ignore'))) {
            $destinationConfig["ignoreEvents"] = $this->option('ignore');
        }

        $newJob = new MigrationJobs();
        $newJob->source_config = $sourceConfig;
        $newJob->destination_config = $destinationConfig;
        $newJob->job_label = $this->argument('jobName');
        $newJob->preserve_sources = ($this->option('preserve-sources') === null ? false : true);
        $newJob->preserve_translations = ($this->option('preserve-translations') === null ? false : true);
        $newJob->parallelize_translations = ($this->option('do-not-parallelize') === null ? true : false);
        if ($this->option('destination-batch') !== null && is_numeric($this->option('destination-batch')) && $this->option('destination-batch') > 0) {
            $newJob->destination_batch = $this->option('destination-batch');
        }
        if ($this->option('sleep-interval') !== null && is_numeric($this->option('sleep-interval')) && $this->option('sleep-interval') > 0) {
            $newJob->sleep_interval = $this->option('sleep-interval');
        }
        $newJob->save();

        $this->info("New Job stored with id -> " . $newJob->id);

        Storage::makeDirectory("migrationJobs/" . $newJob->id);
        Storage::makeDirectory("migrationJobs/" . $newJob->id . "/tmp");
        Storage::makeDirectory("migrationJobs/" . $newJob->id . "/down");
        Storage::makeDirectory("migrationJobs/" . $newJob->id . "/down/bk");
        Storage::makeDirectory("migrationJobs/" . $newJob->id . "/up");
        Storage::makeDirectory("migrationJobs/" . $newJob->id . "/up/bk");
        Storage::makeDirectory("migrationJobs/" . $newJob->id . "/log");

        return Command::SUCCESS;
    }

    private function validateDateHour($date)
    {
        try {
            return \DateTime::createFromFormat('Ymd\TH', $date)->format('Ymd\TH');
        } catch (\Throwable $th) {
            $this->error('Provided date is not in the format YYYYMMDDTHH. Remember T is a literal.');
            die();
        }
    }
}
