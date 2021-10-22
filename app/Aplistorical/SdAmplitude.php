<?php

namespace App\Aplistorical;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Storage;
use App\Models\MigrationJobs;
use DateInterval;

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
        Log::withContext([
            'jobid' => $this->jobid
        ]);
        $migrationJob = MigrationJobs::find($this->jobid);

        if ($migrationJob === null) {
            die('MigrationJob not found....');
        }

        $this->aak = $migrationJob["source_config"]["aak"];
        $this->ask = $migrationJob["source_config"]["ask"];
        $from = \DateTime::createFromFormat('Ymd\TH',$migrationJob['source_config']['dateFrom']);
        $to = \DateTime::createFromFormat('Ymd\TH',$migrationJob['source_config']['dateTo']);
        $ld = (($migrationJob['last_downloaded']===null)?(clone $from):$migrationJob['last_downloaded'])->sub(new DateInterval('PT1H'));
        $start = ($ld > $from)?(clone $ld):(clone $from);
        $end = (clone $ld);
        //$granularity = $migrationJob['source_ganularity'];
        $granularity = 3;

        while ($ld < $to) {
            switch ($granularity) {
                case 3:
                    // Every hour download
                    $end = $ld->add(new DateInterval('PT1H'));
                    $end = ($end<=$to)?$end:$to;
                    break;
                case 2:
                    // Every day
                    $end = $ld->add(new DateInterval('P1D'));
                    $end = ($end<=$to)?$end:$to;
                    break;
                case 1:
                    // Every week
                    $end = $ld->add(new DateInterval('P1W'));
                    $end = ($end<=$to)?$end:$to;
                    break;
                default:
                    $end = $ld->add(new DateInterval('P1M'));
                    $end = ($end<=$to)?$end:$to;
            }
            print_r(' Trying to download interval from '.$start->format('Ymd\TH').' to '.$end->format('Ymd\TH').PHP_EOL);

            $start = (clone $end);
            $start = $start->add(new DateInterval('PT1H'));
            $ld = clone $end;
        }

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
