<?php

/*
 * author Arya Permana - Kirin
 * created on 26-02-2025-09h-58m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePalletReturns;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePalletReturns;
use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Fulfilment\PalletReturn\Notifications\SendPalletReturnNotification;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePalletReturns;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletReturns;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletReturns;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Http\Resources\Fulfilment\PalletReturnResource;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\ActionRequest;

class RevertPalletReturnToInProcess extends OrgAction
{
    use WithActionUpdate;


    public function handle(PalletReturn $palletReturn, array $modelData): PalletReturn
    {
        $modelData['submitted_at'] = null;
        $modelData['confirmed_at'] = null;
        $modelData['state']                                       = PalletReturnStateEnum::IN_PROCESS;

        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            foreach ($palletReturn->pallets as $pallet) {
                UpdatePallet::run($pallet, [
                    'state'  => PalletStateEnum::REQUEST_RETURN_IN_PROCESS,
                    'status' => PalletStatusEnum::RETURNING
                ]);

                $palletReturn->pallets()->syncWithoutDetaching([
                    $pallet->id => [
                        'state' => PalletReturnItemStateEnum::IN_PROCESS
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

        SendPalletReturnNotification::run($palletReturn);
        return $palletReturn;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }
        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function jsonResponse(PalletReturn $palletReturn): JsonResource
    {
        return new PalletReturnResource($palletReturn);
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

        return $this->handle($palletReturn, $this->validatedData);
    }

    public function maya(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

        return $this->handle($palletReturn, $this->validatedData);
    }

    public function action(PalletReturn $palletReturn): PalletReturn
    {
        $this->asAction = true;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, []);

        return $this->handle($palletReturn, []);
    }

}
