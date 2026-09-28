<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateOrderDeliveryAddress;
use App\Actions\Ordering\Order\UpdateOrderShippingEngineAsManual;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\PreOrder;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * At submit, the pre-order part of a basket becomes an order of its own, held until its goods
 * arrive (HELP-3432). In-stock items go now at normal shipping; the pre-order items are sent
 * separately when they arrive and pay their own delivery. A customer who asked to hold
 * everything, or whose basket is all pre-order, keeps one order and all of it waits.
 *
 * Lines move with the price they were sold at: discounts are not recalculated, shipping and
 * charges are, because each order is now its own delivery.
 */
class SplitOrderPreOrders
{
    use AsObject;

    private const array SCALED_AMOUNTS = ['gross_amount', 'net_amount', 'grp_net_amount', 'org_net_amount', 'estimated_weight', 'commission_amount', 'profit_amount'];

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): ?PreOrder
    {
        if ($order->preOrder || !$order->shop->hasPreOrders()) {
            return null;
        }

        $basketPreOrders = GetBasketPreOrders::run($order);
        if (!$basketPreOrders['has_pre_orders']) {
            return null;
        }

        return DB::transaction(function () use ($order, $basketPreOrders) {
            if ($basketPreOrders['hold_together'] || !$basketPreOrders['has_in_stock_lines']) {
                foreach ($basketPreOrders['lines'] as $line) {
                    $this->tagLine(Transaction::find($line['transaction_id']), $line);
                }

                return $this->storePreOrder($order, null, $basketPreOrders);
            }

            $preOrderOrder = $this->storePreOrderOrder($order);

            foreach ($basketPreOrders['lines'] as $line) {
                $this->moveLine(Transaction::find($line['transaction_id']), $preOrderOrder, $line);
            }

            CalculateOrderTotalAmounts::run($order, calculateDiscounts: false);
            CalculateOrderTotalAmounts::run($preOrderOrder->refresh(), calculateDiscounts: false);

            return $this->storePreOrder($preOrderOrder, $order, $basketPreOrders);
        });
    }

    /**
     * @throws \Throwable
     */
    private function storePreOrderOrder(Order $order): Order
    {
        $preOrderOrder = StoreOrder::make()->action($order->customerClient ?? $order->customer, array_filter([
            'sales_channel_id' => $order->sales_channel_id,
            'tax_category_id'  => $order->tax_category_id,
        ]));

        $preOrderOrder->update(array_filter([
            'customer_reference'   => $order->customer_reference,
            'shipping_notes'       => $order->shipping_notes,
            'to_be_paid_by'        => $order->to_be_paid_by,
            'collection_address_id' => $order->collection_address_id,
            'handing_type'         => $order->handing_type,
        ], fn ($value) => $value !== null));

        if (!$order->collection_address_id && $order->deliveryAddress
            && $order->deliveryAddress->checksum !== $preOrderOrder->deliveryAddress?->checksum) {
            UpdateOrderDeliveryAddress::make()->action($preOrderOrder, [
                'address' => Arr::only($order->deliveryAddress->toArray(), [
                    'address_line_1',
                    'address_line_2',
                    'sorting_code',
                    'postal_code',
                    'locality',
                    'dependent_locality',
                    'administrative_area',
                    'country_code',
                    'country_id',
                ]),
            ]);
        }

        return $preOrderOrder->refresh();
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function moveLine(Transaction $transaction, Order $preOrderOrder, array $line): void
    {
        if ($line['in_stock_quantity'] <= 0) {
            $transaction->update(['order_id' => $preOrderOrder->id]);
            DB::table('transaction_has_offer_allowances')->where('transaction_id', $transaction->id)->update(['order_id' => $preOrderOrder->id]);
            $this->tagLine($transaction, $line);

            return;
        }

        $ratio        = $line['pre_order_quantity'] / $line['quantity_ordered'];
        $preOrderLine = $transaction->replicate();

        $preOrderLine->order_id         = $preOrderOrder->id;
        $preOrderLine->quantity_ordered = $line['pre_order_quantity'];
        $inStockAmounts                 = ['quantity_ordered' => $line['in_stock_quantity']];
        foreach (self::SCALED_AMOUNTS as $column) {
            if ($transaction->{$column} === null) {
                continue;
            }
            $preOrderLine->{$column}  = round((float) $transaction->{$column} * $ratio, $column == 'estimated_weight' ? 0 : 2);
            $inStockAmounts[$column]  = round((float) $transaction->{$column} - (float) $preOrderLine->{$column}, $column == 'estimated_weight' ? 0 : 2);
        }
        $preOrderLine->save();
        $transaction->update($inStockAmounts);

        $this->tagLine($preOrderLine, $line);
    }

    /**
     * What each line was sold as, so emails, invoices and reports show it after the product changes.
     *
     * @param  array<string, mixed>  $line
     */
    private function tagLine(Transaction $transaction, array $line): void
    {
        $transaction->update([
            'data' => array_merge($transaction->data ?? [], [
                'pre_order' => Arr::only($line, [
                    'type',
                    'lead_time_days',
                    'dispatch_from_weeks',
                    'dispatch_to_weeks',
                    'deposit_percentage',
                    'is_pallet_delivery',
                    'pre_order_quantity',
                ]),
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $basketPreOrders
     */
    private function storePreOrder(Order $preOrderOrder, ?Order $parentOrder, array $basketPreOrders): PreOrder
    {
        $lines          = collect($basketPreOrders['lines']);
        $shop           = $preOrderOrder->shop;
        $isTrade        = $shop->type === ShopTypeEnum::B2B;
        $hasMadeToOrder = $basketPreOrders['has_made_to_order'];

        $palletEstimate = $basketPreOrders['has_pallet_delivery']
            ? GetProductPreOrder::make()->palletEstimate($shop, $preOrderOrder->deliveryAddress?->country_code)
            : null;

        /** A pallet goes on its own delivery charge: the estimate now, the real quote when the goods arrive */
        if ($palletEstimate !== null) {
            UpdateOrderShippingEngineAsManual::run($preOrderOrder, ['shipping_amount' => $palletEstimate]);
        }

        return PreOrder::create([
            'pallet_estimate_amount'  => $palletEstimate,
            'group_id'                => $preOrderOrder->group_id,
            'organisation_id'         => $preOrderOrder->organisation_id,
            'shop_id'                 => $preOrderOrder->shop_id,
            'customer_id'             => $preOrderOrder->customer_id,
            'order_id'                => $preOrderOrder->id,
            'parent_order_id'         => $parentOrder?->id,
            'state'                   => PreOrderStateEnum::WAITING_FOR_GOODS,
            'is_trade'                => $isTrade,
            'has_back_order'          => $lines->contains('type', 'back_order'),
            'has_made_to_order'       => $hasMadeToOrder,
            'has_pallet_delivery'     => $basketPreOrders['has_pallet_delivery'],
            'deferred_amount'         => $basketPreOrders['deferred_amount'],
            'estimated_dispatch_from' => now()->addWeeks((int) $lines->max('dispatch_from_weeks'))->toDateString(),
            'estimated_dispatch_to'   => now()->addWeeks((int) $lines->max('dispatch_to_weeks'))->toDateString(),
            'free_cancellation_until' => $isTrade && $hasMadeToOrder
                ? now()->addWeekdays((int) $shop->preOrderSetting('free_cancellation_working_days'))->endOfDay()
                : null,
            'terms'                   => Arr::get($preOrderOrder->data, 'pre_order.accepted_terms')
                ?? Arr::get($parentOrder?->data, 'pre_order.accepted_terms')
                ?? $basketPreOrders['terms'],
            'data'                    => [
                'accepted_at'                  => Arr::get($parentOrder?->data ?? $preOrderOrder->data, 'pre_order.accepted_at'),
                'made_to_order_deposit_amount' => $basketPreOrders['made_to_order_deposit_amount'],
            ],
        ]);
    }
}
