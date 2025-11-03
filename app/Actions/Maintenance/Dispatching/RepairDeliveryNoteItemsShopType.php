<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Nov 2025 14:53:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dispatching;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairDeliveryNoteItemsShopType
{
    use WithActionUpdate;


    public function handle(DeliveryNote $deliveryNote): void
    {
        $shopType = ShopTypeEnum::B2B;
        if ($deliveryNote->shop) {
            $shopType = $deliveryNote->shop->type;
        }

        $deliveryNote->update([
            'shop_type' => $shopType
        ]);
    }


    public string $commandSignature = 'delivery_note_items:shop_type';

    public function asCommand(Command $command): void
    {
        $count = DeliveryNote::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        DeliveryNote::orderBy('date', 'desc')
            ->chunk(1000, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });
    }

}
