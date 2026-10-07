<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\PreOrder\PreOrderTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The basket lines, or the part of a line, that go beyond the stock of a product offered for
 * pre-order. Stock is not reserved by baskets, so this is read fresh every time it is shown and
 * again when the order is submitted.
 */
class GetBasketPreOrders
{
    use AsObject;

    /**
     * @return array{deferred_amount: float, pay_now_amount: float, signature: string, is_accepted: bool, hold_together: bool, lines: array<int, array<string, mixed>>, has_pre_orders: bool, has_in_stock_lines: bool, has_made_to_order: bool, has_pallet_delivery: bool, terms: array<int, string>, texts: array<string, string>}
     */
    public function handle(Order $order): array
    {
        $productLines = $order->transactions()
            ->where('model_type', 'Product')
            ->where('is_gift', false)
            ->where('quantity_ordered', '>', 0)
            ->with('model.shop')
            ->get()
            ->filter(fn (Transaction $transaction) => $transaction->model instanceof Product);

        $preOrderByProduct = GetProductPreOrder::make()->byProduct($productLines->pluck('model')->unique('id')->values());

        $lines = [];
        foreach ($productLines as $transaction) {
            $preOrder = $preOrderByProduct[$transaction->model_id] ?? null;
            if (!$preOrder) {
                continue;
            }

            $quantityOrdered  = (float) $transaction->quantity_ordered;
            $inStockQuantity  = min($quantityOrdered, max(0, (float) $transaction->model->available_quantity));
            $preOrderQuantity = $quantityOrdered - $inStockQuantity;
            if ($preOrderQuantity <= 0) {
                continue;
            }

            $lines[$transaction->id] = array_merge($preOrder, [
                'transaction_id'     => $transaction->id,
                'product_id'         => $transaction->model_id,
                'code'               => $transaction->model->code,
                'name'               => $transaction->model->name,
                'quantity_ordered'   => $quantityOrdered,
                'in_stock_quantity'  => $inStockQuantity,
                'pre_order_quantity' => $preOrderQuantity,
                'pre_order_net_amount' => round((float) $transaction->net_amount * $preOrderQuantity / $quantityOrdered, 2),
                'basket_label'       => $this->basketLabel($order, $preOrder, $inStockQuantity, $preOrderQuantity),
            ]);
        }

        $collection = collect($lines);
        $types      = $collection->pluck('type')->unique()->map(fn (string $type) => PreOrderTypeEnum::from($type))->values()->all();
        $terms      = $collection->isEmpty() ? [] : GetProductPreOrder::make()->terms($order->shop, $types, $collection->contains('is_pallet_delivery', true));
        $signature  = $this->signature($order, $lines);

        $deferredAmount  = $this->deferredAmount($order, $collection->all());
        $hasInStockLines = $productLines->count() > $collection->count() || $collection->contains(fn ($line) => $line['in_stock_quantity'] > 0);

        return [
            'deferred_amount'     => $deferredAmount,
            'made_to_order_deposit_amount' => $this->madeToOrderDepositAmount($order, $collection->all()),
            'pay_now_amount'      => round(max(0, (float) $order->total_amount - $deferredAmount), 2),
            'signature'           => $signature,
            'is_accepted'         => $collection->isNotEmpty() && Arr::get($order->data, 'pre_order.accepted_signature') === $signature,
            'hold_together'       => (bool) Arr::get($order->data, 'pre_order.hold_together', false),
            'lines'               => $lines,
            'has_pre_orders'      => $collection->isNotEmpty(),
            'has_in_stock_lines'  => $hasInStockLines,
            'has_made_to_order'   => $collection->contains('type', 'made_to_order'),
            'has_pallet_delivery' => $collection->contains('is_pallet_delivery', true),
            'pallet_estimate_label' => $collection->contains('is_pallet_delivery', true)
                ? GetProductPreOrder::make()->palletEstimateLabel($order->shop, $order->deliveryAddress?->country_code)
                : null,
            'terms'               => $terms,
            'texts'               => $collection->isEmpty() ? [] : $this->texts($order, $hasInStockLines),
        ];
    }

    /**
     * @param  array<string, mixed>  $preOrder
     */
    private function basketLabel(Order $order, array $preOrder, float $inStockQuantity, float $preOrderQuantity): string
    {
        $values = [
            'in_stock'  => trimDecimalZeros($inStockQuantity),
            'pre_order' => trimDecimalZeros($preOrderQuantity),
            'weeks'     => GetPreOrderText::make()->weeks($preOrder['dispatch_from_weeks'], $preOrder['dispatch_to_weeks']),
        ];

        $label = GetPreOrderText::make()->handle($order->shop, $inStockQuantity > 0 ? 'basket_line_partial' : 'basket_line', $values);

        return $label.' · '.$preOrder['payment_label'];
    }

    /**
     * @return array<string, string>
     */
    private function texts(Order $order, bool $hasInStockLines): array
    {
        $texts = GetPreOrderText::make();
        $shop  = $order->shop;

        return [
            'title'          => $texts->handle($shop, 'label'),
            'dispatch'       => $texts->handle($shop, $hasInStockLines ? 'checkout_mixed' : 'checkout_only_pre_order'),
            'hold_together'  => $texts->handle($shop, 'checkout_hold_together'),
            'only_pre_order' => $texts->handle($shop, 'checkout_only_pre_order'),
            'accept'         => $texts->handle($shop, 'checkout_accept'),
            'terms_title'    => $texts->handle($shop, 'terms_link'),
        ];
    }

    /**
     * The part of the order not charged at checkout: what is left after the deposit on trade
     * made-to-order items, tax included. Orders worth less than the shop's threshold are paid in
     * full. Back-orders and dropshipping have a 100% deposit, so nothing is deferred for them.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function deferredAmount(Order $order, array $lines): float
    {
        if ((float) $order->goods_amount < (float) $order->shop->preOrderSetting('full_payment_below')) {
            return 0;
        }

        $deferredNet = 0;
        foreach ($lines as $line) {
            $deferredNet += $line['pre_order_net_amount'] * (100 - $line['deposit_percentage']) / 100;
        }

        return round(min((float) $order->total_amount, $deferredNet * $this->taxFactor($order)), 2);
    }

    /**
     * The deposit on trade made-to-order items, tax included: what is not refunded when the
     * customer cancels after we ordered from the supplier, or does not pay the balance.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function madeToOrderDepositAmount(Order $order, array $lines): float
    {
        $depositNet = 0;
        foreach ($lines as $line) {
            if ($line['type'] == PreOrderTypeEnum::MADE_TO_ORDER->value && $line['deposit_percentage'] < 100) {
                $depositNet += $line['pre_order_net_amount'] * $line['deposit_percentage'] / 100;
            }
        }

        return round($depositNet * $this->taxFactor($order), 2);
    }

    private function taxFactor(Order $order): float
    {
        return (float) $order->net_amount > 0 ? (float) $order->total_amount / (float) $order->net_amount : 1;
    }

    /**
     * What the customer accepted: which lines, how many beyond stock, and the shop's terms. Any
     * change to them after accepting asks the customer to accept again. The terms enter as the
     * shop settings they are written from, not as text, so switching language keeps the acceptance.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function signature(Order $order, array $lines): string
    {
        ksort($lines);

        return hash('sha256', json_encode([
            array_map(fn ($line) => [$line['transaction_id'], $line['pre_order_quantity'], $line['type'], $line['is_pallet_delivery']], array_values($lines)),
            Arr::get($order->shop->settings, 'pre_orders', []),
        ]));
    }

    /**
     * Every way a customer places a basket from Retina asks for this first: pre-order lines go only
     * with the terms accepted.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureTermsAccepted(Order $order): void
    {
        if ($order->state != OrderStateEnum::CREATING || $order->preOrder || !$order->shop->hasPreOrders()) {
            return;
        }

        $basketPreOrders = $this->handle($order);
        if ($basketPreOrders['has_pre_orders'] && !$basketPreOrders['is_accepted']) {
            throw ValidationException::withMessages([
                'pre_order' => __('Please accept the pre-order terms before placing your order.'),
            ]);
        }
    }
}
