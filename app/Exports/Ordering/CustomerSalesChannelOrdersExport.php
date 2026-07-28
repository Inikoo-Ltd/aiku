<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 28 Jul 2026 09:12:47 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exports\Ordering;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Ordering\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class CustomerSalesChannelOrdersExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithStrictNullComparison
{
    public function __construct(protected CustomerSalesChannel $customerSalesChannel)
    {
    }

    public function query(): Builder
    {
        $charges = DB::table('transactions')
            ->leftJoin('charges', 'charges.id', '=', 'transactions.model_id')
            ->whereColumn('transactions.order_id', 'orders.id')
            ->where('transactions.model_type', 'Charge')
            ->whereNull('transactions.deleted_at')
            ->selectRaw("string_agg(coalesce(charges.name, charges.label, 'Charge') || ' (' || round(transactions.net_amount, 2)::text || ')', ', ' order by charges.name)");

        return Order::query()
            ->where('orders.customer_sales_channel_id', $this->customerSalesChannel->id)
            ->whereNotIn('orders.state', [OrderStateEnum::CREATING])
            ->leftJoin('customer_clients', 'customer_clients.id', '=', 'orders.customer_client_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'orders.currency_id')
            ->leftJoin('order_stats', 'order_stats.order_id', '=', 'orders.id')
            ->select([
                'orders.id',
                'orders.reference',
                'orders.customer_reference',
                'orders.platform_order_id',
                'orders.date',
                'orders.state',
                'orders.goods_amount',
                'orders.charges_amount',
                'orders.shipping_amount',
                'orders.insurance_amount',
                'orders.net_amount',
                'orders.tax_amount',
                'orders.total_amount',
                'orders.payment_amount',
                'order_stats.number_item_transactions',
                'customer_clients.name as client_name',
                'currencies.code as currency_code',
            ])
            ->selectSub($charges, 'charges_detail')
            ->orderByDesc('orders.date');
    }

    public function headings(): array
    {
        return [
            __('Reference'),
            __('Your reference'),
            __('Platform order'),
            __('Client'),
            __('Date'),
            __('Status'),
            __('Items'),
            __('Currency'),
            __('Goods'),
            __('Charges'),
            __('Charges detail'),
            __('Shipping'),
            __('Insurance'),
            __('Net'),
            __('Tax'),
            __('Total'),
            __('Paid'),
        ];
    }

    /**
     * @param  Order  $row
     */
    public function map($row): array
    {
        return [
            (string)$row->reference,
            (string)$row->customer_reference,
            (string)$row->platform_order_id,
            (string)$row->client_name,
            $row->date?->format('Y-m-d H:i'),
            $row->state?->labels()[$row->state->value] ?? null,
            (int)$row->number_item_transactions,
            (string)$row->currency_code,
            (float)$row->goods_amount,
            (float)$row->charges_amount,
            (string)$row->charges_detail,
            (float)$row->shipping_amount,
            (float)$row->insurance_amount,
            (float)$row->net_amount,
            (float)$row->tax_amount,
            (float)$row->total_amount,
            (float)$row->payment_amount,
        ];
    }
}
