<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 19:31:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\SupplyChain\AgentStats
 *
 * @property int $id
 * @property int $agent_id
 * @property int $number_suppliers Active + Archived  suppliers
 * @property int $number_active_suppliers Active suppliers, status=true
 * @property int $number_archived_suppliers Archived suppliers status=false
 * @property int $number_supplier_products
 * @property int $number_current_supplier_products state=active|discontinuing
 * @property int $number_available_supplier_products
 * @property int $number_no_available_supplier_products only for state=active|discontinuing
 * @property int $number_supplier_products_state_in_process
 * @property int $number_supplier_products_state_active
 * @property int $number_supplier_products_state_discontinuing
 * @property int $number_supplier_products_state_discontinued
 * @property int $number_purchase_orders
 * @property int $number_current_purchase_orders Number purchase orders (except: cancelled and not_received)
 * @property int $number_open_purchase_orders Number purchase orders (except: in_process,settled,cancelled,not_received)
 * @property int $number_purchase_orders_state_in_process
 * @property int $number_purchase_orders_state_submitted
 * @property int $number_purchase_orders_state_confirmed
 * @property int $number_purchase_orders_state_settled
 * @property int $number_purchase_orders_state_cancelled
 * @property int $number_purchase_orders_state_not_received
 * @property int $number_purchase_orders_delivery_state_in_process
 * @property int $number_purchase_orders_delivery_state_confirmed
 * @property int $number_purchase_orders_delivery_state_ready_to_ship
 * @property int $number_purchase_orders_delivery_state_dispatched
 * @property int $number_purchase_orders_delivery_state_received
 * @property int $number_purchase_orders_delivery_state_checked
 * @property int $number_purchase_orders_delivery_state_placed
 * @property int $number_purchase_orders_delivery_state_cancelled
 * @property int $number_purchase_orders_delivery_state_not_received
 * @property int $number_stock_deliveries Number supplier deliveries
 * @property int $number_current_stock_deliveries Number supplier deliveries (except: cancelled and not_received)
 * @property int $number_is_costed_stock_deliveries is_costed=true
 * @property int $number_is_not_costed_stock_deliveries is_costed=false
 * @property int $number_is_costed_stock_deliveries_state_placed state=placed is_costed=true
 * @property int $number_is_not_costed_stock_deliveries_state_placed state=placed  is_costed=true
 * @property int $number_stock_deliveries_state_in_process
 * @property int $number_stock_deliveries_state_confirmed
 * @property int $number_stock_deliveries_state_ready_to_ship
 * @property int $number_stock_deliveries_state_dispatched
 * @property int $number_stock_deliveries_state_received
 * @property int $number_stock_deliveries_state_checked
 * @property int $number_stock_deliveries_state_placed
 * @property int $number_stock_deliveries_state_cancelled
 * @property int $number_stock_deliveries_state_not_received
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SupplyChain\Agent $agent
 *
 * @method static Builder<static>|AgentStats newModelQuery()
 * @method static Builder<static>|AgentStats newQuery()
 * @method static Builder<static>|AgentStats query()
 *
 * @mixin Eloquent
 */
class AgentStats extends Model
{
    protected $table = 'agent_stats';

    protected $guarded = [];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
