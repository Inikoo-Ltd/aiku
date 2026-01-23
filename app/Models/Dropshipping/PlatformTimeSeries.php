<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Dropshipping;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $platform_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Dropshipping\Platform $platform
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Dropshipping\PlatformTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CollectionTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CollectionTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CollectionTimeSeries query()
 * @mixin \Eloquent
 */
class PlatformTimeSeries extends Model
{
    protected $table = 'platform_time_series';

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

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(PlatformTimeSeriesRecord::class);
    }
}
