<?php

namespace App\Aplistorical;

use Illuminate\Support\Facades\Log;

class Amplitude2Posthog
{
    protected $phPK = '';
    protected $phIU;
    protected $batch;
    protected $wait;
    protected $posthog;
    protected $saveString;
    protected $saveType;
    protected $failedFile;
    protected $userPropertiesMode;
    protected $ignoreEvents = array();
    protected $renameEvents = array();

    /**
     * @param mixed $phPK
     * @param string $phIU
     * @param int $batch
     * @param int $wait
     */
    public function __construct($phPK, $phIU = 'https://app.posthog.com', $batch = 1000, $wait = 1000)
    {
        $this->phPK = $phPK;
        $this->phIU = $phIU;
        $this->batch = $batch;
        $this->wait = $wait;
        $this->posthog = new \PostHog\PostHog();
        $this->posthog->init($phPK, array('host' => $phIU));
        $this->saveString = '';
        $this->failedFile = '';
    }


    /**
     * Sets the SaveString param. Could be file://absoulte_path or sqlite://absolutepath
     * Note: Currently only file:// is supported. sqlite pool comming soon
     * 
     * @param string $filepath
     * @param string $type='file'
     * 
     * @return bool
     */
    public function setSaveString(string $filepath): bool
    {
        if ('file://' === substr($filepath, 0, 7)) {
            $this->saveString = str_replace('file://', '', $filepath);
            $this->saveType = "f"; // File type
            return true;
        } elseif ('sqlite://' === substr($filepath, 0, 9)) {
            $this->saveString = str_replace('sqlite://', '', $filepath);
            $this->saveType = "d"; // DbType type
            return true;
        }
        return false;
    }

    /**
     * 
     * 
     * @param string $filename
     * 
     * @return bool
     */
    public function setFailedFile(string $filename): bool
    {
        return $this->failedFile = $filename;
    }


    /**
     * @param string $mode
     * 
     * @return bool
     */
    public function setUserPropertiesMode(string $mode): bool
    {
        if ($mode === 'root' || $mode === 'property') {
            $this->userPropertiesMode = $mode;
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param array $ignoreEvents
     * 
     * @return bool
     */
    public function setIgnoreEvents(array $ignoreEvents): bool
    {
        $this->ignoreEvents = $ignoreEvents;
        return true;
    }


    public function setRenameEvents(array $renameEvents): bool
    {
        $this->renameEvents = $renameEvents;
        return true;
    }

    /**
     * Return true if $str starts with $startWith
     * @param string $str
     * @param string $startWith
     * 
     * @return bool
     */
    private function startsWith(string $str, string $startWith): bool
    {
        $len = strlen($startWith);
        return (substr($str, 0, $len) === $startWith);
    }


    /**
     * 
     * @param string $file Absolute path to gzipped file with amplitude events
     * 
     * @return bool true if everything was fine
     */
    public function processFile(string $file): bool
    {
        $lines = gzfile($file);
        $uindex = array();
        $count = 0;
        $skippedCount = 0;
        foreach ($lines as $line) {
            $event = json_decode($line);
            if (!isset($event->user_id) || $event->user_id === '') {
                Log::warning("Skipped line on file $file :::: $line");
                continue;
            }
            if (count($this->ignoreEvents) > 0 && in_array($event->event_type, $this->ignoreEvents)) {
                $skippedCount += 1;
                continue;
            }
            if (!isset($uindex[$event->user_id])) {
                $uindex[$event->user_id] = array();
                array_push($uindex[$event->user_id], $this->mapIdentify($event));
            }
            array_push($uindex[$event->user_id], $this->mapProperties($event));
            ++$count;
        }
        Log::debug("Fully processed $count lines from $file . $skippedCount lines skipped. Now start processing events ...");

        return $this->processEvents($uindex, ($this->saveString !== ''));
    }


    protected function processEvents(array $events, bool $save = false): bool
    {
        $batchEvents = array();
        $count = 0;
        $totalcount = 0;
        foreach ($events as $user => $events) {
            foreach ($events as $event) {
                array_push($batchEvents, $event);
                if ($save) {
                    $this->saveEvent($event);
                }
                if (++$count >= $this->batch) {
                    $this->sendBatch($batchEvents);
                    $batchEvents = array();
                    $totalcount += $count;
                    $count = 0;
                    usleep($this->wait * 1000);
                }
            }
        }
        if ($count > 0) {
            $this->sendBatch($batchEvents);
            $totalcount += $count;
        }
        Log::debug("Fully processed $totalcount events from file ...");

        return true;
    }

    /**
     * Translates an Amplitude Event into a Posthog event
     * @param mixed $amplitudeEvent json decoded Amplitude event array
     * 
     * @return array
     */
    protected function mapProperties($amplitudeEvent): array
    {
        $da = new \DateTime($amplitudeEvent->event_time);
        $convertedTimestamp = $da->format('Y-m-d\TH:i:sO');
        if (!isset($amplitudeEvent->user_id) || $amplitudeEvent->user_id === '') {
            return false;
        }

        $eventType = (count($this->renameEvents) > 0 && array_key_exists($amplitudeEvent->event_type,$this->renameEvents)) ? $this->renameEvents[$amplitudeEvent->event_type] : $amplitudeEvent->event_type;

        $PosthogEvent = array(
            'distinctId' => $amplitudeEvent->user_id,
            'distinct_id' => $amplitudeEvent->user_id,
            'timestamp' => $convertedTimestamp,
            'type' => 'capture',
            'event' => ($eventType === 'Viewed  Page' || $eventType === 'PageVisited' || $eventType === 'pagevisited') ? '$pageview' : $eventType,
            'properties' => array(
                'distinct_id' => $amplitudeEvent->user_id,
                'distinctId' => $amplitudeEvent->user_id,
                '$anon_distinct_id' == $amplitudeEvent->uuid
            )
        );
        if (isset($amplitudeEvent->ip_address)) {
            $PosthogEvent['properties']['$ip'] = $amplitudeEvent->ip_address;
        }
        if (isset($amplitudeEvent->library)) {
            $PosthogEvent['properties']['$lib'] = $amplitudeEvent->library;
        }
        if (isset($amplitudeEvent->version_name)) {
            $PosthogEvent['properties']['$lib_version'] = $amplitudeEvent->version_name;
        }
        if (isset($amplitudeEvent->event_time)) {
            $PosthogEvent['properties']['$time'] = $convertedTimestamp;
        }
        if (isset($amplitudeEvent->event_time)) {
            $PosthogEvent['properties']['$timestamp'] = $convertedTimestamp;
        }

        if (isset($amplitudeEvent->device_id)) {
            $PosthogEvent['properties']['device_id'] = $amplitudeEvent->device_id;
        }
        if (isset($amplitudeEvent->device_manufacturer)) {
            $PosthogEvent['properties']['device_manufacturer'] = $amplitudeEvent->device_manufacturer;
        }

        if (isset($amplitudeEvent->os)) {
            $PosthogEvent['properties']['$os'] = $amplitudeEvent->os;
        }
        if (isset($amplitudeEvent->os_version)) {
            $PosthogEvent['properties']['$os_version'] = $amplitudeEvent->os_version;
        }
        if (isset($amplitudeEvent->event_properties)) {
            foreach ($amplitudeEvent->event_properties as $clave => $valor) {
                if ($clave === 'language') {
                    $PosthogEvent['properties']['locale'] = $this->getLocaleCodeForDisplayLanguage($valor);
                } else {
                    $PosthogEvent['properties'][$clave] = $valor;
                }
            }
        }
        if (isset($amplitudeEvent->user_properties)) {
            switch ($this->userPropertiesMode) {
                case 'root':
                    foreach ($amplitudeEvent->user_properties as $clave => $valor) {
                        $PosthogEvent['properties'][$clave] = $valor;
                    }
                    break;
                case 'property':
                    $PosthogEvent['properties']['user_properties'] = array();
                    foreach ($amplitudeEvent->user_properties as $clave => $valor) {
                        $PosthogEvent['properties']['user_properties'][$clave] = $valor;
                    }
                    break;
                default:
                    $PosthogEvent['properties']['$set'] = array();
                    foreach ($amplitudeEvent->user_properties as $clave => $valor) {
                        $PosthogEvent['properties']['$set'][$clave] = $valor;
                    }
                    break;
            }
        }


        return $PosthogEvent;
    }


    /**
     * Translates Amplitude event into a Posthog identify payload
     * 
     * @param mixed $amplitudeEvent
     * 
     * @return array
     */
    protected function mapIdentify($amplitudeEvent): array
    {
        $da = new \DateTime($amplitudeEvent->event_time);
        $convertedTimestamp = $da->format('Y-m-d\TH:i:sO');

        if (!isset($amplitudeEvent->user_id) || $amplitudeEvent->user_id === '') {
            return false;
        }
        $PosthogEvent = array(
            'distinctId' => $amplitudeEvent->user_id,
            'distinct_id' => $amplitudeEvent->user_id,
            'timestamp' => $convertedTimestamp,
            'event' => '$identify',
            'type' => 'identify',
            'properties' => array(
                'distinct_id' => $amplitudeEvent->user_id,
                'distinctId' => $amplitudeEvent->user_id,
                '$anon_distinct_id' == $amplitudeEvent->uuid
            )
        );
        if (isset($amplitudeEvent->ip_address)) {
            $PosthogEvent['properties']['$ip'] = $amplitudeEvent->ip_address;
        }
        if (isset($amplitudeEvent->user_properties)) {
            $PosthogEvent['properties']['$set'] = array();
            foreach ($amplitudeEvent->user_properties as $clave => $valor) {
                $PosthogEvent['properties']['$set'][$clave] = $valor;
            }
        }
        return $PosthogEvent;
    }

    /**
     * @param array $body
     * 
     * @return int httpResponse code . 200 is ok.
     */
    public function sendBatch(array $body): int
    {
        $payload = json_encode(array(
            'batch' => $body,
            'api_key' => $this->phPK
        ));

        $body = gzencode($payload);

        $retval = $this->sendRequest(
            $this->phIU . '/batch/',
            $body,
            [
                // Send user agent in the form of {library_name}/{library_version} as per RFC 7231.
                "User-Agent: {aplistorical/batch}",
                "Content-Encoding: gzip"
            ]
        );

        if ($retval !== 200) {
            if ($this->failedFile !== '') {
                $this->saveFailed($payload);
                Log::error("Failed batch. Response code was " . $retval . ". Please check " . $this->failedFile);
            } else {
                Log::error("Failed batch::: $payload");
            }
        }
        return $retval;
    }

    /**
     * Send batch request to Posthog server. 
     * @param string $url Full url to Posthog server endpont
     * @param string $payload
     * @param array $extraHeaders
     * 
     * @return int Response code from curl 
     */
    public function sendRequest(string $url, string $payload, array $extraHeaders = []): int
    {
        $ch = curl_init();

        if (null !== $payload) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $headers = [];
        $headers[] = 'Content-Type: application/json';

        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, $extraHeaders));
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $httpResponse = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        //close connection
        curl_close($ch);

        if (200 !== $responseCode) {
            Log::error('The CURL call responded with bad response code ' . $responseCode . ' with this additional information ' . json_encode($httpResponse));
        }
        return $responseCode;
    }

    /**
     * Stores a copy of the event in a file
     * @param array $event
     * 
     * @return bool
     */
    protected function saveEvent(array $event): bool
    {
        if ($this->saveType === 'f') {
            return file_put_contents($this->saveString, json_encode($event), FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Stores a copy of the event in a file
     * @param array $event
     * 
     * @return bool
     */
    protected function saveFailed(string $payload): bool
    {
        return file_put_contents($this->failedFile, $payload, FILE_APPEND | LOCK_EX);
    }

    function getLocaleCodeForDisplayLanguage($name)
    {
        $languageCodes = array(
            "aa" => "Afar",
            "ab" => "Abkhazian",
            "ae" => "Avestan",
            "af" => "Afrikaans",
            "ak" => "Akan",
            "am" => "Amharic",
            "an" => "Aragonese",
            "ar" => "Arabic",
            "as" => "Assamese",
            "av" => "Avaric",
            "ay" => "Aymara",
            "az" => "Azerbaijani",
            "ba" => "Bashkir",
            "be" => "Belarusian",
            "bg" => "Bulgarian",
            "bh" => "Bihari",
            "bi" => "Bislama",
            "bm" => "Bambara",
            "bn" => "Bengali",
            "bo" => "Tibetan",
            "br" => "Breton",
            "bs" => "Bosnian",
            "ca" => "Catalan",
            "ce" => "Chechen",
            "ch" => "Chamorro",
            "co" => "Corsican",
            "cr" => "Cree",
            "cs" => "Czech",
            "cu" => "Church Slavic",
            "cv" => "Chuvash",
            "cy" => "Welsh",
            "da" => "Danish",
            "de" => "German",
            "dv" => "Divehi",
            "dz" => "Dzongkha",
            "ee" => "Ewe",
            "el" => "Greek",
            "en" => "English",
            "eo" => "Esperanto",
            "es" => "Spanish",
            "et" => "Estonian",
            "eu" => "Basque",
            "fa" => "Persian",
            "ff" => "Fulah",
            "fi" => "Finnish",
            "fj" => "Fijian",
            "fo" => "Faroese",
            "fr" => "French",
            "fy" => "Western Frisian",
            "ga" => "Irish",
            "gd" => "Scottish Gaelic",
            "gl" => "Galician",
            "gn" => "Guarani",
            "gu" => "Gujarati",
            "gv" => "Manx",
            "ha" => "Hausa",
            "he" => "Hebrew",
            "hi" => "Hindi",
            "ho" => "Hiri Motu",
            "hr" => "Croatian",
            "ht" => "Haitian",
            "hu" => "Hungarian",
            "hy" => "Armenian",
            "hz" => "Herero",
            "ia" => "Interlingua (International Auxiliary Language Association)",
            "id" => "Indonesian",
            "ie" => "Interlingue",
            "ig" => "Igbo",
            "ii" => "Sichuan Yi",
            "ik" => "Inupiaq",
            "io" => "Ido",
            "is" => "Icelandic",
            "it" => "Italian",
            "iu" => "Inuktitut",
            "ja" => "Japanese",
            "jv" => "Javanese",
            "ka" => "Georgian",
            "kg" => "Kongo",
            "ki" => "Kikuyu",
            "kj" => "Kwanyama",
            "kk" => "Kazakh",
            "kl" => "Kalaallisut",
            "km" => "Khmer",
            "kn" => "Kannada",
            "ko" => "Korean",
            "kr" => "Kanuri",
            "ks" => "Kashmiri",
            "ku" => "Kurdish",
            "kv" => "Komi",
            "kw" => "Cornish",
            "ky" => "Kirghiz",
            "la" => "Latin",
            "lb" => "Luxembourgish",
            "lg" => "Ganda",
            "li" => "Limburgish",
            "ln" => "Lingala",
            "lo" => "Lao",
            "lt" => "Lithuanian",
            "lu" => "Luba-Katanga",
            "lv" => "Latvian",
            "mg" => "Malagasy",
            "mh" => "Marshallese",
            "mi" => "Maori",
            "mk" => "Macedonian",
            "ml" => "Malayalam",
            "mn" => "Mongolian",
            "mr" => "Marathi",
            "ms" => "Malay",
            "mt" => "Maltese",
            "my" => "Burmese",
            "na" => "Nauru",
            "nb" => "Norwegian Bokmal",
            "nd" => "North Ndebele",
            "ne" => "Nepali",
            "ng" => "Ndonga",
            "nl" => "Dutch",
            "nn" => "Norwegian Nynorsk",
            "no" => "Norwegian",
            "nr" => "South Ndebele",
            "nv" => "Navajo",
            "ny" => "Chichewa",
            "oc" => "Occitan",
            "oj" => "Ojibwa",
            "om" => "Oromo",
            "or" => "Oriya",
            "os" => "Ossetian",
            "pa" => "Panjabi",
            "pi" => "Pali",
            "pl" => "Polish",
            "ps" => "Pashto",
            "pt" => "Portuguese",
            "qu" => "Quechua",
            "rm" => "Raeto-Romance",
            "rn" => "Kirundi",
            "ro" => "Romanian",
            "ru" => "Russian",
            "rw" => "Kinyarwanda",
            "sa" => "Sanskrit",
            "sc" => "Sardinian",
            "sd" => "Sindhi",
            "se" => "Northern Sami",
            "sg" => "Sango",
            "si" => "Sinhala",
            "sk" => "Slovak",
            "sl" => "Slovenian",
            "sm" => "Samoan",
            "sn" => "Shona",
            "so" => "Somali",
            "sq" => "Albanian",
            "sr" => "Serbian",
            "ss" => "Swati",
            "st" => "Southern Sotho",
            "su" => "Sundanese",
            "sv" => "Swedish",
            "sw" => "Swahili",
            "ta" => "Tamil",
            "te" => "Telugu",
            "tg" => "Tajik",
            "th" => "Thai",
            "ti" => "Tigrinya",
            "tk" => "Turkmen",
            "tl" => "Tagalog",
            "tn" => "Tswana",
            "to" => "Tonga",
            "tr" => "Turkish",
            "ts" => "Tsonga",
            "tt" => "Tatar",
            "tw" => "Twi",
            "ty" => "Tahitian",
            "ug" => "Uighur",
            "uk" => "Ukrainian",
            "ur" => "Urdu",
            "uz" => "Uzbek",
            "ve" => "Venda",
            "vi" => "Vietnamese",
            "vo" => "Volapuk",
            "wa" => "Walloon",
            "wo" => "Wolof",
            "xh" => "Xhosa",
            "yi" => "Yiddish",
            "yo" => "Yoruba",
            "za" => "Zhuang",
            "zh" => "Chinese",
            "zu" => "Zulu"
        );
        return array_search($name, $languageCodes);
    }
}
