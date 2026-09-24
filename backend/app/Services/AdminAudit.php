<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Records one admin action in the audit log. Called by the admin
 * controllers right AFTER an action succeeds — blocked attempts (e.g.
 * "can't suspend another admin") change nothing, so they aren't logged.
 *
 * Never pass passwords, tokens or message content in $details.
 */
class AdminAudit
{
    public static function record(Request $request, string $action, User|Plan $target, array $details = []): void
    {
        $admin = $request->user();

        AdminAuditLog::create([
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'action' => $action,
            'target_type' => $target instanceof User ? 'user' : 'plan',
            'target_id' => $target->id,
            'target_label' => $target instanceof User ? "{$target->name} ({$target->email})" : $target->name,
            'details' => $details ?: null,
            'ip_address' => $request->ip(),
        ]);
    }
}
