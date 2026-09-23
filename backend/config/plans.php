<?php

/**
 * Billing plans. Plain config, not a database table — changing a price or
 * limit is a code change/deploy, not something that needs to be edited
 * live. Every user has a `subscriptions` row whose `plan` column is one
 * of these keys (see App\Models\Subscription, App\Services\PlanLimiter).
 *
 * 'price' is in INR (Cashfree's default currency for this account).
 * 'instances' / 'messages_per_month' are the limits PlanLimiter enforces.
 * `null` price marks the one plan nobody pays for or subscribes to via
 * Cashfree — every user starts here.
 */
return [

    'free' => [
        'name' => 'Free',
        'description' => 'Try the full API before you commit to anything.',
        'price' => 0,
        'instances' => 1,
        'messages_per_month' => 50,
    ],

    'starter' => [
        'name' => 'Starter',
        'description' => 'For a single WhatsApp number handling regular traffic.',
        'price' => 749,
        'instances' => 1,
        'messages_per_month' => 1000,
        'cashfree_plan_id' => 'starter_monthly',
    ],

    'growth' => [
        'name' => 'Growth',
        'description' => 'For teams running multiple numbers and steady campaigns.',
        'price' => 1499,
        'instances' => 3,
        'messages_per_month' => 5000,
        'cashfree_plan_id' => 'growth_monthly',
        'popular' => true,
    ],

    'business' => [
        'name' => 'Business',
        'description' => 'For high-volume senders across many numbers.',
        'price' => 2999,
        'instances' => 10,
        'messages_per_month' => 50000,
        'cashfree_plan_id' => 'business_monthly',
    ],

];
