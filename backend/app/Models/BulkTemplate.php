<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved message (Bulk messages → Saved messages) a user can load into a
 * new campaign. Text only, may contain {name}.
 */
#[Fillable(['user_id', 'name', 'body'])]
class BulkTemplate extends Model
{
    // Most saved messages one user may keep.
    public const MAX_PER_USER = 50;

    protected function casts(): array
    {
        return [
            'body' => 'encrypted', // privacy: see CLAUDE.md §17
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
