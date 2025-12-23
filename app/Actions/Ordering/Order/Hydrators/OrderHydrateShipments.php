<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Dec 2025 12:19:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\Hydrators;

use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrderHydrateShipments implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int|null $orderID): string
    {
        return $orderID ?? 'empty';
    }

    public function handle(int|null $orderID): void
    {
        if (!$orderID) {
            return;
        }
        $order = Order::find($orderID);
        if (!$order) {
            return;
        }

        $shipments = $order->deliveryNotes()
            ->with(['shipments.shipper'])
            ->get()
            ->flatMap(function ($deliveryNote) {
                return $deliveryNote->shipments->map(function ($shipment) use ($deliveryNote) {
                    $shipment->delivery_note_id        = $deliveryNote->id;
                    $shipment->delivery_note_reference = $deliveryNote->reference;

                    return $shipment;
                });
            })
            ->filter(
                fn ($shipment) => $shipment->tracking
                && strtolower($shipment->tracking) !== 'na'
                && $shipment->tracking !== '.'
            );

        $trackingNumbers = $shipments->pluck('tracking')
            ->filter()
            ->unique()
            ->sort()
            ->implode(', ');

        $shippingData = $shipments->map(fn ($shipment) => [
            'delivery_note_id'        => $shipment->delivery_note_id,
            'delivery_note_reference' => $shipment->delivery_note_reference,
            'shipping_id'             => $shipment->id,
            'shipper_slug'            => $shipment->shipper?->slug,
            'tracking_number'         => $shipment->tracking,
            'trackings'               => $shipment->trackings,
            'tracking_urls'           => $shipment->tracking_urls,
        ])->values()->toArray();

        $order->update([
            'tracking_number' => $trackingNumbers ?: null,
            'shipping_data'   => $shippingData ?: [],
        ]);
    }

    public function getCommandSignature(): string
    {
        return 'orders:hydrate_shipments {order_id?}';
    }

    public function asCommand($command): void
    {
        if ($command->argument('order_id')) {
            $orderID = $command->argument('order_id');
            $this->handle($orderID);

            return;
        }

        $query = Order::query()
            ->whereNull('source_id')->orderBy('id', 'desc');

        $count = $query->count();

        if ($count === 0) {
            $command->info('No orders found to hydrate.');

            return;
        }

        $bar = $command->getOutput()->createProgressBar($count);

        $bar->start();

        $query->chunk(100, function ($orders) use ($bar) {
            foreach ($orders as $order) {
                $this->handle($order->id);
                $bar->advance();
            }
        });

        $bar->finish();

        $command->newLine();
    }
}
