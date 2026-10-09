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
 * @property int|null $webpage_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $page_url
 * @property string $page_url_hash
 * @property int $clicks
 * @property int $impressions
 * @property string $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Web\Webpage|null $webpage
 * @property-read \App\Models\Web\Website $website
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchConsolePageDay newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchConsolePageDay newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SearchConsolePageDay query()
 * @mixin \Eloquent
 */
class SearchConsolePageDay extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function webpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
