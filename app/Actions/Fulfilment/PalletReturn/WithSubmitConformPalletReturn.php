<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Apr 2025 19:28:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletReturns;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletReturns;
use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletReturns;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletReturns;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletReturns;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Fulfilment\PalletReturn;

trait WithSubmitConformPalletReturn
{
    public function processChangeState(PalletReturnStateEnum $state, PalletReturn $palletReturn, array $modelData): PalletReturn
    {
        $modelData['state'] = $state;

        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            foreach ($palletReturn->pallets as $pallet) {
                UpdatePallet::run(
                    pallet: $pallet,
                    modelData: [
                        'state'  => $state == PalletReturnStateEnum::SUBMITTED ? PalletStateEnum::REQUEST_RETURN_SUBMITTED : PalletStateEnum::REQUEST_RETURN_CONFIRMED,
                        'status' => PalletStatusEnum::RETURNING
                    ],
                    hydrateParents: false
                );

                $palletReturn->pallets()->syncWithoutDetaching([
                    $pallet->id => [
                        'state' => $state == PalletReturnStateEnum::SUBMITTED ? PalletReturnItemStateEnum::SUBMITTED : PalletReturnItemStateEnum::CONFIRMED
                    ]
                ]);
            }
        }

        $palletReturn = $this->update($palletReturn, $modelData);

        GroupHydratePalletReturns::dispatch($palletReturn->group);
        OrganisationHydratePalletReturns::dispatch($palletReturn->organisation);
        WarehouseHydratePalletReturns::dispatch($palletReturn->warehouse);
        FulfilmentCustomerHydratePalletReturns::dispatch($palletReturn->fulfilmentCustomer);
        FulfilmentHydratePalletReturns::dispatch($palletReturn->fulfilment);

        return $palletReturn;
    }
}
