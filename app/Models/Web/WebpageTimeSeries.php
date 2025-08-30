<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 15:44:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $webpage_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Web\WebpageTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebpageTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebpageTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebpageTimeSeries query()
 * @mixin \Eloquent
 */
class WebpageTimeSeries extends Model
{
    protected $table = 'webpage_time_series';

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
        return $this->hasMany(WebpageTimeSeriesRecord::class);
    }

}
