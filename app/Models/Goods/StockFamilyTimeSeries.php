<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 12:24:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $stock_family_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Goods\StockFamilyTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockFamilyTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockFamilyTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockFamilyTimeSeries query()
 * @mixin \Eloquent
 */
class StockFamilyTimeSeries extends Model
{
    protected $table = 'stock_family_time_series';

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
        return $this->hasMany(StockFamilyTimeSeriesRecord::class);
    }
}
