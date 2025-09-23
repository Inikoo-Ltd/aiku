<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItem;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UndispatchDeliveryNote extends OrgAction
{
    public function handle(DeliveryNote $deliveryNote): void
    {
        if ($deliveryNote->orders?->first()?->invoices?->count()) {
            throw ValidationException::withMessages(['message' => __('You need to delete invoice before rollback the delivery notes')]);
        }

        UpdateDeliveryNote::make()->action($deliveryNote, [
            'state' => DeliveryNoteStateEnum::PACKED ,
            'finalised_at' => null,
            'dispatched_at' => null,
        ]);

        foreach ($deliveryNote->deliveryNoteItems as $item) {
            if ($item->state == DeliveryNoteItemStateEnum::DISPATCHED) {
                UpdateDeliveryNoteItem::make()->action($item, [
                    'state' =>  DeliveryNoteItemStateEnum::PACKED,
                    'finalised_at' => null,
                    'dispatched_at' => null,
                ]);
            }
        }
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): void
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        $this->handle($deliveryNote);
    }
}
