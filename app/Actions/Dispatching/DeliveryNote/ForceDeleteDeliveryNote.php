<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Mar 2025 21:57:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
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

class ForceDeleteDeliveryNote extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        $deliveryNote = DB::transaction(function () use ($deliveryNote) {
            foreach ($deliveryNote->deliveryNoteItems as $item) {
                $item->pickings()->forceDelete();
            }
            $deliveryNote->deliveryNoteItems()->forceDelete();
            $deliveryNote->forceDelete();

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
    public function action(DeliveryNote $deliveryNote): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
