<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Mar 2026 18:54:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property \Illuminate\Support\Carbon $date
 * @property numeric $org_stock_value FIFO method
 * @property numeric $grp_stock_value FIFO method
 * @property numeric $org_stock_commercial_value
 * @property numeric $grp_stock_commercial_value
 * @property int $number_org_stocks
 * @property int $number_out_of_stock_org_stocks
 * @property int $number_location_org_stocks
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $is_week
 * @property bool $is_month
 * @property bool $is_year
 * @property int $number_locations
 * @property float $percentage_out_of_stock
 * @property numeric $value_dormant_stock_1y
 * @property int $number_org_stocks_not_sold_1y
 * @property float $percentage_value_dormant_stock_1y
 * @property int|null $group_stock_history_id
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory\LocationOrgStockHistory> $locationOrgStockHistories
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory\OrgStockHistory> $orgStockHistories
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


    public function orgStockHistories(): HasMany
    {
        return $this->hasMany(OrgStockHistory::class);
    }

    public function locationOrgStockHistories(): HasMany
    {
        return $this->hasMany(LocationOrgStockHistory::class);
    }


}
