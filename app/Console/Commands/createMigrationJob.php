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
        {sourceDriver=amplitude : It defines the data source driver. Currently only amplitude is supported} 
        {destinationDriver=posthog : It defines the destination driver. Currently only posthog is supported} 

        {--aak= : Amplitude Api Key} 
        {--ask= : Amplitude Secret Key} 
        {--preserve-sources : Do not delete downloaded files after process it } 

        {--ppk= : Posthog Project Api Key} 
        {--piu= : Posthog Instance Url} 
        {--preserve-translations : Do not delete translated files after process it } 
        {--do-not-parallelize : Disable parallel translation jobs } 

        {--destination-batch= : How many events should be sent per destination API call}
        {--sleep-interval= : Sleep time in milliseconds between destination batches }     

        {--ssl-strict : Do not ignore ssl certificate issues when connecting with both source or destination} 
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
            $askFrom = $this->ask('Please, provide the date & hour to START the migration. Format: YYYYMMDD"T"HH Ex: 20211018T00 .');
            $sourceConfig["dateFrom"] = $this->validateDateHour($askFrom);
        } else {
            $sourceConfig["dateFrom"] = $this->validateDateHour($this->argument('dateFrom'));
        }
        if (!$sourceConfig['dateFrom']) {
            $this->error('Date from must have this format 20211231T00.. T00 to T23.');
            return -1;
        }
        if ($this->argument('dateTo') === null) {
            $askTo = $this->ask('Please, provide the date & hour to END the migration. Format: YYYYMMDD"T"HH Ex: 20211018T00 .');
            $sourceConfig["dateTo"] = $this->validateDateHour($askTo);
        } else {
            $sourceConfig["dateTo"] = $this->validateDateHour($this->argument('dateTo'));
        }
        if (!$sourceConfig['dateTo']) {
            $this->error('Date To must have this format 20211231T00.. T00 to T23.');
            return -1;
        }
        if ($this->option('aak') === null) {
            $sourceConfig["aak"] = $this->ask('Please, provide Amplitude API Key.');
        } else {
            $sourceConfig["aak"] = $this->option('aak');
        }
        if ($this->option('ask') === null) {
            $sourceConfig["ask"] = $this->ask('Please, provide Amplitude Secret Key.');
        } else {
            $sourceConfig["ask"] = $this->option('ask');
        }
        if ($this->option('ppk') === null) {
            $destinationConfig["ppk"] = $this->ask('Please, provide a PostHog project API Key.');
        } else {
            $destinationConfig["ppk"] = $this->option('ppk');
        }
        if ($this->option('piu') === null) {
            $destinationConfig["piu"] = $this->ask('Please, provide a PostHog Instance Url.');
        } else {
            $destinationConfig["piu"] = $this->option('piu');
        }
        $destinationConfig["ssl_strict"] = ($this->option('ssl-strict') === null ? true : false);

        $newJob = new MigrationJobs();
        $newJob->source_config = $sourceConfig;
        $newJob->destination_config = $destinationConfig;
        $newJob->job_label = $this->argument('jobName');
        $newJob->preserve_sources = ($this->option('preserve-sources') === null ? false : true);
        $newJob->preserve_translations = ($this->option('preserve-translations') === null ? false : true);
        $newJob->parallelize_translations = ($this->option('do-not-parallelize') === null ? true : false);
        if ($this->option('destination-batch')!==null && is_numeric($this->option('destination-batch')) && $this->option('destination-batch')>0) {
            $newJob->destination_batch = $this->option('destination-batch');   
        }
        if ($this->option('sleep-interval')!==null && is_numeric($this->option('sleep-interval')) && $this->option('sleep-interval')>0) {
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
        return \DateTime::createFromFormat('Ymd\TH', $date)->format('Ymd\TH');
    }
}
