<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Nov 2025 14:53:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateDeliveryNotes;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateDeliveryNotesState implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateDeliveryNotes;

    public function getJobUniqueId(int $shopId, DeliveryNoteStateEnum $state): string
    {
        return $shopId.'-'.$state->value;
    }

    public function handle(int $shopId, DeliveryNoteStateEnum $state): void
    {
        $shop = Shop::find($shopId);
        if (!$shop) {
            return;
        }

        $stats = $this->getDeliveryStateNotesStats($state, $shop);
        $shop->orderHandlingStats()->update($stats);
    }


}
