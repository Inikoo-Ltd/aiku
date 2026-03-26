<?php

namespace App\Models\Helpers;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $brand_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\BrandTimeSeriesRecord> $records
 * @property-read \App\Models\Helpers\Brand $brand
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BrandTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BrandTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BrandTimeSeries query()
 * @mixin \Eloquent
 */
class BrandTimeSeries extends Model
{
    protected $table = 'brand_time_series';

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

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(BrandTimeSeriesRecord::class);
    }
}
