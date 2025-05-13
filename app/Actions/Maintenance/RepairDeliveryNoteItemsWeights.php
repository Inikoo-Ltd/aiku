<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 May 2025 15:29:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance;

use App\Actions\Dispatching\DeliveryNote\CalculateDeliveryNoteTotalAmounts;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairDeliveryNoteItemsWeights
{
    use WithActionUpdate;


    public function handle(DeliveryNote $deliveryNote): void
    {
        foreach ($deliveryNote->deliveryNoteItems as $deliveryNoteItem) {
            $requiredWeight = (int)floor($deliveryNoteItem->weight * 1000);

            $pickedWeight = 0;

            if ($deliveryNoteItem->quantity_required != 0) {
                $pickedWeight = (int)floor($requiredWeight * $deliveryNoteItem->quantity_picked / $deliveryNoteItem->quantity_required);
            }

            $deliveryNoteItem->update(
                [
                    'estimated_required_weight' => $requiredWeight,
                    'estimated_picked_weight'   => $pickedWeight,
                ]
            );
        }

        if ($deliveryNote->weight == 0) {
            $deliveryNote->update([
                'weight' => null
            ]);
        }

        CalculateDeliveryNoteTotalAmounts::run($deliveryNote);
    }


    public string $commandSignature = 'delivery_note_items:repair_weight';

    public function asCommand(Command $command): void
    {
        $count = DeliveryNote::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        DeliveryNote::chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });
    }

}
