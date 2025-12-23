<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Dec 2025 12:09:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\Hydrators;

use App\Models\Dispatching\DeliveryNote;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class DeliveryNoteHydrateShipments implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int|null $deliveryNoteID): string
    {
        return $deliveryNoteID ?? 'empty';
    }

    public function handle(int|null $deliveryNoteID): void
    {
        if (!$deliveryNoteID) {
            return;
        }
        $deliveryNote = DeliveryNote::find($deliveryNoteID);
        if (!$deliveryNote) {
            return;
        }

        if ($deliveryNote->collection_address_id) {
            $deliveryNote->update([
                'tracking_number' => null,
                'shipping_data'   => [
                    'is_collection' => true
                ]
            ]);

            return;
        }


        $shipments = $deliveryNote->shipments()
            ->with('shipper')
            ->get()
            ->filter(
                fn($shipment) => $shipment->tracking
                    && strtolower($shipment->tracking) !== 'na'
                    && $shipment->tracking !== '.'
            );

        $trackingNumbers = $shipments->pluck('tracking')
            ->filter()
            ->unique()
            ->sort()
            ->implode(', ');

        $shippingData = $shipments->map(fn($shipment) => [
            'shipping_id'     => $shipment->id,
            'shipper_slug'    => $shipment->shipper?->slug,
            'shipper_label'   => $shipment->trade_as ?: $shipment->shipper?->code,
            'tracking_number' => $shipment->tracking,
            'trackings'       => $shipment->trackings,
            'tracking_urls'   => $shipment->tracking_urls,
        ])->values()->toArray();

        $deliveryNote->update([
            'tracking_number' => $trackingNumbers ?: null,
            'shipping_data'   => $shippingData ?: [],
        ]);
    }

    public function getCommandSignature(): string
    {
        return 'delivery_notes:hydrate_shipments {delivery_note_id?}';
    }

    public function asCommand($command): void
    {
        if ($command->argument('delivery_note_id')) {
            $deliveryNoteID = $command->argument('delivery_note_id');
            $this->handle($deliveryNoteID);

            return;
        }

        $query = DeliveryNote::query()
            ->whereNull('source_id');

        $count = $query->count();

        if ($count === 0) {
            $command->info('No delivery notes found to hydrate.');

            return;
        }

        $bar = $command->getOutput()->createProgressBar($count);

        $bar->start();

        $query->chunk(100, function ($deliveryNotes) use ($bar) {
            foreach ($deliveryNotes as $deliveryNote) {
                $this->handle($deliveryNote->id);
                $bar->advance();
            }
        });

        $bar->finish();

        $command->newLine();
    }
}
