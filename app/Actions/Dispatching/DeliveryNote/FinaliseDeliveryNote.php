<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Ordering\Order\DispatchOrderFromDeliveryNote;
use App\Actions\Ordering\Order\InvoiceOrderFromDeliveryNoteFinalisation;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShopTypeDeliveryNotes;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class FinaliseDeliveryNote extends OrgAction
{
    use WithActionUpdate;

    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        $deliveryNote = DB::transaction(function () use ($deliveryNote) {
            data_set($modelData, 'finalised_at', now());
            data_set($modelData, 'state', DeliveryNoteStateEnum::FINALISED->value);


            $deliveryNote->refresh();
            foreach ($deliveryNote->orders as $order) {
                InvoiceOrderFromDeliveryNoteFinalisation::make()->action($order);
            }

            $deliveryNote = $this->update($deliveryNote, $modelData);

            return $deliveryNote;
        });

        OrganisationHydrateShopTypeDeliveryNotes::dispatch($deliveryNote->organisation, $deliveryNote->shop->type)
            ->delay($this->hydratorsDelay);

        return $deliveryNote;
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }

    public function action(DeliveryNote $deliveryNote): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
