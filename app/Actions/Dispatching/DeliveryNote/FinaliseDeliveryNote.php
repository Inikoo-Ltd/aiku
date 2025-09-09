<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Ordering\Order\InvoiceOrderFromDeliveryNoteFinalisation;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShopTypeDeliveryNotes;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class FinaliseDeliveryNote extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        if ($deliveryNote->shipments->isEmpty()) {
            throw ValidationException::withMessages([
                  'message' => [
                            'delivery_note' => 'Shipment should be set before finalizing.',
                        ]
            ]);
        }

        $deliveryNote = DB::transaction(function () use ($deliveryNote) {
            data_set($modelData, 'finalised_at', now());
            data_set($modelData, 'state', DeliveryNoteStateEnum::FINALISED->value);


            $deliveryNote->refresh();
            if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
                foreach ($deliveryNote->orders as $order) {
                    InvoiceOrderFromDeliveryNoteFinalisation::make()->action($order);
                }
            }

            return $this->update($deliveryNote, $modelData);

        });


        OrganisationHydrateShopTypeDeliveryNotes::dispatch($deliveryNote->organisation, $deliveryNote->shop->type)
            ->delay($this->hydratorsDelay);

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
