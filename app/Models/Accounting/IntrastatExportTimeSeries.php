<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Thu, 13 Feb 2026 16:10:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Accounting;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Helpers\Country;
use App\Models\Helpers\TaxCategory;
use App\Models\SysAdmin\Organisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organisation_id
 * @property string $tariff_code Tariff code (HS code)
 * @property int $country_id
 * @property int|null $tax_category_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Country $country
 * @property-read Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Accounting\IntrastatExportTimeSeriesRecord> $records
 * @property-read TaxCategory|null $taxCategory
 * @method static Builder<static>|IntrastatExportTimeSeries newModelQuery()
 * @method static Builder<static>|IntrastatExportTimeSeries newQuery()
 * @method static Builder<static>|IntrastatExportTimeSeries query()
 * @mixin Eloquent
 */
class IntrastatExportTimeSeries extends Model
{
    protected $table = 'intrastat_export_time_series';

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

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function taxCategory(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(IntrastatExportTimeSeriesRecord::class);
    }
}
