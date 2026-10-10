<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;

/**
 * A domain's organic Google footprint in one market, from DataForSEO Labs. Traffic is DataForSEO's
 * estimate from rankings and volumes, not a measurement.
 *
 * @property int $id
 * @property string $domain
 * @property string $country_code
 * @property string $language_code
 * @property \Illuminate\Support\Carbon $date
 * @property int $organic_keywords
 * @property string $estimated_traffic
 * @property int $top_3
 * @property int $top_10
 * @property int $stored_keywords
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoDomainOverview newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoDomainOverview newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoDomainOverview query()
 * @mixin \Eloquent
 */
class SeoDomainOverview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
