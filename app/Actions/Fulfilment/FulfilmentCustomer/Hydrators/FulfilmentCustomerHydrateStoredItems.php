<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jan 2024 19:23:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentCustomer\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\PalletStoredItem\PalletStoredItemStateEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentCustomerHydrateStoredItems implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(FulfilmentCustomer $fulfilmentCustomer): string
    {
        return $fulfilmentCustomer->id;
    }

    public function handle(FulfilmentCustomer $fulfilmentCustomer): void
    {
        $numberPalletsWithStoredItemsStateStoring = DB::table('pallet_stored_items')->where('pallet_stored_items.state', PalletStoredItemStateEnum::ACTIVE)->leftjoin('pallets', 'pallet_stored_items.pallet_id', '=', 'pallets.id')->where('fulfilment_customer_id', $fulfilmentCustomer->id)->count();

        $stats = [
            'number_stored_items'        => $fulfilmentCustomer->storedItems->count(),
            'number_pallets_with_stored_items_state_storing' => $numberPalletsWithStoredItemsStateStoring
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'stored_items',
            field: 'state',
            enum: StoredItemStateEnum::class,
            models: StoredItem::class,
            where: function ($q) use ($fulfilmentCustomer) {
                $q->where('fulfilment_customer_id', $fulfilmentCustomer->id);
            }
        ));

        $fulfilmentCustomer->update($stats);
    }


}
