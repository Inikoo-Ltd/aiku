<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Nov 2025 14:53:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateDeliveryNotes;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDeliveryNotes;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDeliveryNotesState;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDeliveryNotes;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDeliveryNotesState;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShopTypeDeliveryNotesState;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;

trait HasDeliveryNoteHydrators
{
    public function storeDeliveryNoteHydrators(DeliveryNote $deliveryNote): void
    {
        GroupHydrateDeliveryNotes::dispatch($deliveryNote->group_id, $deliveryNote->type)->delay($this->hydratorsDelay);
        OrganisationHydrateDeliveryNotes::dispatch($deliveryNote->organisation_id, $deliveryNote->type)->delay($this->hydratorsDelay);
        ShopHydrateDeliveryNotes::dispatch($deliveryNote->shop_id, $deliveryNote->type)->delay($this->hydratorsDelay);
        CustomerHydrateDeliveryNotes::dispatch($deliveryNote->customer_id, $deliveryNote->type)->delay($this->hydratorsDelay);
    }

    public function deliveryNoteHandlingHydrators(DeliveryNote $deliveryNote, DeliveryNoteStateEnum $deliveryNoteStateEnum): void
    {
        GroupHydrateDeliveryNotesState::dispatch($deliveryNote->group_id, $deliveryNoteStateEnum)->delay($this->hydratorsDelay);
        OrganisationHydrateDeliveryNotesState::dispatch($deliveryNote->organisation_id, $deliveryNoteStateEnum)->delay($this->hydratorsDelay);
        ShopHydrateDeliveryNotesState::dispatch($deliveryNote->shop_id, $deliveryNoteStateEnum)->delay($this->hydratorsDelay);
        // Get directly from shop.type because some deliveryNote has no shop_type somehow (null), probably old order_data
        OrganisationHydrateShopTypeDeliveryNotesState::dispatch($deliveryNote->organisation_id, $deliveryNote->shop_type ?? $deliveryNote->shop->type, $deliveryNoteStateEnum);
    }

}
