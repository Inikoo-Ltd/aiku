<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Mar 2026 18:54:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $org_stock_value FIFO method
 * @property numeric $grp_stock_value FIFO method
 * @property string $org_stock_commercial_value
 * @property numeric $grp_stock_commercial_value
 * @property int $number_org_stocks
 * @property int $number_out_of_stock_org_stocks
 * @property int $number_location_org_stocks
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationStockHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationStockHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationStockHistory query()
 * @mixin \Eloquent
 */
class OrganisationStockHistory extends Model
{
    use inOrganisation;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'                           => 'date',
            'stock_value'                    => 'decimal:2',
            'grp_stock_value'                => 'decimal:2',
            'stock_commercial_value'         => 'decimal:2',
            'grp_stock_commercial_value'     => 'decimal:2',
            'number_org_stocks'              => 'integer',
            'number_out_of_stock_org_stocks' => 'integer',
        ];
    }


}
