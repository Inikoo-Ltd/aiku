<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Feb 2025 23:29:35 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem\Hydrators;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;

class StoreItemHydratePallets implements ShouldBeUnique
{
    use WithActionUpdate;
    use WithEnumStats;

    public function getJobUniqueId(StoredItem $storedItem): string
    {
        return $storedItem->id;
    }

    public function handle(StoredItem $storedItem): void
    {

        $stats = [
            'number_pallets' => DB::table('pallet_stored_items')
            ->join('pallets', 'pallet_stored_items.pallet_id', '=', 'pallets.id')
            ->where('pallet_stored_items.stored_item_id', $storedItem->id)
            ->where('pallet_stored_items.in_process', false)
            ->whereIn('pallets.status', [PalletStatusEnum::STORING, PalletStatusEnum::RETURNING])
            ->count(),
        ];
        $storedItem->update($stats);
    }
}
