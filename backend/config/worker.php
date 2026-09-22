<?php

return [

    // Where the Node.js worker listens. Keep this pointed at 127.0.0.1 —
    // the worker is never meant to be reachable from outside this machine.
    'base_url' => env('WORKER_BASE_URL', 'http://127.0.0.1:3001'),

    // Shared secret with the worker. Must match INTERNAL_API_SECRET in
    // whatsapp-worker/.env exactly. Sent as the X-Internal-Secret header
    // in both directions.
    'secret' => env('INTERNAL_API_SECRET'),

];
