<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 12:22:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $stock_family_time_series_id
 * @property string $frequency
 * @property numeric|null $sales_external
 * @property numeric|null $sales_org_currency_external
 * @property numeric|null $sales_grp_currency_external
 * @property numeric|null $sales_internal
 * @property numeric|null $sales_org_currency_internal
 * @property numeric|null $sales_grp_currency_internal
 * @property numeric|null $lost_revenue
 * @property numeric|null $lost_revenue_org_currency
 * @property numeric|null $lost_revenue_grp_currency
 * @property int|null $invoices
 * @property int|null $refunds
 * @property int|null $orders
 * @property int|null $customers_invoiced
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property string|null $period
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockFamilyTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockFamilyTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockFamilyTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class StockFamilyTimeSeriesRecord extends Model
{
    protected $table = 'stock_family_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from' => 'datetime',
            'to'   => 'datetime',
        ];
    }
}
