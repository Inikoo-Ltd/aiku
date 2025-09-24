<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Sept 2025 19:31:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dispatching;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairDeliveryNotesAddress
{
    use WithActionUpdate;
    use WithFixedAddressActions;

    public function handle(DeliveryNote $deliveryNote): void
    {

        if (!$deliveryNote->delivery_locked) {
            $deliveryAddress = $deliveryNote->deliveryAddress;
            $this->createFixedAddress(
                $deliveryNote,
                $deliveryAddress,
                'Ordering',
                'delivery',
                'address_id'
            );

            $deliveryNote->update([
                'delivery_locked' => true,
            ]);
        }
    }


    public string $commandSignature = 'delivery_notes:repair_address';

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
