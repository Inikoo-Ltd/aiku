<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A note staff write on a purchase order or stock delivery, kept in the audits table
 * next to the automatic change history.
 *
 * @property int $id
 * @property string $auditable_type
 * @property int $auditable_id
 * @property int|null $user_id
 * @property array $new_values
 * @property array $data
 * @property string|null $source_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read User|null $user
 */
class ProcurementNote extends Model
{
    protected $table = 'audits';

    protected $casts = [
        'tags'       => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
        'data'       => 'array',
    ];

    protected $attributes = [
        'tags'       => '{}',
        'old_values' => '{}',
        'new_values' => '{}',
        'data'       => '{}',
    ];

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
