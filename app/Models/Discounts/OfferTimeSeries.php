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
 * @property int $offer_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Discounts\Offer $offer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Discounts\OfferTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferTimeSeries query()
 * @mixin \Eloquent
 */
class OfferTimeSeries extends Model
{
    protected $table = 'offer_time_series';

    protected $guarded = [];

    protected $casts = [
        'data'      => 'array',
        'frequency' => TimeSeriesFrequencyEnum::class,
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(OfferTimeSeriesRecord::class);
    }
}
