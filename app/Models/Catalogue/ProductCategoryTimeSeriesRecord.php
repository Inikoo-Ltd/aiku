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
 * @property int $product_category_time_series_id
 * @property string $sales
 * @property string $sales_org_currency
 * @property string $sales_grp_currency
 * @property int $invoices
 * @property int $refunds
 * @property int $orders
 * @property int $delivery_notes
 * @property int $customers_invoiced
 * @property string|null $from
 * @property string|null $to
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



}
