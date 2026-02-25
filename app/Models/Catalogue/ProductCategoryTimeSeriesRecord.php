<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 14:40:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $type
 * @property string $frequency
 * @property string $period
 * @property int $product_category_time_series_id
 * @property string|null $sales_external
 * @property string|null $sales_org_currency_external
 * @property string|null $sales_grp_currency_external
 * @property string|null $sales_internal
 * @property string|null $sales_org_currency_internal
 * @property string|null $sales_grp_currency_internal
 * @property int|null $invoices
 * @property int|null $refunds
 * @property int|null $orders
 * @property int|null $customers_invoiced
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCategoryTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCategoryTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCategoryTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class ProductCategoryTimeSeriesRecord extends Model
{
    protected $table = 'product_category_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from' => 'datetime',
            'to' => 'datetime',
        ];
    }
}
