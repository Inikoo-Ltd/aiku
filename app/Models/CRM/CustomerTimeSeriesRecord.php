<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\CRM;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $customer_time_series_id
 * @property string $frequency
 * @property numeric|null $sales
 * @property numeric|null $sales_org_currency
 * @property numeric|null $sales_grp_currency
 * @property numeric|null $lost_revenue
 * @property numeric|null $lost_revenue_org_currency
 * @property numeric|null $lost_revenue_grp_currency
 * @property numeric|null $baskets_created
 * @property numeric|null $baskets_created_org_currency
 * @property numeric|null $baskets_created_grp_currency
 * @property numeric|null $baskets_updated
 * @property numeric|null $baskets_updated_org_currency
 * @property numeric|null $baskets_updated_grp_currency
 * @property int|null $invoices
 * @property int|null $refunds
 * @property int|null $orders
 * @property int|null $delivery_notes
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property string|null $period
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class CustomerTimeSeriesRecord extends Model
{
    protected $table = 'customer_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from' => 'datetime',
            'to'   => 'datetime',
        ];
    }
}
