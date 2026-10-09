<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;

/**
 * A domain linking to `domain`. `first_seen` is when DataForSEO first found it; `first_fetched_at`
 * and `lost_at` are our own weekly runs, which is what new and lost since the previous run mean.
 *
 * @property int $id
 * @property string $domain
 * @property string $referring_domain
 * @property int|null $rank
 * @property int $backlinks
 * @property bool $is_own_website
 * @property \Illuminate\Support\Carbon|null $first_seen
 * @property \Illuminate\Support\Carbon $first_fetched_at
 * @property \Illuminate\Support\Carbon $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $lost_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoReferringDomain newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoReferringDomain newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoReferringDomain query()
 * @mixin \Eloquent
 */
class SeoReferringDomain extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_own_website'   => 'boolean',
            'first_seen'       => 'datetime',
            'first_fetched_at' => 'datetime',
            'last_fetched_at'  => 'datetime',
            'lost_at'          => 'datetime',
        ];
    }
}
