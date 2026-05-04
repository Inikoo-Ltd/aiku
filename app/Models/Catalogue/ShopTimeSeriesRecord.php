<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 03:13:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $shop_time_series_id
 * @property string $frequency
 * @property numeric|null $sales_external
 * @property numeric|null $sales_org_currency_external
 * @property numeric|null $sales_grp_currency_external
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
 * @property int|null $registrations_with_orders
 * @property int|null $registrations_without_orders
 * @property int|null $customers_invoiced
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property string|null $period
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property numeric|null $sales_internal
 * @property numeric|null $sales_org_currency_internal
 * @property numeric|null $sales_grp_currency_internal
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class ShopTimeSeriesRecord extends Model
{
    protected $table = 'shop_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from' => 'datetime',
            'to'   => 'datetime',
        ];
    }
}
