<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 00:54:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $outbox_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comms\OutboxTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxTimeSeries query()
 * @mixin \Eloquent
 */
class OutboxTimeSeries extends Model
{
    protected $table = 'outbox_time_series';

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
        return $this->hasMany(OutboxTimeSeriesRecord::class);
    }
}
