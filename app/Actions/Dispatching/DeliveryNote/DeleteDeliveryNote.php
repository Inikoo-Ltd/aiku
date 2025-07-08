<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDeliveryNotes;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateDeliveryNotes;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDeliveryNotes;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDeliveryNotes;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShopTypeDeliveryNotes;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Facades\DB;

class DeleteDeliveryNote extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $deliveryNote = DB::transaction(function () use ($deliveryNote, $modelData) {
            $deliveryNote = $this->update($deliveryNote, $modelData);

            foreach ($deliveryNote->deliveryNoteItems as $item) {
                $item->pickings()->delete();
            }
            $deliveryNote->deliveryNoteItems()->delete();
            $deliveryNote->delete();

            return $deliveryNote;
        });
        CustomerHydrateDeliveryNotes::dispatch($deliveryNote->customer);
        ShopHydrateDeliveryNotes::dispatch($deliveryNote->shop);
        OrganisationHydrateDeliveryNotes::dispatch($deliveryNote->organisation);
        GroupHydrateDeliveryNotes::dispatch($deliveryNote->group);

        OrganisationHydrateShopTypeDeliveryNotes::dispatch($deliveryNote->organisation, $deliveryNote->shop->type)
            ->delay($this->hydratorsDelay);

        return $deliveryNote;
    }

    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $modelData);

        return $this->handle($deliveryNote, $this->validatedData);
    }
}
