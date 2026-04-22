<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class ReturnPalletToCustomer extends OrgAction
{
    use WithActionUpdate;


    private Pallet $pallet;

    public function handle(Pallet $pallet): Pallet
    {
        $pallet = $this->update($pallet, [
            'location_id' => null,
            'state'       => PalletStateEnum::DISPATCHED,
            'status'      => PalletStatusEnum::RETURNED
        ]);
        return $pallet;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment.{$this->fulfilment->id}.edit");
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, Fulfilment $fulfilment, Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->pallet = $pallet;
        $this->initialisationFromFulfilment($pallet->fulfilment, $request);

        return $this->handle($pallet);
    }

    public function action(Pallet $pallet): Pallet
    {
        $this->pallet = $pallet;

        return $this->handle($pallet);
    }
    public function jsonResponse(Pallet $pallet): PalletResource
    {
        return new PalletResource($pallet);
    }
}
