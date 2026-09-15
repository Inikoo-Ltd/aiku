<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 28 Jul 2026 09:12:47 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exports\Ordering;

use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Ordering\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * One row per ordered product, with the order level columns repeated on every
 * row of the same order, matching the column layout dropshipping customers get
 * from their sales channel platform.
 */
class CustomerSalesChannelOrdersExport extends DefaultValueBinder implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithStrictNullComparison, WithCustomValueBinder
{
    public function __construct(protected CustomerSalesChannel $customerSalesChannel)
    {
    }

    /**
     * Phone numbers and postal codes would otherwise be read as numbers or
     * formulas, losing the leading "+" and any leading zero.
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function query(): Builder
    {
        $orderQuantity = DB::table('transactions as order_items')
            ->whereColumn('order_items.order_id', 'orders.id')
            ->where('order_items.model_type', 'Product')
            ->whereNull('order_items.deleted_at')
            ->selectRaw('coalesce(sum(order_items.quantity_ordered), 0)');

        $paymentMethods = DB::table('model_has_payments')
            ->join('payments', 'payments.id', '=', 'model_has_payments.payment_id')
            ->leftJoin('payment_accounts', 'payment_accounts.id', '=', 'payments.payment_account_id')
            ->where('model_has_payments.model_type', 'Order')
            ->whereColumn('model_has_payments.model_id', 'orders.id')
            ->where('payments.type', 'payment')
            ->whereNull('payments.deleted_at')
            ->selectRaw("string_agg(distinct payment_accounts.name, ' + ')");

        $refundedAmount = DB::table('model_has_payments')
            ->join('payments', 'payments.id', '=', 'model_has_payments.payment_id')
            ->where('model_has_payments.model_type', 'Order')
            ->whereColumn('model_has_payments.model_id', 'orders.id')
            ->where('payments.type', 'refund')
            ->whereNull('payments.deleted_at')
            ->selectRaw('coalesce(sum(payments.amount), 0)');

        /* delivery_note_order carries no index at all, so tracking is aggregated once
           for the whole sales channel and joined, never correlated per row. */
        $trackingNumbers = DB::table('delivery_note_order')
            ->join('orders as tracked_orders', function ($join) {
                $join->on('tracked_orders.id', '=', 'delivery_note_order.order_id')
                    ->where('tracked_orders.customer_sales_channel_id', '=', $this->customerSalesChannel->id);
            })
            ->join('model_has_shipments', function ($join) {
                $join->on('model_has_shipments.model_id', '=', 'delivery_note_order.delivery_note_id')
                    ->where('model_has_shipments.model_type', '=', 'DeliveryNote');
            })
            ->join('shipments', 'shipments.id', '=', 'model_has_shipments.shipment_id')
            ->whereNull('shipments.deleted_at')
            ->groupBy('delivery_note_order.order_id')
            ->select('delivery_note_order.order_id')
            ->selectRaw("string_agg(distinct shipments.tracking, ', ') as tracking");

        return Transaction::query()
            ->join('orders', 'orders.id', '=', 'transactions.order_id')
            ->where('orders.customer_sales_channel_id', $this->customerSalesChannel->id)
            ->whereNotIn('orders.state', [OrderStateEnum::CREATING])
            ->where('transactions.model_type', 'Product')
            ->leftJoin('assets', 'assets.id', '=', 'transactions.asset_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'orders.currency_id')
            ->leftJoin('shipping_zones', 'shipping_zones.id', '=', 'orders.shipping_zone_id')
            ->leftJoin('customer_clients', 'customer_clients.id', '=', 'orders.customer_client_id')
            ->leftJoin('customers', 'customers.id', '=', 'orders.customer_id')
            ->leftJoin('addresses as delivery_addresses', 'delivery_addresses.id', '=', 'orders.delivery_address_id')
            ->leftJoin('countries as delivery_countries', 'delivery_countries.id', '=', 'delivery_addresses.country_id')
            ->leftJoin('addresses as billing_addresses', 'billing_addresses.id', '=', 'orders.billing_address_id')
            ->leftJoin('countries as billing_countries', 'billing_countries.id', '=', 'billing_addresses.country_id')
            ->leftJoinSub($trackingNumbers, 'order_tracking', 'order_tracking.order_id', '=', 'orders.id')
            ->select([
                'transactions.id',
                'transactions.quantity_ordered',
                'transactions.gross_amount',
                'transactions.estimated_weight',
                'assets.code as sku',
                'assets.name as item_name',

                'orders.reference as order_reference',
                'orders.date as order_date',
                'orders.state as order_state',
                'orders.pay_status',
                'orders.handing_type',
                'orders.customer_notes',
                'orders.shipping_amount',
                'orders.tax_amount',
                'orders.total_amount',
                'orders.net_amount',

                'currencies.code as currency_code',
                'shipping_zones.name as shipping_zone_name',

                'customer_clients.name as recipient_name',
                'customer_clients.company_name as recipient_company_name',
                'customer_clients.email as recipient_email',
                'customer_clients.phone as recipient_phone',
                'orders.email as order_email',

                'customers.name as billing_name',
                'customers.company_name as billing_company_name',
                'customers.phone as billing_phone',

                'delivery_addresses.address_line_1 as delivery_address_line_1',
                'delivery_addresses.locality as delivery_locality',
                'delivery_addresses.administrative_area as delivery_administrative_area',
                'delivery_addresses.postal_code as delivery_postal_code',
                'delivery_countries.iso3 as delivery_country_code',

                'billing_addresses.address_line_1 as billing_address_line_1',
                'billing_addresses.locality as billing_locality',
                'billing_addresses.administrative_area as billing_administrative_area',
                'billing_addresses.postal_code as billing_postal_code',
                'billing_countries.iso3 as billing_country_code',

                'order_tracking.tracking as tracking_number',
            ])
            ->selectSub($orderQuantity, 'order_quantity')
            ->selectSub($paymentMethods, 'payment_methods')
            ->selectSub($refundedAmount, 'refunded_amount')
            ->orderByDesc('orders.date')
            ->orderBy('transactions.id');
    }

    public function headings(): array
    {
        return [
            'Order number',
            'Date created',
            'Time',
            'Total order quantity',
            'Contact email',
            'Note from customer',
            'Additional checkout info',
            'Item',
            'Variant',
            'SKU',
            'Qty',
            'Quantity refunded',
            'Price',
            'Weight',
            'Custom text',
            'Deposit amount',
            'Delivery method',
            'Delivery time',
            'Recipient name',
            'Recipient phone',
            'Recipient company name',
            'Delivery country',
            'Delivery state',
            'Delivery city',
            'Delivery address',
            'Delivery zip/postal code',
            'Billing name',
            'Billing phone',
            'Billing company name',
            'Billing country',
            'Billing state',
            'Billing city',
            'Billing address',
            'Billing zip/postal code',
            'Payment status',
            'Payment method',
            'Gift card amount',
            'Shipping rate',
            'Total tax',
            'Total',
            'Currency',
            'Refunded amount',
            'Net amount',
            'Fulfillment status',
            'Tracking number',
            'Fulfillment service',
            'Shipping label',
        ];
    }

    /**
     * @param  Transaction  $row
     */
    public function map($row): array
    {
        $date = $row->order_date ? Carbon::parse($row->order_date) : null;
        $quantity = (float)$row->quantity_ordered;
        $isShipped = $row->handing_type === OrderHandingTypeEnum::SHIPPING->value;

        return [
            (string)$row->order_reference,
            $date?->format('M j, Y'),
            $date?->format('g:i:s A'),
            $this->quantity($row->order_quantity),
            (string)($row->recipient_email ?: $row->order_email),
            (string)$row->customer_notes,
            '',
            (string)$row->item_name,
            '',
            (string)$row->sku,
            $this->quantity($quantity),
            0,
            $quantity > 0 ? round((float)$row->gross_amount / $quantity, 2) : 0,
            $quantity > 0 ? round((float)$row->estimated_weight / $quantity / 1000, 3) : 0,
            '',
            '',
            $this->deliveryMethod($row, $isShipped),
            '',
            $isShipped ? (string)$row->recipient_name : '',
            $isShipped ? (string)$row->recipient_phone : '',
            $isShipped ? (string)$row->recipient_company_name : '',
            (string)$row->delivery_country_code,
            '',
            (string)$row->delivery_locality,
            (string)$row->delivery_address_line_1,
            (string)$row->delivery_postal_code,
            (string)$row->billing_name,
            (string)$row->billing_phone,
            (string)$row->billing_company_name,
            (string)$row->billing_country_code,
            '',
            (string)$row->billing_locality,
            (string)$row->billing_address_line_1,
            (string)$row->billing_postal_code,
            OrderPayStatusEnum::labels()[$row->pay_status] ?? '',
            (string)$row->payment_methods,
            '',
            (float)$row->shipping_amount,
            (float)$row->tax_amount,
            (float)$row->total_amount,
            (string)$row->currency_code,
            (float)$row->refunded_amount,
            (float)$row->net_amount,
            $row->order_state === OrderStateEnum::DISPATCHED->value ? 'Fulfilled' : 'Unfulfilled',
            (string)$row->tracking_number,
            '',
            $this->shippingLabel($row),
        ];
    }

    private function quantity(float|string|null $quantity): int|float
    {
        $quantity = (float)$quantity;

        return $quantity === floor($quantity) ? (int)$quantity : $quantity;
    }

    private function deliveryMethod(Transaction $row, bool $isShipped): string
    {
        if (!$isShipped) {
            return '';
        }

        if ((float)$row->shipping_amount === 0.0) {
            return 'Free shipping';
        }

        return (string)$row->shipping_zone_name;
    }

    private function shippingLabel(Transaction $row): string
    {
        if (!$row->delivery_address_line_1 && !$row->delivery_locality) {
            return '';
        }

        return implode(' / ', [
            (string)$row->recipient_name,
            $row->delivery_address_line_1.',',
            (string)$row->delivery_locality,
            trim($row->delivery_administrative_area.' '.$row->delivery_postal_code),
            (string)$row->delivery_country_code,
            (string)$row->recipient_phone,
        ]);
    }
}
