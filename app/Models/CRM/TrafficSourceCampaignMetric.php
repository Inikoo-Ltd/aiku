<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\CRM;

use App\Models\Helpers\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\CRM\TrafficSourceCampaignMetric
 *
 * @property int $id
 * @property int $traffic_source_campaign_id
 * @property \Illuminate\Support\Carbon $date
 * @property int $impressions
 * @property int $clicks
 * @property string $conversions
 * @property string $source_cost
 * @property string $source_conversions_value
 * @property string $all_conversions
 * @property string $source_all_conversions_value
 * @property string|null $search_impression_share
 * @property string|null $search_rank_lost_impression_share
 * @property string|null $search_budget_lost_impression_share
 * @property string|null $search_top_impression_share
 * @property string|null $search_rank_lost_top_impression_share
 * @property string|null $search_budget_lost_top_impression_share
 * @property string|null $search_absolute_top_impression_share
 * @property string|null $search_rank_lost_absolute_top_impression_share
 * @property string|null $search_budget_lost_absolute_top_impression_share
 * @property int|null $source_currency_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CRM\TrafficSourceCampaign $trafficSourceCampaign
 * @property-read \App\Models\Helpers\Currency|null $sourceCurrency
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceCampaignMetric newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceCampaignMetric newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceCampaignMetric query()
 * @mixin \Eloquent
 */
class TrafficSourceCampaignMetric extends Model
{
    use HasFactory;

    /**
     * Google's daily search impression share ratios, between 0 and 1, null for campaign types that do
     * not run on Google Search. Never averaged across days: a period figure is weighted by eligible
     * impressions, which is impressions divided by search_impression_share.
     */
    public const array IMPRESSION_SHARE_COLUMNS = [
        'search_impression_share',
        'search_rank_lost_impression_share',
        'search_budget_lost_impression_share',
        'search_top_impression_share',
        'search_rank_lost_top_impression_share',
        'search_budget_lost_top_impression_share',
        'search_absolute_top_impression_share',
        'search_rank_lost_absolute_top_impression_share',
        'search_budget_lost_absolute_top_impression_share',
    ];

    protected $table = 'traffic_source_campaign_metrics';

    protected $guarded = [];

    protected function casts(): array
    {
        $casts = [
            'date'                         => 'date',
            'impressions'                  => 'integer',
            'clicks'                       => 'integer',
            'conversions'                  => 'decimal:2',
            'source_cost'                  => 'decimal:2',
            'source_conversions_value'     => 'decimal:2',
            'all_conversions'              => 'decimal:2',
            'source_all_conversions_value' => 'decimal:2',
        ];

        foreach (self::IMPRESSION_SHARE_COLUMNS as $column) {
            $casts[$column] = 'decimal:4';
        }

        return $casts;
    }

    public function trafficSourceCampaign(): BelongsTo
    {
        return $this->belongsTo(TrafficSourceCampaign::class);
    }

    public function sourceCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'source_currency_id');
    }
}
