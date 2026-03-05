<?php

/*
 * author Louis Perez
 * created on 04-03-2026-14h-25m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\HydrateModel;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;

class AutomaticallySetPalletReturnAsCancelledIfEmpty extends HydrateModel
{
    use WithActionUpdate;

    public function handle(PalletReturn $palletReturn): PalletReturn
    {
        $baseQuery = $palletReturn->pallets()->whereNotIn('pallet_return_items.state', [PalletReturnStateEnum::DISPATCHED, PalletReturnStateEnum::CANCEL]);
        $palletCount = $baseQuery->count();

        if(empty($palletCount)){
            CancelPalletReturn::run($palletReturn, []);
        }

        return $palletReturn;
    }
}
