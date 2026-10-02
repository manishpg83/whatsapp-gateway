<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin's edited version of one email. Rendering and the built-in
 * defaults live in App\Services\EmailTemplates.
 */
#[Fillable(['key', 'subject', 'body', 'button_text', 'updated_by'])]
class EmailTemplate extends Model
{
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
