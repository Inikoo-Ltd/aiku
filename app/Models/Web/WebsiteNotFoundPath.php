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
 * @property int $website_id
 * @property string $path
 * @property string $path_hash
 * @property string $last_segment
 * @property int $hits
 * @property string|null $last_referrer
 * @property bool $is_ignored
 * @property \Illuminate\Support\Carbon $first_seen_at
 * @property \Illuminate\Support\Carbon $last_seen_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Web\Website $website
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteNotFoundPath newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteNotFoundPath newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteNotFoundPath query()
 * @mixin \Eloquent
 */
class WebsiteNotFoundPath extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_ignored'    => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at'  => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
