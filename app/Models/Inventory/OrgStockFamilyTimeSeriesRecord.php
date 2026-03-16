<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 13:24:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $org_stock_family_time_series_id
 * @property string $frequency
 * @property string|null $sales_external
 * @property string|null $sales_org_currency_external
 * @property string|null $sales_grp_currency_external
 * @property string|null $sales_internal
 * @property string|null $sales_org_currency_internal
 * @property string|null $sales_grp_currency_internal
 * @property string|null $lost_revenue
 * @property string|null $lost_revenue_org_currency
 * @property string|null $lost_revenue_grp_currency
 * @property int|null $invoices
 * @property int|null $refunds
 * @property int|null $orders
 * @property int|null $customers_invoiced
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property string|null $period
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockFamilyTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockFamilyTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockFamilyTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class OrgStockFamilyTimeSeriesRecord extends Model
{
    protected $table = 'org_stock_family_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from' => 'datetime',
            'to'   => 'datetime',
        ];
    }
}
