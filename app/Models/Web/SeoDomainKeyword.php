<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;

/**
 * A keyword a domain ranks for in the top 100 of one market, as of its latest fetch. The set of a
 * domain is replaced on every fetch; the history is in `seo_domain_overviews`.
 *
 * @property int $id
 * @property string $domain
 * @property string $country_code
 * @property string $language_code
 * @property string $keyword
 * @property int $position
 * @property string|null $url
 * @property int|null $search_volume
 * @property string|null $estimated_traffic
 * @property int|null $keyword_difficulty
 * @property string|null $intent
 * @property \Illuminate\Support\Carbon $fetched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoDomainKeyword newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoDomainKeyword newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoDomainKeyword query()
 * @mixin \Eloquent
 */
class SeoDomainKeyword extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
        ];
    }
}
