<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:20:42 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\CRM\CustomerStats
 *
 * @property int $id
 * @property int $customer_id
 * @property string $sales_all
 * @property string $sales_org_currency_all
 * @property string $sales_grp_currency_all
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
 * @property int $number_item_transactions_out_of_stock_in_basket transactions at the time up submission from basket
 * @property string|null $out_of_stock_in_basket_grp_net_amount
 * @property string|null $out_of_stock_in_basket_org_net_amount
 * @property string $out_of_stock_in_basket_net_amount
 * @property int $number_item_transactions transactions including cancelled
 * @property int $number_current_item_transactions transactions excluding cancelled
 * @property int $number_item_transactions_state_creating
 * @property int $number_item_transactions_state_submitted
 * @property int $number_item_transactions_state_in_warehouse
 * @property int $number_item_transactions_state_handling
 * @property int $number_item_transactions_state_packed
 * @property int $number_item_transactions_state_finalised
 * @property int $number_item_transactions_state_dispatched
 * @property int $number_item_transactions_state_cancelled
 * @property int $number_item_transactions_status_creating
 * @property int $number_item_transactions_status_processing
 * @property int $number_item_transactions_status_settled
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
 * @property int $number_web_users
 * @property int $number_current_web_users Number of web users with state = true
 * @property int $number_web_users_type_web
 * @property int $number_web_users_type_api
 * @property int $number_web_users_auth_type_default
 * @property int $number_web_users_auth_type_aurora
 * @property int $number_customer_clients
 * @property int $number_current_customer_clients
 * @property int $number_portfolios
 * @property int $number_current_portfolios
 * @property int $number_credit_transactions
 * @property int $number_top_ups
 * @property int $number_top_ups_status_in_process
 * @property int $number_top_ups_status_success
 * @property int $number_top_ups_status_fail
 * @property int $number_favourites
 * @property int $number_unfavourited
 * @property int $number_reminders
 * @property int $number_reminders_cancelled
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_unpaid_invoices
 * @property string $unpaid_invoices_amount
 * @property string $unpaid_invoices_amount_org_currency
 * @property string $unpaid_invoices_amount_grp_currency
 * @property int $number_deleted_invoices
 * @property int $number_platforms
 * @property int $number_customer_sales_channels
 * @property int $number_customer_sales_channels_platform_type_shopify
 * @property int $number_customer_sales_channels_platform_type_tiktok
 * @property int $number_customer_sales_channels_platform_type_woocommerce
 * @property int $number_customer_sales_channels_platform_type_ebay
 * @property int $number_customer_sales_channels_platform_type_manual
 * @property int $number_customer_sales_channels_platform_type_amazon
 * @property int $number_customer_sales_channels_platform_type_magento
 * @property int $number_customer_sales_channels_platform_type_wix
 * @property-read \App\Models\CRM\Customer $customer
 * @method static Builder<static>|CustomerStats newModelQuery()
 * @method static Builder<static>|CustomerStats newQuery()
 * @method static Builder<static>|CustomerStats query()
 * @mixin Eloquent
 */
class CustomerStats extends Model
{
    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
