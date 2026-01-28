<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Accounting;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $invoice_category_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Accounting\InvoiceCategory $invoiceCategory
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Accounting\InvoiceCategoryTimeSeriesRecord> $records
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceCategoryTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceCategoryTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceCategoryTimeSeries query()
 * @mixin \Eloquent
 */
class InvoiceCategoryTimeSeries extends Model
{
    protected $table = 'invoice_category_time_series';

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

    public function invoiceCategory(): BelongsTo
    {
        return $this->belongsTo(InvoiceCategory::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(InvoiceCategoryTimeSeriesRecord::class);
    }
}
