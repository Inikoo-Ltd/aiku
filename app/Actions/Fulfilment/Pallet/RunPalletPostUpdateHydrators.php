<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Apr 2025 20:33:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePallets;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePallets;
use App\Actions\Fulfilment\Pallet\Search\PalletRecordSearch;
use App\Actions\Fulfilment\PalletDelivery\SetPalletDeliveryAutoServices;
use App\Actions\Inventory\Location\Hydrators\LocationHydratePallets;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePallets;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePallets;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePallets;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Location;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class RunPalletPostUpdateHydrators
{
    use asAction;

    public function handle(Pallet $pallet, array $originalData, array $changes, $hydratorsDelay = 0): void
    {
        if (Arr::hasAny($changes, ['state'])) {
            GroupHydratePallets::dispatch($pallet->group)->delay($hydratorsDelay);
            OrganisationHydratePallets::dispatch($pallet->organisation)->delay($hydratorsDelay);
            FulfilmentCustomerHydratePallets::dispatch($pallet->fulfilmentCustomer)->delay($hydratorsDelay);
            FulfilmentHydratePallets::dispatch($pallet->fulfilment)->delay($hydratorsDelay);
            WarehouseHydratePallets::dispatch($pallet->warehouse)->delay($hydratorsDelay);
        }

        if (Arr::hasAny($changes, ['state', 'location_id'])) {
            if (Arr::get($originalData, 'location_id')) {
                $oldLocation = Location::find($originalData['location_id']);
                LocationHydratePallets::dispatch($oldLocation)->delay($hydratorsDelay);
            }
            if ($pallet->location) {
                LocationHydratePallets::dispatch($pallet->location)->delay($hydratorsDelay); //Hydrate New Location
            }
        }


        if (Arr::get($originalData, 'type') !== $pallet->type) {
            SetPalletDeliveryAutoServices::run($pallet->palletDelivery);
        }
        if (Arr::hasAny($changes, [
            'reference',
            'type',
            'customer_reference',
            'state',
            'status',
            'slug'
        ])) {
            PalletRecordSearch::dispatch($pallet);
        }
    }

}
