<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Goods;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $trade_unit_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Goods\TradeUnit $tradeUnit
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Goods\TradeUnitTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitTimeSeries query()
 * @mixin \Eloquent
 */
class TradeUnitTimeSeries extends Model
{
    protected $table = 'trade_unit_time_series';

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

    public function tradeUnit(): BelongsTo
    {
        return $this->belongsTo(TradeUnit::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(TradeUnitTimeSeriesRecord::class);
    }
}
