<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 29 Aug 2026 18:00:00 Central European Summer Time, Bratislava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Dropshipping;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $platform_time_series_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $sales_channel_id
 * @property string $frequency
 * @property numeric|null $sales_external
 * @property numeric|null $sales_org_currency_external
 * @property numeric|null $sales_grp_currency_external
 * @property int|null $invoices
 * @property \Illuminate\Support\Carbon|null $from
 * @property \Illuminate\Support\Carbon|null $to
 * @property string $period
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformSalesChannelTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformSalesChannelTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformSalesChannelTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class PlatformSalesChannelTimeSeriesRecord extends Model
{
    protected $table = 'platform_sales_channel_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sales_external' => 'decimal:2',
            'sales_org_currency_external' => 'decimal:2',
            'sales_grp_currency_external' => 'decimal:2',
            'from' => 'datetime',
            'to'   => 'datetime',
        ];
    }
}
