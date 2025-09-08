<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Sept 2025 09:44:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dispatching;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairDeliveryNoteMissingOrderNotes
{
    use WithActionUpdate;


    public function handle(DeliveryNote $deliveryNote): void
    {
        $order = $deliveryNote->orders()->first();
        if (!$order) {
            return;
        }

        $deliveryNote->update(
            [
                'customer_notes' => $order->customer_notes,
                'public_notes'   => $order->public_notes,
                'internal_notes' => $order->internal_notes,
                'shipping_notes' => $order->shipping_notes,
            ]
        );
    }


    public string $commandSignature = 'delivery_note:repair_mising_order_notes';

    public function asCommand(Command $command): void
    {
        $count = DeliveryNote::whereNull('source_id')->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        DeliveryNote::whereNull('source_id')->orderBy('date', 'desc')
            ->chunk(1000, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });
    }

}
