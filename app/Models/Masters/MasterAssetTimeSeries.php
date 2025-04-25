<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Dec 2024 12:49:05 Malaysia Time, Kuala Lumpur, Malaysia
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
 * @property int $master_asset_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Masters\MasterAssetTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAssetTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAssetTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAssetTimeSeries query()
 * @mixin \Eloquent
 */
class MasterAssetTimeSeries extends Model
{
    protected $table = 'master_asset_time_series';

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
        return $this->hasMany(MasterAssetTimeSeriesRecord::class);
    }
}
