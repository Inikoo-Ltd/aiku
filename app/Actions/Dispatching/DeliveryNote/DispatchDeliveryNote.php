<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDeliveryNotes;
use App\Actions\Ordering\Order\DispatchOrderFromDeliveryNote;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShopTypeDeliveryNotes;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDeliveryNotes;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDeliveryNotes;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class DispatchDeliveryNote extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        $deliveryNote = DB::transaction(function () use ($deliveryNote) {
            data_set($modelData, 'dispatched_at', now());
            data_set($modelData, 'state', DeliveryNoteStateEnum::DISPATCHED->value);

            foreach ($deliveryNote->deliveryNoteItems as $item) {
                $this->update($item, [
                    'state'               => DeliveryNoteItemStateEnum::DISPATCHED,
                    'dispatched_at'       => now(),
                    'quantity_dispatched' => $item->quantity_packed
                ]);
            }

            $deliveryNote = $this->update($deliveryNote, $modelData);



            $deliveryNote->refresh();
            if($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
                foreach ($deliveryNote->orders as $order) {
                    DispatchOrderFromDeliveryNote::make()->action($order);
                }
            }



            OrganisationHydrateShopTypeDeliveryNotes::dispatch($deliveryNote->organisation, $deliveryNote->shop->type)
                ->delay($this->hydratorsDelay);


            return $deliveryNote;
        });

        GroupHydrateDeliveryNotes::dispatch($deliveryNote->group)->delay($this->hydratorsDelay);
        OrganisationHydrateDeliveryNotes::dispatch($deliveryNote->organisation)->delay($this->hydratorsDelay);
        ShopHydrateDeliveryNotes::dispatch($deliveryNote->shop)->delay($this->hydratorsDelay);

        return $deliveryNote;
    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }

    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
