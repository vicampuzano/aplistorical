<?php

namespace App\Console\Commands;

use App\Aplistorical\SdAmplitude;
use Illuminate\Console\Command;

use app\Drivers\AmplitudeDownloader;

class getFromAmplitude extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aplistorical:getFromAmplitude
    {jobId : Job id to download files from Amplitude}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Connects with Amplitude and downloads all data for a specified Migration Job';

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
        $sdamp = new SdAmplitude($this->argument('jobId'));
        $this->line("Ok, let's download all files ... It willl take a long while. Be patient.");
        $this->line("You can check the log file for debugging info. You can use this command tail -f storage/logs/laravel.log");
        $sdamp->getData();
        $this->info("Process completed!");
        return Command::SUCCESS;
    }
}
