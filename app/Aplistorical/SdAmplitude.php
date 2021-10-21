<?php

namespace App\Aplistorical;

use App\Models\MigrationJobs;

class SdAmplitude
{
    protected $jobid;

    public function __construct( $jobid ){ 
        $this->jobid=$jobid;
    }

    public function getData($jobid) {
        $migrationJob = MigrationJobs::find($jobid);

        if ($migrationJob===null) {
            die('MigrationJob not found....');
        }

        $apikey=$migrationJob["source_config"]["apikey"];
        $secretkey=$migrationJob["source_config"]["secretkey"];
        $from=$migrationJob['source_config']['dateFrom'];
        $to=$migrationJob['source_config']['dateTo'];
        $start=$migrationJob['last_downloaded'];
        $granularity=$migrationJob['source_ganularity'];

        
    }
}
