<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One admin action. Append-only: nothing in the app edits or deletes these
 * rows. Written through App\Services\AdminAudit::record().
 */
#[Fillable([
    'admin_id',
    'admin_name',
    'action',
    'target_type',
    'target_id',
    'target_label',
    'details',
    'ip_address',
])]
class AdminAuditLog extends Model
{
    // Only created_at — an entry is never updated.
    public const UPDATED_AT = null;

    // action => human label, also the order of the page's filter dropdown.
    public const ACTIONS = [
        'user.plan_changed' => 'Changed user plan',
        'user.suspended' => 'Suspended user',
        'user.unsuspended' => 'Unsuspended user',
        'user.deleted' => 'Deleted user',
        'plan.created' => 'Created plan',
        'plan.updated' => 'Updated plan',
        'plan.deleted' => 'Deleted plan',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    /**
     * The details as short readable lines, e.g. "price: 749 → 899".
     *
     * @return array<int, string>
     */
    public function detailLines(): array
    {
        $lines = [];

        foreach ($this->details ?? [] as $key => $value) {
            $lines[] = is_array($value) && array_keys($value) === ['from', 'to']
                ? "{$key}: {$this->display($value['from'])} → {$this->display($value['to'])}"
                : "{$key}: {$this->display($value)}";
        }

        return $lines;
    }

    private function display(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'yes' : 'no',
            $value === null => '—',
            is_array($value) => json_encode($value),
            default => (string) $value,
        };
    }
}
