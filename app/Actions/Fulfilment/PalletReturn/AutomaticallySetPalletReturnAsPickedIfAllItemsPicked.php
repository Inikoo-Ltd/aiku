<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Mar 2024 14:21:35 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\HydrateModel;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;

class AutomaticallySetPalletReturnAsPickedIfAllItemsPicked extends HydrateModel
{
    use WithActionUpdate;

    public function handle(PalletReturn $palletReturn): PalletReturn
    {
        $currentState = $palletReturn->state instanceof PalletReturnStateEnum ? $palletReturn->state->value : $palletReturn->state;
        if ($currentState !== PalletReturnStateEnum::PICKING->value) {
            return $palletReturn;
        }

        $baseQuery = $palletReturn->pallets()->whereNot('pallets.state', [PalletStateEnum::DISPATCHED]);
        $palletCount = (clone $baseQuery)->count();

        $palletPickedCount = (clone $baseQuery)
            ->wherePivot('state', PalletReturnItemStateEnum::PICKED->value)
            ->count();
        $palletNotPickedCount = (clone $baseQuery)
            ->wherePivot('state', PalletReturnItemStateEnum::NOT_PICKED->value)
            ->count();
        $palletCancelCount = (clone $baseQuery)
            ->wherePivot('state', PalletReturnItemStateEnum::CANCEL->value)
            ->count();

        if (($palletPickedCount + $palletNotPickedCount + $palletCancelCount) == $palletCount) {
            $palletReturn = SetPalletReturnAsPicked::run($palletReturn);
        }

        return $palletReturn;
    }
}
