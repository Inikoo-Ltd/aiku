<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Dec 2024 13:55:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $master_product_category_id
 * @property string|null $last_order_created_at
 * @property string|null $last_order_submitted_at
 * @property string|null $last_order_dispatched_at
 * @property int $number_orders
 * @property int $number_orders_state_creating
 * @property int $number_orders_state_submitted
 * @property int $number_orders_state_in_warehouse
 * @property int $number_orders_state_handling
 * @property int $number_orders_state_handling_blocked
 * @property int $number_orders_state_packed
 * @property int $number_orders_state_finalised
 * @property int $number_orders_state_dispatched
 * @property int $number_orders_state_cancelled
 * @property int $number_orders_status_creating
 * @property int $number_orders_status_processing
 * @property int $number_orders_status_settled
 * @property int $number_orders_handing_type_collection
 * @property int $number_orders_handing_type_shipping
 * @property int $number_transactions_out_of_stock_in_basket transactions at the time up submission from basket
 * @property string|null $out_of_stock_in_basket_grp_net_amount
 * @property int $number_transactions transactions including cancelled
 * @property int $number_current_transactions transactions excluding cancelled
 * @property int $number_transactions_state_creating
 * @property int $number_transactions_state_submitted
 * @property int $number_transactions_state_in_warehouse
 * @property int $number_transactions_state_handling
 * @property int $number_transactions_state_packed
 * @property int $number_transactions_state_finalised
 * @property int $number_transactions_state_dispatched
 * @property int $number_transactions_state_cancelled
 * @property int $number_transactions_status_creating
 * @property int $number_transactions_status_processing
 * @property int $number_transactions_status_settled
 * @property int $number_invoices
 * @property int $number_invoices_type_invoice
 * @property int $number_invoices_type_refund
 * @property string|null $last_invoiced_at
 * @property int $number_invoice_transactions transactions including cancelled
 * @property int $number_positive_invoice_transactions amount>0
 * @property int $number_negative_invoice_transactions amount<0
 * @property int $number_zero_invoice_transactions amount=0
 * @property int $number_current_invoice_transactions transactions excluding cancelled
 * @property int $number_positive_current_invoice_transactions transactions excluding cancelled, amount>0
 * @property int $number_negative_current_invoice_transactions transactions excluding cancelled, amount<0
 * @property int $number_zero_current_invoice_transactions transactions excluding cancelled, amount=0
 * @property int $number_invoiced_customers
 * @property string|null $last_delivery_note_created_at
 * @property string|null $last_delivery_note_dispatched_at
 * @property string|null $last_delivery_note_type_order_created_at
 * @property string|null $last_delivery_note_type_order_dispatched_at
 * @property string|null $last_delivery_note_type_replacement_created_at
 * @property string|null $last_delivery_note_type_replacement_dispatched_at
 * @property int $number_delivery_notes
 * @property int $number_delivery_notes_type_order
 * @property int $number_delivery_notes_type_replacement
 * @property int $number_delivery_notes_state_unassigned
 * @property int $number_delivery_notes_state_queued
 * @property int $number_delivery_notes_state_handling
 * @property int $number_delivery_notes_state_handling_blocked
 * @property int $number_delivery_notes_state_packed
 * @property int $number_delivery_notes_state_finalised
 * @property int $number_delivery_notes_state_dispatched
 * @property int $number_delivery_notes_state_cancelled
 * @property int $number_delivery_notes_cancelled_at_state_unassigned
 * @property int $number_delivery_notes_cancelled_at_state_queued
 * @property int $number_delivery_notes_cancelled_at_state_handling
 * @property int $number_delivery_notes_cancelled_at_state_handling_blocked
 * @property int $number_delivery_notes_cancelled_at_state_packed
 * @property int $number_delivery_notes_cancelled_at_state_finalised
 * @property int $number_delivery_notes_cancelled_at_state_dispatched
 * @property int $number_delivery_notes_state_with_out_of_stock
 * @property int $number_delivery_note_items transactions including cancelled
 * @property int $number_uphold_delivery_note_items transactions excluding cancelled
 * @property int $number_delivery_note_items_state_unassigned
 * @property int $number_delivery_note_items_state_queued
 * @property int $number_delivery_note_items_state_handling
 * @property int $number_delivery_note_items_state_handling_blocked
 * @property int $number_delivery_note_items_state_packed
 * @property int $number_delivery_note_items_state_finalised
 * @property int $number_delivery_note_items_state_dispatched
 * @property int $number_delivery_note_items_state_cancelled
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategoryOrderingStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategoryOrderingStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterProductCategoryOrderingStats query()
 * @mixin \Eloquent
 */
class MasterProductCategoryOrderingStats extends Model
{
    protected $table = 'master_product_category_ordering_stats';

    protected $guarded = [];
}
