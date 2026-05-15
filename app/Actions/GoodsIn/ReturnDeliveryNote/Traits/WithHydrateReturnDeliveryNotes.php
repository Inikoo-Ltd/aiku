<?php

/*
 * author Louis Perez
 * created on 15-05-2026-11h-13m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNote\Traits;

use App\Actions\GoodsIn\ReturnDeliveryNote\Hydrators\ShopHydrateReturnDeliveryNotes;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateReturnDeliveryNotes;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateReturnDeliveryNotes;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateReturnDeliveryNotes;
use App\Models\GoodsIn\ReturnDeliveryNote;

trait WithHydrateReturnDeliveryNotes
{
    public function hydrateReturnDeliveryNotes(ReturnDeliveryNote $returnDeliveryNote)
    {
        GroupHydrateReturnDeliveryNotes::dispatch($returnDeliveryNote->group);
        OrganisationHydrateReturnDeliveryNotes::dispatch($returnDeliveryNote->organisation);
        WarehouseHydrateReturnDeliveryNotes::dispatch($returnDeliveryNote->warehouse);
        ShopHydrateReturnDeliveryNotes::dispatch($returnDeliveryNote->shop);
    }
}
