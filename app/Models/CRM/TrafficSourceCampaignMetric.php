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

    protected $table = 'traffic_source_campaign_metrics';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'                     => 'date',
            'impressions'              => 'integer',
            'clicks'                   => 'integer',
            'conversions'              => 'decimal:2',
            'source_cost'              => 'decimal:2',
            'source_conversions_value' => 'decimal:2',
        ];
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
