<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Apr 2025 12:54:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Enums\Ordering\ShippingZoneSchema\ShippingZoneSchemaStateEnum;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Models\Ordering\ShippingZone;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShippingZone
{
    use AsAction;

    public function handle(Order $order, $discount = false): ?ShippingZone
    {
        if ($order->collection_address_id) {
            return null;
        }

        $shippingZoneSchema = $this->getShippingZoneSchema($order, $discount);

        $shippingZones = $shippingZoneSchema->shippingZones()
            ->where('status', true)
            ->orderby('position', 'desc')
            ->get();

        foreach ($shippingZones as $shippingZone) {
            $territories = $shippingZone->territories;

            foreach ($territories as $territory) {
                if ($this->getShippingZoneFromTerritory($shippingZone, $order->deliveryAddress, $territory)) {
                    return $shippingZone;
                }
            }
        }


        return null;
    }


    private function getShippingZoneFromTerritory(ShippingZone $shippingZone, Address $address, array $territory): ?ShippingZone
    {
        if ($address->country_code == Arr::get($territory, 'country_code')) {
            if (Arr::has($territory, 'included_postal_codes')) {
                if ($this->postCodeMatch($address->postal_code, $territory['included_postal_codes'])) {
                    return $shippingZone;
                }
            } elseif (Arr::has($territory, 'excluded_postal_codes')) {
                if ($this->postCodeMatch($address->postal_code, $territory['excluded_postal_codes'])) {
                    return $shippingZone;
                }
            } else {
                return $shippingZone;
            }
        }

        return null;
    }


    private function postCodeMatch(string $postalCode, string $pattern): bool
    {
        if (preg_match($pattern, $postalCode)) {
            return true;
        }

        return false;
    }


    private function getShippingZoneSchema(Order $order, $isDiscounted)
    {
        $query = $order->shop->shippingZoneSchemas()
            ->where('state', ShippingZoneSchemaStateEnum::LIVE);
        if ($isDiscounted) {
            $query->where('is_current_discount', true);
        } else {
            $query->where('is_current', true);
        }

        return $query->first();
    }


    //    public string $commandSignature = 'order:get-shipping-zone {order? : The ID of the order}';
    //
    //    public function commandProcess(Command $command, Order $order): void
    //    {
    //        $shippingZone = $this->handle($order);
    //
    //        if ($shippingZone) {
    //            $command->info('Shipping zone found: '.$shippingZone->name.' ('.$shippingZone->slug.') ['.$shippingZone->id.']');
    //        } else {
    //            $command->info('No shipping zone found for the order.');
    //        }
    //    }
    //
    //    public function asCommand(Command $command): int
    //    {
    //        if ($command->argument('order')) {
    //            $orderId = $command->argument('order');
    //            $order   = Order::findOrFail($orderId);
    //            $this->commandProcess($command, $order);
    //        } else {
    //            $count = 0;
    //            $command->info('Processing all orders in chunks of 1000...');
    //
    //            Order::chunk(1000, function ($orders) use ($command, &$count) {
    //                foreach ($orders as $order) {
    //                    $this->commandProcess($command, $order);
    //                    $count++;
    //                }
    //                $command->info("Processed $count orders so far...");
    //            });
    //
    //            $command->info("Completed processing all $count orders.");
    //        }
    //
    //
    //        return 0;
    //    }

}
