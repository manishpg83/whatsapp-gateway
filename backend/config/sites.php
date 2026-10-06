<?php

return [

    /*
    | The two public domains of the same product. Used for the hreflang
    | links that tell search engines one is for India and the other for
    | South Africa (so they don't compete as duplicates). Leave one empty
    | to turn the links off — e.g. until the Indian domain is live.
    | Which domain shows Rand prices is set in config/currency.php.
    */
    'india_domain' => env('SITE_DOMAIN_IN', 'instamessage.in'),

    'south_africa_domain' => env('SITE_DOMAIN_ZA', 'instamessage.co.za'),

];
