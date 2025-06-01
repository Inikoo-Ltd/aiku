<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Jun 2025 09:58:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateDeliveryNotes;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateShopTypeDeliveryNotes implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateDeliveryNotes;

    public function getJobUniqueId(Organisation $organisation, ShopTypeEnum $shopTypeEnum): string
    {
        return $organisation->id.'_'.$shopTypeEnum->value;
    }


    public function handle(Organisation $organisation, ShopTypeEnum $shopTypeEnum): void
    {

        if ($shopTypeEnum == ShopTypeEnum::FULFILMENT) {
            return;
        }

        $stats = [];



        $count = DeliveryNote::selectRaw("delivery_notes.state, count(*) as total")
            ->leftJoin('shops', 'shops.id', '=', 'delivery_notes.shop_id')
           ->where('shops.type', $shopTypeEnum->value)
            ->where('delivery_notes.organisation_id', $organisation->id)
            ->groupBy('delivery_notes.state')
            ->pluck('total', 'delivery_notes.state')->all();
        foreach (DeliveryNoteStateEnum::cases() as $case) {

            $stats["number_".$shopTypeEnum->snake()."_shop_delivery_notes_state_".$case->snake()] = Arr::get($count, $case->value, 0);
        }


        $organisation->orderingStats()->update($stats);
    }


}
