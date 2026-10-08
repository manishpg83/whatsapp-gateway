<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\EmailTemplate;
use App\Models\Plan;
use App\Models\Setting;
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
    public static function record(Request $request, string $action, User|Plan|EmailTemplate|Setting $target, array $details = []): void
    {
        $admin = $request->user();

        [$targetType, $targetLabel] = match (true) {
            $target instanceof User => ['user', "{$target->name} ({$target->email})"],
            $target instanceof Plan => ['plan', $target->name],
            $target instanceof EmailTemplate => ['email_template', EmailTemplates::definition($target->key)['label']],
            $target instanceof Setting => ['setting', ucfirst($target->key).' settings'],
        };

        AdminAuditLog::create([
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $target->id,
            'target_label' => $targetLabel,
            'details' => $details ?: null,
            'ip_address' => $request->ip(),
        ]);
    }
}
