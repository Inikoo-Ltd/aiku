<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Jan 2026 15:05:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\Repair;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOrdersWithReplacement
{
    use AsAction;

    public string $commandSignature = 'repair:orders-with-replacement';


    public function asCommand(Command $command): void
    {

        DeliveryNote::query()
            ->where('type', DeliveryNoteTypeEnum::REPLACEMENT)
            ->each(function (DeliveryNote $deliveryNote) use ($command) {
                $command->info("Repairing delivery note {$deliveryNote->reference}");
                $deliveryNote->orders()->each(function (Order $order) use ($command) {
                    $command->info(">> Repairing order {$order->reference}");
                    $order->update(['with_replacement' => true]);

                });
            });

        $command->info('Orders with replacements repaired successfully.');
    }
}
