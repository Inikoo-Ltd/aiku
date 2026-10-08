<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use App\Enums\Web\Seo\SeoKeywordDeviceEnum;
use App\Enums\Web\Seo\SeoKeywordFrequencyEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shop_id
 * @property string $keyword
 * @property string $country_code
 * @property string $language_code
 * @property SeoKeywordDeviceEnum $device
 * @property SeoKeywordFrequencyEnum $frequency
 * @property int|null $target_webpage_id
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Shop $shop
 * @property-read \App\Models\Web\Webpage|null $targetWebpage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoTrackedKeyword newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoTrackedKeyword newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoTrackedKeyword query()
 * @mixin \Eloquent
 */
class SeoTrackedKeyword extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'device'    => SeoKeywordDeviceEnum::class,
            'frequency' => SeoKeywordFrequencyEnum::class,
            'is_active' => 'boolean',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function targetWebpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class, 'target_webpage_id');
    }
}
