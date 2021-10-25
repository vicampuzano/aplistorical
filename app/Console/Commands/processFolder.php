<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class processFolder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aplistorical:processFolder
    {jobId : JobId to identify source folder and update project status }';

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
        $folder = Storage::path("migrationJobs/$this->argument('jobId')/down/");
        $allfiles = $this->getAllFiles($folder);
        
        return Command::SUCCESS;
    }

    protected function getAllFiles($dir)
    {

        $result = array();

        $cdir = scandir($dir);
        foreach ($cdir as $key => $value) {
            if (!in_array($value, array(".", "..", "bk"))) {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $value)) {
                    $result = array_merge($result, $this->getAllFiles($dir . DIRECTORY_SEPARATOR . $value));
                } else {
                    if(str_ends_with($value,'json.gz')) {
                        $result[] = $value;
                    }
                }
            }
        }

        return $result;
    }
}
