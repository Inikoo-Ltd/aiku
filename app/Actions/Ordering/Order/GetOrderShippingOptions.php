<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Models\Dispatching\Shipper;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsAction;

class GetOrderShippingOptions
{
    use AsAction;

    /**
     * @return array<int, array{shipper_id: int, name: string, code: string, amount: float|null, is_tbc: bool, is_selected: bool}>|null
     */
    public function handle(Order $order): ?array
    {
        $entries = collect($order->shippingZone?->shippers_price ?? []);
        if ($entries->count() < 2) {
            return null;
        }

        $shippers = Shipper::whereIn('id', $entries->pluck('shipper_id'))
            ->where('status', true)
            ->get()
            ->keyBy('id');

        $calculator = CalculateOrderShipping::make();

        $options = $entries->map(function (array $entry) use ($order, $shippers, $calculator) {
            $shipper = $shippers->get($entry['shipper_id'] ?? null);
            if (!$shipper) {
                return null;
            }

            $amount = $calculator->getShippingAmountFromPriceData($order, $entry);

            return [
                'shipper_id'  => $shipper->id,
                'name'        => $shipper->name,
                'code'        => $shipper->code,
                'amount'      => is_numeric($amount) ? (float) $amount : null,
                'is_tbc'      => $amount === 'TBC',
                'is_selected' => false,
            ];
        })->filter()->values();

        if ($options->count() < 2) {
            return null;
        }

        $selectedShipperId = $order->shipper_id ?? $options->first()['shipper_id'];

        return $options->map(function (array $option) use ($selectedShipperId) {
            $option['is_selected'] = $option['shipper_id'] == $selectedShipperId;

            return $option;
        })->all();
    }
}
