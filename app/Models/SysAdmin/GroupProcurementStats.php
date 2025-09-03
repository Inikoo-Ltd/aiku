<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Dec 2024 01:44:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $number_org_agents Total number agens active+archived
 * @property int $number_active_org_agents Active agents, status=true
 * @property int $number_archived_org_agents Archived agents, status=false
 * @property int $number_org_suppliers Active + Archived  suppliers
 * @property int $number_active_org_suppliers Active suppliers, status=true
 * @property int $number_archived_org_suppliers Archived suppliers status=false
 * @property int $number_independent_org_suppliers Active + Archived no agent suppliers
 * @property int $number_active_independent_org_suppliers Active no agent suppliers, status=true
 * @property int $number_archived_independent_org_suppliers Archived no agent suppliers status=false
 * @property int $number_org_suppliers_in_agents Active + Archived suppliers
 * @property int $number_active_org_suppliers_in_agents Active suppliers, status=true
 * @property int $number_archived_org_suppliers_in_agents Archived suppliers status=false
 * @property int $number_org_supplier_products
 * @property int $number_current_org_supplier_products status=true
 * @property int $number_available_org_supplier_products
 * @property int $number_no_available_org_supplier_products only for state=active|discontinuing
 * @property int $number_org_supplier_products_state_active
 * @property int $number_org_supplier_products_state_discontinuing
 * @property int $number_org_supplier_products_state_discontinued
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
 * @property int $number_purchase_order_transactions
 * @property int $number_current_purchase_order_transactions Number purchase order transactions (except: cancelled and not_received)
 * @property int $number_open_purchase_order_transactions Number purchase order transactions (except: in_process,settled,cancelled,not_received)
 * @property int $number_purchase_order_transactions_state_in_process
 * @property int $number_purchase_order_transactions_state_submitted
 * @property int $number_purchase_order_transactions_state_confirmed
 * @property int $number_purchase_order_transactions_state_settled
 * @property int $number_purchase_order_transactions_state_cancelled
 * @property int $number_purchase_order_transactions_state_not_received
 * @property int $number_purchase_orders_transactions_delivery_state_in_process
 * @property int $number_purchase_orders_transactions_delivery_state_confirmed
 * @property int $number_purchase_orders_transactions_delivery_state_ready_to_shi
 * @property int $number_purchase_orders_transactions_delivery_state_dispatched
 * @property int $number_purchase_orders_transactions_delivery_state_received
 * @property int $number_purchase_orders_transactions_delivery_state_checked
 * @property int $number_purchase_orders_transactions_delivery_state_settled
 * @property int $number_purchase_orders_transactions_delivery_state_not_received
 * @property int $number_purchase_orders_transactions_delivery_state_cancelled
 * @property int $number_stock_delivery_items Number supplier deliveries
 * @property int $number_stock_delivery_items_except_cancelled Number supplier deliveries
 * @property int $number_stock_delivery_items_state_in_process
 * @property int $number_stock_delivery_items_state_confirmed
 * @property int $number_stock_delivery_items_state_ready_to_ship
 * @property int $number_stock_delivery_items_state_dispatched
 * @property int $number_stock_delivery_items_state_received
 * @property int $number_stock_delivery_items_state_checked
 * @property int $number_stock_delivery_items_state_placed
 * @property int $number_stock_delivery_items_state_cancelled
 * @property int $number_stock_delivery_items_state_not_received
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static Builder<static>|GroupProcurementStats newModelQuery()
 * @method static Builder<static>|GroupProcurementStats newQuery()
 * @method static Builder<static>|GroupProcurementStats query()
 * @mixin Eloquent
 */
class GroupProcurementStats extends Model
{
    protected $table = 'group_procurement_stats';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
