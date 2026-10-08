<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $provider
 * @property string $endpoint
 * @property int|null $website_id
 * @property bool $is_success
 * @property int $rows
 * @property int $duration_ms
 * @property string|null $cost
 * @property string|null $error
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\Web\Website|null $website
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoApiRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoApiRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoApiRequest query()
 * @mixin \Eloquent
 */
class SeoApiRequest extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_success' => 'boolean',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
