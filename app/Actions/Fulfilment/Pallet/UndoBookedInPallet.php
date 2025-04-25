<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Apr 2024 14:56:54 Malaysia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Inventory\Location\Hydrators\LocationHydratePallets;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\Fulfilment\Pallet;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Fulfilment\MayaPalletResource;

class UndoBookedInPallet extends OrgAction
{
    use WithActionUpdate;


    public function handle(Pallet $pallet): Pallet
    {

        $oldLocationId = $pallet->location;

        $modelData['state']       = PalletStateEnum::RECEIVED;
        $modelData['status']      = PalletStatusEnum::RECEIVING;
        $modelData['location_id'] = null;

        $pallet = $this->update($pallet, $modelData, ['data']);

        LocationHydratePallets::dispatch($oldLocationId);

        return $pallet;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }
        return $request->user()->authTo("fulfilment.{$this->warehouse->id}.edit");
    }


    public function asController(Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->initialisationFromWarehouse($pallet->warehouse, $request);

        return $this->handle($pallet);
    }


    public function action(Pallet $pallet, int $hydratorsDelay = 0): Pallet
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromWarehouse($pallet->warehouse, []);

        return $this->handle($pallet);
    }

    public function jsonResponse(Pallet $pallet, ActionRequest $request): PalletResource|MayaPalletResource
    {
        if ($request->hasHeader('Maya-Version')) {
            return MayaPalletResource::make($pallet);
        }
        return new PalletResource($pallet);
    }
}
