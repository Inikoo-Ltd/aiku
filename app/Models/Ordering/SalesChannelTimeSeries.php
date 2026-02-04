<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Ordering;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $sales_channel_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Ordering\SalesChannel $salesChannel
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ordering\SalesChannelTimeSeries> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesChannelTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesChannelTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesChannelTimeSeries query()
 * @mixin \Eloquent
 */
class SalesChannelTimeSeries extends Model
{
    protected $table = 'sales_channel_time_series';

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

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(SalesChannelTimeSeriesRecord::class);
    }
}
