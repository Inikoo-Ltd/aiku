<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 03:12:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $group_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SysAdmin\GroupTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupTimeSeries query()
 * @mixin \Eloquent
 */
class GroupTimeSeries extends Model
{
    protected $table = 'group_time_series';

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
        return $this->hasMany(GroupTimeSeriesRecord::class);
    }

}
