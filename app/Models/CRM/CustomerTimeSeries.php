<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\CRM;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $customer_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CRM\CustomerTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerTimeSeries query()
 * @mixin \Eloquent
 */
class CustomerTimeSeries extends Model
{
    protected $table = 'customer_time_series';

    protected $guarded = [];

    protected $casts = [
        'data'      => 'array',
        'frequency' => TimeSeriesFrequencyEnum::class,
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(CustomerTimeSeriesRecord::class);
    }
}
