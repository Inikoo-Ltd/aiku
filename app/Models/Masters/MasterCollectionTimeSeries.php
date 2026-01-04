<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 03:02:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $master_collection_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Masters\MasterCollection $masterCollection
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Masters\MasterCollectionTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollectionTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollectionTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollectionTimeSeries query()
 * @mixin \Eloquent
 */
class MasterCollectionTimeSeries extends Model
{
    protected $table = 'master_collection_time_series';

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

    public function masterCollection(): BelongsTo
    {
        return $this->belongsTo(MasterCollection::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(MasterCollectionTimeSeriesRecord::class);
    }
}
