<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What a customer asking "where is my order" can be told, read from the order, its delivery
 * notes and their shipments: the same things their own order page shows. The order they named
 * if it is theirs, otherwise their latest, and only ever this customer's own orders.
 */
class GetChatOrderFacts
{
    use AsAction;

    /** What each state means to somebody waiting for the parcel, for the model to explain. */
    public const array STATE_MEANING = [
        'submitted'        => 'received, waiting to be picked in the warehouse',
        'in_warehouse'     => 'in the warehouse, waiting to be picked',
        'handling'         => 'being picked in the warehouse',
        'handling_blocked' => 'held up in the warehouse, staff are looking at it',
        'picked'           => 'picked, waiting to be packed',
        'packing'          => 'being packed',
        'packed'           => 'packed, waiting for the courier',
        'finalised'        => 'ready, waiting for the courier to collect',
        'dispatched'       => 'dispatched, with the courier',
        'cancelled'        => 'cancelled',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function handle(Customer $customer, string $text): ?array
    {
        $named = GetChatClaimDetails::orderReference($customer->shop, $text);

        $placed = $customer->orders()
            ->whereNot('state', OrderStateEnum::CREATING)
            ->latest('date')
            ->limit(3)
            ->get();

        $order = $named
            ? $customer->orders()->where('reference', $named)->first()
            : $placed->first(fn (Order $order) => $order->state !== OrderStateEnum::CANCELLED) ?? $placed->first();

        if (!$order) {
            return null;
        }

        return [
            'customer_name' => $customer->contact_name ?: $customer->name,
            'order'         => $this->order($order),
            'order_named_by_customer' => (bool) $named,
            'other_recent_orders' => $placed->reject(fn (Order $recent) => $recent->id === $order->id)
                ->map(fn (Order $recent) => [
                    'reference' => $recent->reference,
                    'placed'    => $recent->submitted_at?->toDateString() ?? $recent->date?->toDateString(),
                    'status'    => self::STATE_MEANING[$recent->state->value] ?? $recent->state->value,
                ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function order(Order $order): array
    {
        return array_filter([
            'reference'     => $order->reference,
            'placed'        => $order->submitted_at?->toDateString() ?? $order->date?->toDateString(),
            'status'        => self::STATE_MEANING[$order->state->value] ?? $order->state->value,
            'dispatched_on' => $order->dispatched_at?->toDateString(),
            'cancelled_on'  => $order->cancelled_at?->toDateString(),
            'parcels'       => $order->deliveryNotes()->with('shipments.shipper')->get()
                ->flatMap(fn (DeliveryNote $deliveryNote) => $deliveryNote->shipments->map(fn (Shipment $shipment) => $this->shipment($shipment)))
                ->values()
                ->all(),
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    private function shipment(Shipment $shipment): array
    {
        $tracking = collect($shipment->trackings ?? [])
            ->map(fn ($number, $key) => array_filter([
                'number' => $number,
                'link'   => Arr::get($shipment->tracking_urls ?? [], $key),
            ]))
            ->values()
            ->all();

        return array_filter([
            'courier'  => $shipment->shipper?->trade_as ?: $shipment->shipper?->name,
            'shipped'  => $shipment->shipped_at ? Carbon::parse($shipment->shipped_at)->toDateString() : null,
            'tracking' => $tracking ?: ($shipment->tracking ? [['number' => $shipment->tracking]] : null),
        ]);
    }
}
