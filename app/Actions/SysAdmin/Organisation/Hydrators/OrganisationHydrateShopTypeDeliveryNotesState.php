<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Nov 2025 12:26:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateShopTypeDeliveryNotesState implements ShouldBeUnique
{
    use AsAction;


    public function getJobUniqueId(int $organisationID, ShopTypeEnum $shopTypeEnum, DeliveryNoteStateEnum $state): string
    {
        return $organisationID.'_'.$shopTypeEnum->value.'-'.$state->value;
    }


    public function handle(int $organisationID, ShopTypeEnum $shopTypeEnum, DeliveryNoteStateEnum $state): void
    {
        if ($shopTypeEnum == ShopTypeEnum::FULFILMENT) {
            return;
        }
        $organisation = Organisation::find($organisationID);
        if (!$organisation) {
            return;
        }

        $count = DeliveryNote::where('delivery_notes.shop_type', $shopTypeEnum->value)
            ->where('delivery_notes.organisation_id', $organisation->id)
            ->whereNull('delivery_notes.deleted_at')
           ->where('delivery_notes.state', $state)->count();

        $organisation->orderingStats()->update(
            [
                "number_".$shopTypeEnum->snake()."_shop_delivery_notes_state_".$state->snake() => $count

            ]
        );


    }


}
