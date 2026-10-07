<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shop_id
 * @property string $keyword
 * @property string $country_code
 * @property string $language_code
 * @property int|null $avg_monthly_searches
 * @property array<array-key, mixed>|null $monthly_searches
 * @property string|null $competition
 * @property int|null $competition_index
 * @property int|null $low_top_of_page_bid_micros
 * @property int|null $high_top_of_page_bid_micros
 * @property string $source
 * @property \Illuminate\Support\Carbon $fetched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoKeyword newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoKeyword newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoKeyword query()
 * @mixin \Eloquent
 */
class SeoKeyword extends Model
{
    public const string SOURCE_KEYWORD_PLANNER = 'google_ads_keyword_planner';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'monthly_searches' => 'array',
            'fetched_at'       => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
