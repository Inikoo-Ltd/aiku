<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 13:24:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $org_stock_family_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory\OrgStockFamilyTimeSeriesRecord> $records
 * @property-read \App\Models\Inventory\OrgStockFamily $orgStockFamily
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockFamilyTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockFamilyTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockFamilyTimeSeries query()
 * @mixin \Eloquent
 */
class OrgStockFamilyTimeSeries extends Model
{
    protected $table = 'org_stock_family_time_series';

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

    public function orgStockFamily(): BelongsTo
    {
        return $this->belongsTo(OrgStockFamily::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(OrgStockFamilyTimeSeriesRecord::class);
    }
}
