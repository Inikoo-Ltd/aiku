<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePallets;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePallets;
use App\Actions\Fulfilment\PalletDelivery\UpdatePalletDeliveryStateFromItems;
use App\Actions\Fulfilment\PalletReturn\AutomaticallySetPalletReturnAsPickedIfAllItemsPicked;
use App\Actions\Inventory\Location\Hydrators\LocationHydratePallets;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePallets;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePallets;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePallets;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Fulfilment\Pallet;
use Illuminate\Support\Arr;

class UpdatePalletHydrate extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    private Pallet $pallet;

    public function handle(Pallet $pallet): Pallet
    {
        $oldLocation = $pallet->location;

        if (Arr::hasAny($pallet->getChanges(), ['state'])) {
            if ($pallet->pallet_delivery_id) {
                UpdatePalletDeliveryStateFromItems::run($pallet->palletDelivery);
            }
            if ($pallet->pallet_return_id) {
                AutomaticallySetPalletReturnAsPickedIfAllItemsPicked::run($pallet->palletReturn);
            }

            GroupHydratePallets::dispatch($pallet->group)->delay($this->hydratorsDelay);
            OrganisationHydratePallets::dispatch($pallet->organisation)->delay($this->hydratorsDelay);
            FulfilmentCustomerHydratePallets::dispatch($pallet->fulfilmentCustomer)->delay($this->hydratorsDelay);
            FulfilmentHydratePallets::dispatch($pallet->fulfilment)->delay($this->hydratorsDelay);
            WarehouseHydratePallets::dispatch($pallet->warehouse)->delay($this->hydratorsDelay);
            if ($oldLocation) {
                LocationHydratePallets::dispatch($oldLocation)->delay($this->hydratorsDelay); //Hydrate Old Location
            }
            if ($pallet->location) {
                LocationHydratePallets::dispatch($pallet->location)->delay($this->hydratorsDelay); //Hydrate New Location
            }
        }

        return $pallet->refresh();
    }
}
