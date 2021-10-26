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
    {jobId : Job id to download}';

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
        $sdamp = new SdAmplitude($this->argument('jobId'));
        $this->line("Ok, let's download all files ... It willl take a few minutes. Be patient.");
        $this->line("Please, check the logs for debug info...");
        $sdamp->getData();
        $this->info("Process completed!");
        return Command::SUCCESS;
    }
}
