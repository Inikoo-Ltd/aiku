<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\Helpers\Address\GetFormattedAddress;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * An account can carry a default delivery address that never received a parcel: imported accounts kept one that
 * Aurora never used, and one customer's default mixed an old street with an even older postcode (HELP-3127).
 * When an order is about to go to such an address while the last delivery went elsewhere, the customer and staff
 * are shown where that last order went. Addresses are compared by street and postcode together, not by dates:
 * imported rows all carry the import date.
 */
class GetEarlierDeliveryAddressWarning
{
    use AsObject;

    /**
     * @return array{previous_address: string, previous_address_line: string, previous_order_reference: string, current_address_line: string, confirmed: bool, actions: array|null}|null
     */
    public function handle(Order $order, bool $withCustomerActions = false): ?array
    {
        if (!in_array($order->state, [OrderStateEnum::CREATING, OrderStateEnum::SUBMITTED], true)
            || $order->collection_address_id
            || $order->customer_client_id
            || !$order->deliveryAddress?->hasAnyLine()) {
            return null;
        }

        $currentKey     = $this->addressKey($order->deliveryAddress);
        $defaultAddress = $order->customer?->deliveryAddress;

        if (!$defaultAddress || $this->addressKey($defaultAddress) !== $currentKey) {
            return null;
        }

        $lastDelivered   = $this->lastDeliveredOrder($order);
        $previousAddress = $lastDelivered?->deliveryAddress;

        if (!$previousAddress
            || $this->postcode($previousAddress) === ''
            || $this->addressKey($previousAddress) === $currentKey
            || $this->hasBeenDeliveredTo($order, $currentKey)
            || $this->isOwnPremises($this->postcode($previousAddress))) {
            return null;
        }

        $confirmed = Arr::get($order->data, 'delivery_address_confirmed') === $currentKey;

        return [
            'previous_address'         => GetFormattedAddress::run($previousAddress),
            'previous_address_line'    => $this->shortLine($previousAddress),
            'previous_order_reference' => $lastDelivered->reference,
            'current_address_line'     => $this->shortLine($order->deliveryAddress),
            'confirmed'                => $confirmed,
            'actions'                  => $withCustomerActions && !$confirmed && $order->state == OrderStateEnum::CREATING ? [
                'confirm_route'      => [
                    'method'     => 'patch',
                    'name'       => 'retina.models.order.delivery_address_confirm',
                    'parameters' => ['order' => $order->id],
                ],
                'use_previous_route' => [
                    'method'     => 'patch',
                    'name'       => 'retina.models.order.delivery_address_use_previous',
                    'parameters' => ['order' => $order->id],
                ],
            ] : null,
        ];
    }

    public function previousDeliveredAddress(Order $order): ?Address
    {
        return $this->lastDeliveredOrder($order)?->deliveryAddress;
    }

    public function addressKey(?Address $address): string
    {
        return $this->postcode($address).'|'.preg_replace('/[^a-z0-9]/', '', strtolower((string)$address?->address_line_1));
    }

    private function lastDeliveredOrder(Order $order): ?Order
    {
        /** @var Order|null */
        return Order::where('customer_id', $order->customer_id)
            ->where('id', '!=', $order->id)
            ->where('state', OrderStateEnum::DISPATCHED)
            ->whereNull('customer_client_id')
            ->whereNull('collection_address_id')
            ->whereNotNull('delivery_address_id')
            ->latest('created_at')
            ->first();
    }

    private function hasBeenDeliveredTo(Order $order, string $addressKey): bool
    {
        return Order::query()
            ->join('addresses', 'addresses.id', '=', 'orders.delivery_address_id')
            ->where('orders.customer_id', $order->customer_id)
            ->where('orders.id', '!=', $order->id)
            ->where('orders.state', OrderStateEnum::DISPATCHED)
            ->whereNull('orders.customer_client_id')
            ->whereNull('orders.collection_address_id')
            ->whereRaw(
                "regexp_replace(lower(coalesce(addresses.postal_code, '')), '\\s', '', 'g') || '|' || regexp_replace(lower(coalesce(addresses.address_line_1, '')), '[^a-z0-9]', '', 'g') = ?",
                [$addressKey]
            )
            ->exists();
    }

    private function postcode(?Address $address): string
    {
        return strtolower(preg_replace('/\s+/', '', (string)$address?->postal_code));
    }

    private function shortLine(Address $address): string
    {
        return collect([$address->address_line_1, $address->postal_code])->filter()->implode(', ');
    }

    /** Aurora recorded collections at our own warehouse or shop, which is not an address the customer moved from */
    private function isOwnPremises(string $postcode): bool
    {
        return DB::table('addresses')
            ->where(fn ($query) => $query
                ->whereIn('id', DB::table('warehouses')->whereNotNull('address_id')->select('address_id'))
                ->orWhereIn('id', DB::table('shops')->whereNotNull('address_id')->select('address_id'))
                ->orWhereIn('id', DB::table('shops')->whereNotNull('collection_address_id')->select('collection_address_id')))
            ->whereRaw("lower(regexp_replace(coalesce(postal_code, ''), '\\s', '', 'g')) = ?", [$postcode])
            ->exists();
    }
}
