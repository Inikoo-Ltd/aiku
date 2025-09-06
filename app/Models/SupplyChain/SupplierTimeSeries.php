<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 15:44:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $supplier_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupplyChain\SupplierTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierTimeSeries query()
 * @mixin \Eloquent
 */
class SupplierTimeSeries extends Model
{
    protected $table = 'supplier_time_series';

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
        return $this->hasMany(SupplierTimeSeriesRecord::class);
    }

}
