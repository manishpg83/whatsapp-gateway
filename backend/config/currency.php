<?php

return [

    /*
    | Visitors whose domain ends with one of these (comma-separated) see
    | South African Rand prices, e.g. instamessage.co.za. Every other
    | domain shows Indian Rupees (the default).
    */
    'zar_domains' => array_values(array_filter(array_map('trim', explode(',', env('CURRENCY_ZAR_DOMAINS', '.za'))))),

    /*
    | Force one currency on every domain — e.g. CURRENCY_FORCE=ZAR to try the
    | South African prices on localhost. Leave empty to choose by domain.
    */
    'force' => env('CURRENCY_FORCE'),

];
