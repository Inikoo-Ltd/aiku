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
 * @property string $sales
 * @property string $sales_org_currency
 * @property string $sales_grp_currency
 * @property int $invoices
 * @property int $refunds
 * @property int $orders
 * @property int $delivery_notes
 * @property int $customers_invoiced
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $frequency
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
