<?php

return [
    'environment' => getenv('DIAN_ENVIRONMENT') ?: 'HABILITACION',
    
    'api_urls' => [
        'HABILITACION' => 'https://api-hab.dian.gov.co',
        'PRODUCCION' => 'https://api.dian.gov.co'
    ],
    
    'certificate' => [
        'path' => getenv('DIAN_CERTIFICATE_PATH') ?: '',
        'password' => getenv('DIAN_CERTIFICATE_PASSWORD') ?: ''
    ],
    
    'credentials' => [
        'username' => getenv('DIAN_USERNAME') ?: '',
        'password' => getenv('DIAN_PASSWORD') ?: ''
    ],
    
    'endpoints' => [
        'send_bill' => '/ubl2.1/send-bill',
        'get_status' => '/ubl2.1/get-status',
        'get_cdr' => '/ubl2.1/get-cdr'
    ],
    
    'timeout' => 30,
    'retry_attempts' => 3,
    'retry_delay' => 2
];

