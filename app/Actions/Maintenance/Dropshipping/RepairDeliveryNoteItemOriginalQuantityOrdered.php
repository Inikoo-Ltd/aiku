<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-15h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairDeliveryNoteItemOriginalQuantityOrdered
{
    use WithActionUpdate;


    public function handle(DeliveryNote $deliveryNote): void
    {
        foreach($deliveryNote->deliveryNoteItems as $item) {
            $item->update([
                'original_quantity_required' => $item->quantity_required
            ]);
        }
    }


    public string $commandSignature = 'repair:delivery_note_item_original_quantity';

    public function asCommand(Command $command): void
    {
        $count = DeliveryNote::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        DeliveryNote::orderBy('id')
            ->chunk(100, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });
        
    }

}
