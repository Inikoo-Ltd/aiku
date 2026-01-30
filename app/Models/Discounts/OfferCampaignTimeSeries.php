<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Discounts;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $offer_campaign_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Discounts\OfferCampaign $offerCampaign
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Discounts\OfferCampaignTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferCampaignTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferCampaignTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferCampaignTimeSeries query()
 * @mixin \Eloquent
 */
class OfferCampaignTimeSeries extends Model
{
    protected $table = 'offer_campaign_time_series';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data'      => 'array',
            'frequency' => TimeSeriesFrequencyEnum::class,
        ];
    }

    protected function attributes(): array
    {
        return [
            'data' => [],
        ];
    }

    public function offerCampaign(): BelongsTo
    {
        return $this->belongsTo(OfferCampaign::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(OfferCampaignTimeSeriesRecord::class);
    }
}
