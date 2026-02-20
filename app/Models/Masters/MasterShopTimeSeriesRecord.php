<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 12:12:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $master_shop_time_series_id
 * @property string $frequency
 * @property string|null $sales_grp_currency_external
 * @property string|null $sales_grp_currency_internal
 * @property string|null $lost_revenue_grp_currency
 * @property string|null $baskets_created_grp_currency
 * @property string|null $baskets_updated_grp_currency
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShopTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShopTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShopTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class MasterShopTimeSeriesRecord extends Model
{
    protected $table = 'master_shop_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from' => 'datetime',
            'to'   => 'datetime',
        ];
    }
}
