<?php

/*
|--------------------------------------------------------------------------
| Bulk messaging (Bulk messages → campaigns)
|--------------------------------------------------------------------------
|
| A campaign sends one message, then waits `interval` seconds before the
| next (App\Jobs\SendBulkMessage). Steady pacing keeps a customer's number
| from sending in spam-like bursts. The customer picks one of
| `interval_options`; nothing faster than the smallest is allowed.
|
*/

return [

    'interval_options' => [10, 15, 30, 60],

    'default_interval' => 10,

    // Most numbers one campaign may have: free plans vs paid plans. A
    // campaign must also fit in the user's remaining monthly messages.
    'max_recipients' => [
        'free' => 50,
        'paid' => 1000,
    ],

    // Pause the campaign after this many failed sends in a row, so a
    // problem (bad numbers, a restricted account) doesn't burn the list.
    'pause_after_failures' => 5,

    // Scheduling: how far ahead a campaign may be scheduled, and the
    // timezone used when the browser doesn't tell us the user's own.
    'max_schedule_days' => 30,

    'default_timezone' => 'Asia/Kolkata',

];
