<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Dec 2024 14:01:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $type
 * @property string $frequency
 * @property string $period
 * @property int $master_product_category_time_series_id
 * @property string|null $sales
 * @property string|null $sales_org_currency
 * @property string|null $sales_grp_currency
 * @property int|null $invoices
 * @property int|null $refunds
 * @property int|null $orders
 * @property int|null $customers_invoiced
 * @property string|null $from
 * @property string|null $to
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategoryTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategoryTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategoryTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class MasterProductCategoryTimeSeriesRecord extends Model
{
    protected $table = 'master_product_category_time_series_records';

    protected $guarded = [];



}
