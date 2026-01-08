<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Models\Catalogue;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $collection_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Catalogue\Collection $collection
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Catalogue\CollectionTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CollectionTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CollectionTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CollectionTimeSeries query()
 * @mixin \Eloquent
 */
class CollectionTimeSeries extends Model
{
    protected $table = 'collection_time_series';

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

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(CollectionTimeSeriesRecord::class);
    }
}
