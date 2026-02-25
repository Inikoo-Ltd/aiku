<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Dec 2024 12:49:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $master_asset_time_series_id
 * @property string $frequency
 * @property string|null $sales_external
 * @property string|null $sales_org_currency_external
 * @property string|null $sales_grp_currency_external
 * @property string|null $sales_internal
 * @property string|null $sales_org_currency_internal
 * @property string|null $sales_grp_currency_internal
 * @property int|null $invoices
 * @property int|null $refunds
 * @property int|null $orders
 * @property int|null $delivery_notes
 * @property int|null $customers_invoiced
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property string|null $period
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAssetTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAssetTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAssetTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class MasterAssetTimeSeriesRecord extends Model
{
    protected $table = 'master_asset_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from' => 'datetime',
            'to' => 'datetime',
        ];
    }
}
