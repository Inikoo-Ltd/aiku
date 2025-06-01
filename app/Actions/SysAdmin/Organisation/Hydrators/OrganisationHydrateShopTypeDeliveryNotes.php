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
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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
        $shopsIds = $organisation->shops()
            ->where('shops.type', $shopTypeEnum->value)
            ->pluck('id');

        $stats = $this->getDeliveryNotesStats($organisation);

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'delivery_notes',
                field: 'type',
                enum: DeliveryNoteTypeEnum::class,
                models: DeliveryNote::class,
                where: function ($q) use ($organisation, $shopsIds) {
                    $q->where('organisation_id', $organisation->id)->whereIn('shop_id', $shopsIds);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'delivery_notes',
                field: 'state',
                enum: DeliveryNoteStateEnum::class,
                models: DeliveryNote::class,
                where: function ($q) use ($organisation, $shopsIds) {
                    $q->where('organisation_id', $organisation->id)->whereIn('shop_id', $shopsIds);
                }
            )
        );

        $organisation->orderingStats()->update($stats);
    }


}
