<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Aplistorical\Amplitude2Posthog;
use App\Models\MigrationJobs;

class processFolder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aplistorical:processFolder
    {jobId : JobId to identify source folder and update project status }
    {--limit= : Maximum files to process in this execution. Default: all files.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Takes, translates and uploads all downloaded files for a specified Migration Job ';

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
        $mj = MigrationJobs::find($this->argument('jobId'));
        $a2p = new Amplitude2Posthog($mj['destination_config']['ppk'], $mj['destination_config']['piu'], $mj['destination_batch'], $mj['sleep_interval']);
        $folder = Storage::path("migrationJobs/" . $this->argument('jobId') . "/down/");
        $failedPayloads = Storage::path("migrationJobs/" . $this->argument('jobId') . "/up/failedSends.json");
        $a2p->setFailedFile($failedPayloads);
        $bkevents = 'file://' . Storage::path("migrationJobs/" . $this->argument('jobId') . "/up/bk/upload-" . $this->argument('jobId') . ".events");
        if ($mj['preserve_translations']) {
            $a2p->setSaveString($bkevents);
        }
        if (array_key_exists('user-properties-mode',$mj['destination_config'])) {
            $a2p->setUserPropertiesMode($mj['destination_config']['user-properties-mode']);
        }
        if (array_key_exists('ignoreEvents',$mj['destination_config'])) {
            $a2p->setIgnoreEvents($mj['destination_config']['ignoreEvents']);
        }
        if (array_key_exists('rename',$mj['destination_config'])) {
            $a2p->setRenameEvents($mj['destination_config']['rename']);
        }
        $allfiles = $this->getAllFiles($folder);
        $fileCount = count($allfiles);

        if ($this->option('limit') !== null && is_numeric($this->option('limit')) && $this->option('limit') > 0) {
            $filelimit = $this->option('limit');
            $bar = $this->output->createProgressBar($filelimit);
            $this->line("Processing " . $filelimit . " files for Job: " . $this->argument('jobId'));
        } else {
            $filelimit = 1000000;
            $bar = $this->output->createProgressBar($fileCount);
            $this->line("Processing " . $fileCount . "files for Job: " . $this->argument('jobId'));
        }
        $bar->start();
        foreach ($allfiles as $file) {
            if (!$a2p->processFile($file, 'file://' . $bkevents)) {
                $this->error("File $file processed with errors. Check de log");
            }
            unlink($file);
            $bar->advance();
            if (--$filelimit <= 0) {
                break;
            }
        }

        $bar->finish();

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
                    if (str_ends_with($value, 'json.gz')) {
                        $result[] = $dir . '/' . $value;
                    }
                }
            }
        }

        return $result;
    }
}
