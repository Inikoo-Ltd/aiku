<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 May 2025 01:09:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Enums\Ordering\Order\OrderShippingEngineEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\ShippingZone;
use App\Models\Ordering\ShippingZoneSchema;
use App\Models\Ordering\Transaction;
use CommerceGuys\Addressing\Address;
use CommerceGuys\Addressing\Zone\Zone;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class CalculateOrderShipping
{
    use AsObject;

    protected bool $toBeConfirmed = false;

    public function handle(Order $order): Order
    {
        if (in_array($order->shipping_engine, [OrderShippingEngineEnum::MANUAL, OrderShippingEngineEnum::NO_APPLICABLE, OrderShippingEngineEnum::TO_BE_CONFIRMED_SET])) {
            return $order;
        }

        $shippingZoneSchema = $order->shop->currentShippingZoneSchema;
        if (!$shippingZoneSchema) {
            if ($order->shipping_engine == OrderShippingEngineEnum::AUTO) {
                $order->update([
                    'shipping_engine' => OrderShippingEngineEnum::MANUAL,
                ]);
            }
            return $order;
        }

        list($shippingAmount, $shippingZone) = $this->getShippingAmountAndShippingZone($order, $shippingZoneSchema);

        $shippingTransaction = $order->transactions()->where('model_type', 'ShippingZone')->first();
        if ($shippingTransaction) {
            $this->updateShippingTransaction($shippingTransaction, $shippingZone, $shippingAmount);
        } else {
            $this->storeShippingTransaction($order, $shippingZone, $shippingAmount);
        }

        if ($this->toBeConfirmed) {
            $order->update([
                'shipping_engine' => OrderShippingEngineEnum::TO_BE_CONFIRMED,
            ]);
        } else {
            $order->update([
                'shipping_engine' => OrderShippingEngineEnum::AUTO,
            ]);
        }

        return $order;
    }

    private function storeShippingTransaction(Order $order, ShippingZone $shippingZone, $shippingAmount): Transaction
    {
        return StoreTransaction::run(
            $order,
            $shippingZone->historicAsset,
            [
                'quantity_ordered' => 1,
                'gross_amount'     => $shippingAmount,
                'net_amount'       => $shippingAmount,

            ],
            false
        );
    }


    private function updateShippingTransaction(Transaction $transaction, ShippingZone $shippingZone, $shippingAmount): Transaction
    {
        return UpdateTransaction::run(
            $transaction,
            [
                'model_id'          => $shippingZone->id,
                'asset_id'          => $shippingZone->asset_id,
                'historic_asset_id' => $shippingZone->historicAsset->id,
                'gross_amount'      => $shippingAmount ?? 0,
                'net_amount'        => $shippingAmount ?? 0,
            ],
            false
        );
    }


    private function getShippingAmountAndShippingZone(Order $order, ShippingZoneSchema $shippingZoneSchema): array
    {
        $shippingZones = $shippingZoneSchema->shippingZones()->where('status', true)->orderBy('position', 'desc')->get();

        foreach ($shippingZones as $shippingZone) {
            if ($this->matchTerritories($order, $shippingZone)) {
                $shippingAmount = $this->getShippingAmountFromShippingZone($order, $shippingZone);

                return [$shippingAmount, $shippingZone];
            }
        }

        return [null, null];
    }


    private function getShippingAmountFromShippingZone(Order $order, ShippingZone $shippingZone)
    {
        $pricingType = Arr::get($shippingZone->price, 'type');
        if ($pricingType == 'Step Order Items Net Amount') {
            return $this->getPriceBlanketFromAmount($order->goods_amount, Arr::get($shippingZone->price, 'steps'));
        } elseif ($pricingType == 'TBC') {
            $this->toBeConfirmed = true;

            return null;
        }

        return null;
    }

    private function getPriceBlanketFromAmount($amount, array $priceBlankets)
    {
        foreach ($priceBlankets as $priceBlanket) {
            if (
                ($priceBlanket['to'] == 'INF' && $amount >= $priceBlanket['from'])
                || ($amount <= $priceBlanket['to'] && $amount >= $priceBlanket['from'])

            ) {
                return $priceBlanket['price'];
            }
        }

        return null;
    }


    private function matchTerritories(Order $order, ShippingZone $shippingZone): bool
    {
        if (!$shippingZone->territories) {
            // The rest of the world does not have territories defined
            return true;
        }
        $helperZone      = new Zone([
            'id'          => $shippingZone->slug,
            'label'       => $shippingZone->name,
            'territories' => $shippingZone->territories,
        ]);
        $deliveryAddress = $order->deliveryAddress;
        $helperAddress   = new Address();
        $helperAddress   = $helperAddress
            ->withCountryCode($deliveryAddress->country_code)
            ->withPostalCode($deliveryAddress->postal_code);


        return $helperZone->match($helperAddress);
    }
}
