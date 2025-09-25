<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Sept 2025 12:20:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dispatching;

use App\Actions\Ordering\Order\SendOrderToWarehouse;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairDeliveryNotesRecipientFields
{
    use WithActionUpdate;

    public function handle(DeliveryNote $deliveryNote): void
    {
        /** @var Order $order */
        $order = $deliveryNote->orders()->first();

        if ($order) {
            $deliveryNote->update([
                'email'        => SendOrderToWarehouse::make()->getEmail($order),
                'phone'        => SendOrderToWarehouse::make()->getPhone($order),
                'contact_name' => SendOrderToWarehouse::make()->getContactName($order),
                'company_name' => SendOrderToWarehouse::make()->getCompanyName($order)
            ]);
        }
    }

    public string $commandSignature = 'delivery_notes:repair_recipient_fields';

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
