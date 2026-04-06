<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Apr 2026 18:28:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $group_id
 * @property \Illuminate\Support\Carbon $date
 * @property numeric $grp_stock_value
 * @property numeric $grp_stock_commercial_value
 * @property string $grp_value_dormant_stock_1y
 * @property float $percentage_value_dormant_stock_1y
 * @property int $number_stocks
 * @property int $number_org_stocks_no_stock
 * @property int $number_stocks_org_stocks_no_stock Number of stocks plus org stocks with no stock relationship
 * @property int $number_org_stocks
 * @property int $number_out_of_stock_org_stocks
 * @property int $number_location_org_stocks
 * @property int $number_locations
 * @property bool $is_week
 * @property bool $is_month
 * @property bool $is_year
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStockHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStockHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStockHistory query()
 * @mixin \Eloquent
 */
class GroupStockHistory extends Model
{
    use inGroup;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'                       => 'date',
            'grp_stock_value'            => 'decimal:2',
            'grp_stock_commercial_value' => 'decimal:2',
        ];
    }


}
