<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Sept 2024 12:40:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Procurement\HistoricSupplierProductStats
 *
 * @property int $id
 * @property int $historic_supplier_product_id
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
 * @method static Builder<static>|HistoricSupplierProductStats newModelQuery()
 * @method static Builder<static>|HistoricSupplierProductStats newQuery()
 * @method static Builder<static>|HistoricSupplierProductStats query()
 * @mixin Eloquent
 */
class HistoricSupplierProductStats extends Model
{
    protected $table = 'historic_supplier_product_stats';

    protected $guarded = [];

    /*
    public function historicProduct(): BelongsTo
    {
        return $this->belongsTo(HistoricAsset::class);
    }
    */
}
