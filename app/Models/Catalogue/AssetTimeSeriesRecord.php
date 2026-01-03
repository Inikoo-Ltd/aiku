<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 14:22:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $asset_time_series_id
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
 * @property string|null $type
 * @property string|null $frequency
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class AssetTimeSeriesRecord extends Model
{
    protected $table = 'asset_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from' => 'datetime',
            'to' => 'datetime',
        ];
    }
}
