<?php
/*
 * author Arya Permana - Kirin
 * created on 17-02-2025-10h-00m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Fulfilment\PalletReturn\AutoAssignServicesToPalletReturn;
use App\Actions\Fulfilment\PalletReturn\Hydrators\PalletReturnHydratePallets;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsCommand;

class AttachPalletToReturn extends OrgAction
{
    use AsCommand;

    private PalletReturn $parent;

    private function handle(PalletReturn $palletReturn, Pallet $pallet): PalletReturn
    {
        $palletReturn->pallets()->attach($pallet->id, [
            'quantity_ordered'     => 1,
            'type'                 => 'Pallet'
        ]);

        $pallet = UpdatePallet::make()->action($pallet, [
            'pallet_return_id' => $palletReturn->id,
            'status' => PalletStatusEnum::RETURNING,
            'state'  => PalletStateEnum::REQUEST_RETURN_IN_PROCESS,
            'requested_for_return_at' => now()
        ]);

        $palletReturn->refresh();

        AutoAssignServicesToPalletReturn::run($palletReturn, $pallet);
        PalletReturnHydratePallets::run($palletReturn);

        return $palletReturn;
    }

    public function action(PalletReturn $palletReturn, Pallet $pallet, int $hydratorsDelay = 0): PalletReturn
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->parent         = $palletReturn;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, []);

        return $this->handle($palletReturn, $pallet);
    }

    public function asController(PalletReturn $palletReturn, Pallet $pallet, ActionRequest $request): PalletReturn
    {
        $this->parent = $palletReturn;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

        return $this->handle($palletReturn, $pallet);
    }
}
