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
 * @property string|null $sales
 * @property string|null $sales_org_currency
 * @property string|null $sales_grp_currency
 * @property string|null $lost_revenue
 * @property string|null $lost_revenue_org_currency
 * @property string|null $lost_revenue_grp_currency
 * @property int|null $invoices
 * @property int|null $refunds
 * @property int|null $customers_invoiced
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property string|null $period
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
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
