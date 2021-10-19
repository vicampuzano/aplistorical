<?php 
return [
    /*
        Standard Server	https://amplitude.com/api/2/export
        EU Residency Server	https://analytics.eu.amplitude.com/api/2/export
    */
    'amplitude_endpoint' => 'https://amplitude.com/api/2/export',
    'amplitude_api_key' => '',
    'amplitude_secret_key' => '',

    /* Posthog Configuration */
    'posthog_project_api_key' => '',
    'posthog_instance_address' => '',
    'posthog_ignore_ssl_issues' => true,
    
    /* Migration date range */
    'start' => '20190101T00',
    'end' => '20211231T23',

    'json_mapping' => [
        'userId' => 'userId'
    ]
];