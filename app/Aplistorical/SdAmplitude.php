<?php

namespace App\Aplistorical;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;


use Illuminate\Support\Facades\Storage;
use App\Models\MigrationJobs;

class SdAmplitude
{
    protected $jobid;
    protected $aak;
    protected $ask;
    protected $preserve_sources;

    public function __construct($jobid)
    {
        $this->jobid = $jobid;
        $migrationJob = MigrationJobs::find($this->jobid);

        if ($migrationJob === null) {
            die('MigrationJob not found....');
        }

        $this->aak = $migrationJob["source_config"]["aak"];
        $this->ask = $migrationJob["source_config"]["ask"];
        $this->preserve_sources=$migrationJob["preserve_sources"];
    }

    public function getData($restart = false)
    {
        $migrationJob = MigrationJobs::find($this->jobid);

        if ($migrationJob === null) {
            die('MigrationJob not found....');
        }

        $this->aak = $migrationJob["source_config"]["aak"];
        $this->ask = $migrationJob["source_config"]["ask"];
        $from = $migrationJob['source_config']['dateFrom'];
        $to = $migrationJob['source_config']['dateTo'];
        $start = $migrationJob['last_downloaded'];
        $granularity = $migrationJob['source_ganularity'];
    }

    public function downloadRange($start, $end, $region = null)
    {
        // Initialize variables
        $url = ($region === 'eu') ? 'https://analytics.eu.amplitude.com/api/2/export' : 'https://amplitude.com/api/2/export';

        $tmppath = Storage::path('migrationJobs/' . $this->jobid . '/tmp/');
        $savepath = Storage::path('migrationJobs/' . $this->jobid . '/down/');

        $tmpFile = tempnam($tmppath, 'ampdown');
        $handle = fopen($tmpFile, 'w');
        $zipfile = $savepath . 'bk/' . $start . "-" . $end . ".zip";


        $client = new Client(array(
            'base_uri' => '',
            'query' => [
                'start' => $start,
                'end' => $end
            ],
            'auth' => [
                $this->aak, $this->ask
            ],
            'verify' => false,
            'sink' => $zipfile,
            'curl.options' => array(
                'CURLOPT_RETURNTRANSFER' => true,
                'CURLOPT_FILE' => $handle
            )
        ));
        
        try {
            $response=$client->get($url);
            if ($response->getStatusCode()===400) {
                // Return file too large
                fclose($handle);
                unlink($tmpFile);
                return 1;
            }
        } catch (RequestException $e) {
            fclose($handle);
            unlink($tmpFile);
            return 2;
        }
        
        fclose($handle);
        unlink($tmpFile);

        $zip = new \ZipArchive;
        if ($zip->open($zipfile) === TRUE) {
            $zip->extractTo($savepath);
            $zip->close();
        } else {     
            return 3;
        }

        if (!$this->preserve_sources) { unlink($zipfile); }

        return 0;
    }
}
