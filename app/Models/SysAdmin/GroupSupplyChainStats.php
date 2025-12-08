<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 May 2023 17:09:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\SysAdmin\GroupSupplyChainStats
 *
 * @property int $id
 * @property int $group_id
 * @property int $number_agents Total number agens active+archived
 * @property int $number_active_agents Active agents, status=true
 * @property int $number_archived_agents Archived agents, status=false
 * @property int $number_suppliers Active + Archived  suppliers
 * @property int $number_active_suppliers Active suppliers, status=true
 * @property int $number_archived_suppliers Archived suppliers status=false
 * @property int $number_independent_suppliers Active + Archived no agent suppliers
 * @property int $number_active_independent_suppliers Active no agent suppliers, status=true
 * @property int $number_archived_independent_suppliers Archived no agent suppliers status=false
 * @property int $number_suppliers_in_agents Active + Archived suppliers
 * @property int $number_active_suppliers_in_agents Active suppliers, status=true
 * @property int $number_archived_suppliers_in_agents Archived suppliers status=false
 * @property int $number_supplier_products
 * @property int $number_current_supplier_products state=active|discontinuing
 * @property int $number_available_supplier_products
 * @property int $number_no_available_supplier_products only for state=active|discontinuing
 * @property int $number_supplier_products_state_in_process
 * @property int $number_supplier_products_state_active
 * @property int $number_supplier_products_state_discontinuing
 * @property int $number_supplier_products_state_discontinued
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 *
 * @method static Builder<static>|GroupSupplyChainStats newModelQuery()
 * @method static Builder<static>|GroupSupplyChainStats newQuery()
 * @method static Builder<static>|GroupSupplyChainStats query()
 *
 * @mixin Eloquent
 */
class GroupSupplyChainStats extends Model
{
    protected $table = 'group_supply_chain_stats';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
