<?php

namespace App\Aplistorical;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use PostHog\PostHog;

class Amplitude2Posthog
{
    protected $phPK = '';
    protected $phIU;
    protected $batch;
    protected $wait;
    protected $posthog;


    public function __construct($phPK, $phIU = 'https://app.posthog.com', $batch = 10000, $wait = 5000)
    {
        $this->phPK = $phPK;
        $this->phIU = $phIU;
        $this->batch = $batch;
        $this->wait = $wait;
        $this->posthog = new \PostHog\PostHog();
        $this->posthog->init($phPK, array('host' => $phIU));
    }

    public function processFile($file, $bkpath = '')
    {
        $gzfile = Storage::path('migrationJobs/1/down/158257/158257_2020-12-01_0#277.json.gz');
        $lines = gzfile($gzfile);
        $id = 0;
        $uindex = array();
        foreach ($lines as $line) {
            $event = json_decode($line);
            if (!isset($event->user_id) || $event->user_id === '') {
                continue;
            }
            if (!isset($uindex[$event->user_id])) {
                $uindex[$event->user_id] = array();
            }
            array_push($uindex[$event->user_id], $this->mapProperties($event));
            if (++$id > $this->batch) {
                // Hemos completado un batch, ahora hay que enviar y resetear valores ... 
                //print_r($uindex);
                $this->processEvents($uindex);
                $uindex = array();
                $id = 0;
                usleep($this->wait * 1000);
                return true;
            }
        }
        if ($id > 0) {
            $this->processEvents($uindex);
        }
    }

    protected function processEvents($events)
    {
        foreach ($events as $user => $events) {
            //$this->posthog->identify(array('distinctId'=>$user,'properties'=>array()));
            print_r("[" . $user . "] Idenfying ..." . PHP_EOL);
            foreach ($events as $event) {
                //print_r("[".$user."] ".$event["event"].PHP_EOL);
                //print_r($event);
                $this->posthog->capture($event);
            }
            $this->posthog->flush();
        }
    }

    protected function mapProperties($amplitudeEvent)
    {
        if (!isset($amplitudeEvent->user_id) || $amplitudeEvent->user_id === '') {
            return false;
        }
        $PosthogEvent = array(
            'distinctId' => $amplitudeEvent->user_id,
            'distinct_id' => $amplitudeEvent->user_id,
            'timestamp' => $amplitudeEvent->event_time,
            'event' => ($amplitudeEvent->event_type === 'Viewed  Page' || $amplitudeEvent->event_type === 'PageVisited' || $amplitudeEvent->event_type === 'pagevisited') ? '$pageview' : $amplitudeEvent->event_type,
            'properties' => array(
                'distinct_id' => $amplitudeEvent->user_id,
                'distinctId' => $amplitudeEvent->user_id,
                '$anon_distinct_id' => 'anonUserId-' . $amplitudeEvent->user_id
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
            $PosthogEvent['properties']['$time'] = $amplitudeEvent->event_time;
        }
        if (isset($amplitudeEvent->event_time)) {
            $PosthogEvent['properties']['$timestamp'] = $amplitudeEvent->event_time;
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
            $PosthogEvent['properties']['$set'] = array();
            foreach ($amplitudeEvent->user_properties as $clave => $valor) {
                $PosthogEvent['properties']['$set'][$clave] = $valor;
            }
        }


        return $PosthogEvent;
    }

    public function sendBatch($body)
    {
        $body = gzencode($body);

        return $this->sendRequest(
            $this->phIU.'/batch/',
            $body,
            [
                // Send user agent in the form of {library_name}/{library_version} as per RFC 7231.
                "User-Agent: {aplistorical/batch}",
            ]
        );
    }

    public function sendRequest(string $url, ?string $payload, array $extraHeaders = [])
    {
        $ch = curl_init();

        if (null !== $payload) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $headers = [];
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Encoding: gzip';

        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, $extraHeaders));
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $httpResponse = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        //close connection
        curl_close($ch);

        if (200 != $responseCode) {
            // Mierda, gestionar el error.
        } else {
            // No ha habido error... 
        }
        return $httpResponse;
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
