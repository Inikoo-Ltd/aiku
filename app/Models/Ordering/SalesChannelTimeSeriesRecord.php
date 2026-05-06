<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Ordering;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $sales_channel_time_series_id
 * @property string $frequency
 * @property numeric|null $sales_external
 * @property numeric|null $sales_org_currency_external
 * @property numeric|null $sales_grp_currency_external
 * @property numeric|null $lost_revenue
 * @property numeric|null $lost_revenue_org_currency
 * @property numeric|null $lost_revenue_grp_currency
 * @property int|null $invoices
 * @property int|null $refunds
 * @property int|null $customers_invoiced
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property string|null $period
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property numeric|null $sales_internal
 * @property numeric|null $sales_org_currency_internal
 * @property numeric|null $sales_grp_currency_internal
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesChannelTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesChannelTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesChannelTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class SalesChannelTimeSeriesRecord extends Model
{
    protected $table = 'sales_channel_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from' => 'datetime',
            'to'   => 'datetime',
        ];
    }
}
