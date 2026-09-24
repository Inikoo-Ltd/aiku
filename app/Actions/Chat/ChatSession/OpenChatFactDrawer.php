<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Catalogue\Product\GetProductIncomingStock;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The extra facts a draft may ask for. The model picks drawers by name from MENU and never
 * sees how they are filled: each one is a fixed lookup in this shop, and the ones about orders
 * only ever read the customer aiku already knows is writing. A drawer that has nothing to say
 * returns null, and a name that is not on the menu opens nothing.
 */
class OpenChatFactDrawer
{
    use AsAction;

    public const array MENU = [
        'order_lines'      => 'the products in the order: quantities ordered, sent and not sent',
        'replacements'     => 'replacement parcels we sent this customer: their state and tracking',
        'alternatives'     => 'in-stock products from the same family as a product that is out of stock',
        'incoming_stock'   => 'whether more of a product is on a purchase order to us (never a date)',
        'order_payment'    => 'whether the order is paid',
        'recent_orders'    => 'the customer\'s last 10 orders with their state',
    ];

    /**
     * @param  array<string, mixed>  $facts
     * @return array<int|string, mixed>|null
     */
    public function handle(string $drawer, Shop $shop, ?Customer $customer, array $facts): ?array
    {
        $order    = $this->order($customer, $facts);
        $products = $this->products($shop, $facts);

        $contents = match ($drawer) {
            'order_lines'    => $order ? $this->orderLines($order) : null,
            'replacements'   => $customer ? $this->replacements($customer) : null,
            'alternatives'   => $this->alternatives($products),
            'incoming_stock' => $products->mapWithKeys(fn (Product $product) => [
                $product->code => $product->state !== ProductStateEnum::DISCONTINUED && GetProductIncomingStock::make()->earliestEta($product) !== null
                    ? 'yes, more is on a purchase order, no confirmed date'
                    : 'nothing on order',
            ])->all(),
            'order_payment'  => $order ? [$order->reference => $order->pay_status?->value ?? 'unknown'] : null,
            'recent_orders'  => $customer ? $this->recentOrders($customer) : null,
            default          => null,
        };

        return $contents ?: null;
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function order(?Customer $customer, array $facts): ?Order
    {
        $reference = $facts['order_facts']['order']['reference'] ?? null;

        return $customer && $reference ? $customer->orders()->where('reference', $reference)->first() : null;
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function products(Shop $shop, array $facts): \Illuminate\Support\Collection
    {
        $codes = collect($facts['product_facts'] ?? [])->pluck('code')->filter()->all();

        return $codes ? Product::where('shop_id', $shop->id)->whereIn('code', $codes)->get() : collect();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orderLines(Order $order): array
    {
        return $order->transactions()
            ->where('model_type', 'Product')
            ->with('asset')
            ->limit(60)
            ->get()
            ->map(fn (Transaction $transaction) => array_filter([
                'code'     => $transaction->asset?->code,
                'name'     => $transaction->asset?->name,
                'ordered'  => (float) $transaction->quantity_ordered,
                'sent'     => $transaction->quantity_dispatched !== null ? (float) $transaction->quantity_dispatched : null,
                'not_sent' => (float) ($transaction->quantity_cancelled ?? 0) + (float) ($transaction->quantity_fail ?? 0) ?: null,
            ], fn ($value) => $value !== null))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function replacements(Customer $customer): array
    {
        return DeliveryNote::where('customer_id', $customer->id)
            ->where('type', DeliveryNoteTypeEnum::REPLACEMENT)
            ->where('created_at', '>=', now()->subDays(90))
            ->with(['shipments.shipper', 'orders'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (DeliveryNote $deliveryNote) => array_filter([
                'for_order' => $deliveryNote->orders->first()?->reference,
                'created'   => $deliveryNote->created_at?->toDateString(),
                'state'     => $deliveryNote->state?->value,
                'parcels'   => $deliveryNote->shipments->map(fn ($shipment) => array_filter([
                    'courier'  => $shipment->shipper?->trade_as ?: $shipment->shipper?->name,
                    'tracking' => $shipment->tracking ?: implode(' ', (array) ($shipment->trackings ?? [])),
                ]))->values()->all(),
            ]))
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function alternatives(\Illuminate\Support\Collection $products): array
    {
        return $products
            ->filter(fn (Product $product) => $product->family_id && ($product->available_quantity < 1 || $product->state === ProductStateEnum::DISCONTINUED))
            ->mapWithKeys(fn (Product $product) => [
                $product->code => Product::where('family_id', $product->family_id)
                    ->whereKeyNot($product->id)
                    ->where('is_for_sale', true)
                    ->where('status', '!=', ProductStatusEnum::OUT_OF_STOCK)
                    ->where('available_quantity', '>', 0)
                    ->whereNot('state', ProductStateEnum::DISCONTINUED)
                    ->orderByDesc('available_quantity')
                    ->limit(5)
                    ->get()
                    ->map(fn (Product $alternative) => [
                        'code'          => $alternative->code,
                        'name'          => $alternative->name,
                        'available_now' => (int) $alternative->available_quantity,
                    ])->all(),
            ])
            ->filter()
            ->all();
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function recentOrders(Customer $customer): array
    {
        return $customer->orders()
            ->latest('date')
            ->limit(10)
            ->get()
            ->map(fn (Order $order) => [
                'reference' => $order->reference,
                'placed'    => $order->date?->toDateString(),
                'state'     => GetChatOrderFacts::STATE_MEANING[$order->state->value] ?? $order->state->value,
            ])
            ->all();
    }
}
