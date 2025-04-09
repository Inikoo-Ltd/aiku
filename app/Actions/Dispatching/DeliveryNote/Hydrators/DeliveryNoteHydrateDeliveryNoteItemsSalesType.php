<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:53:53 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\Hydrators;

use App\Actions\Dispatching\DeliveryNoteItem\Hydrators\DeliveryNoteItemHydrateSalesType;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemSalesTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class DeliveryNoteHydrateDeliveryNoteItemsSalesType implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;


    public function getJobUniqueId(DeliveryNote $deliveryNote): string
    {
        return $deliveryNote->id;
    }


    public function handle(DeliveryNote $deliveryNote): void
    {
        if ($deliveryNote->type == DeliveryNoteTypeEnum::REPLACEMENT) {
            $this->updateItemsSalesType($deliveryNote, DeliveryNoteItemSalesTypeEnum::NA);
        } else {
            $numberOrders = $deliveryNote->orders()->count();
            if ($numberOrders == 0) {
                $this->updateItemsSalesType($deliveryNote, DeliveryNoteItemSalesTypeEnum::NA);
            } elseif ($numberOrders == 1) {
                $order     = $deliveryNote->orders->first();
                $salesType = DeliveryNoteItemHydrateSalesType::make()->getSalesTypeFromOrder($order);
                $this->updateItemsSalesType($deliveryNote, $salesType);
            } else {
                foreach ($deliveryNote->deliveryNoteItems as $deliveryNoteItem) {
                    DeliveryNoteItemHydrateSalesType::run($deliveryNoteItem);
                }
            }
        }
    }

    public function updateItemsSalesType(DeliveryNote $deliveryNote, DeliveryNoteItemSalesTypeEnum $hydrateSalesType): void
    {
        $deliveryNote->deliveryNoteItems()->update(
            [
                'sales_type' => $hydrateSalesType
            ]
        );
    }

}
