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
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @property string|null $pending_task_id
 * @property \Illuminate\Support\Carbon|null $pending_task_posted_at
 * @property \Illuminate\Support\Carbon|null $last_checked_at
 * @property int|null $position
 * @property int|null $previous_position
 * @property \Illuminate\Support\Carbon|null $previous_checked_at
 * @property string|null $ranking_url
 * @property int|null $ranking_webpage_id
 * @property array<array-key, mixed>|null $serp_features
 * @property bool $in_ai_overview
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Shop $shop
 * @property-read \App\Models\Web\Webpage|null $targetWebpage
 * @property-read \App\Models\Web\Webpage|null $rankingWebpage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SeoKeywordRanking> $rankings
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
            'device'                 => SeoKeywordDeviceEnum::class,
            'frequency'              => SeoKeywordFrequencyEnum::class,
            'is_active'              => 'boolean',
            'pending_task_posted_at' => 'datetime',
            'last_checked_at'        => 'datetime',
            'previous_checked_at'    => 'datetime',
            'serp_features'          => 'array',
            'in_ai_overview'         => 'boolean',
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

    public function rankingWebpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class, 'ranking_webpage_id');
    }

    public function rankings(): HasMany
    {
        return $this->hasMany(SeoKeywordRanking::class, 'tracked_keyword_id');
    }
}
