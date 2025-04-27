<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Dec 2024 14:00:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 *
 *
 * @property int $id
 * @property int $master_product_category_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Masters\MasterProductCategoryTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategoryTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategoryTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategoryTimeSeries query()
 * @mixin \Eloquent
 */
class MasterProductCategoryTimeSeries extends Model
{
    protected $table = 'master_product_category_time_series';

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
        return $this->hasMany(MasterProductCategoryTimeSeriesRecord::class);
    }

}
