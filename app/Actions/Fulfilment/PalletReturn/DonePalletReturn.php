<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Http\Resources\Fulfilment\PalletReturnResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\ActionRequest;

class DonePalletReturn extends OrgAction
{
    use WithActionUpdate;


    public function handle(PalletReturn $palletReturn, array $modelData): PalletReturn
    {
        $modelData[PalletReturnStateEnum::DONE->value.'_at'] = now();
        $modelData['state']                                  = PalletReturnStateEnum::DONE;

        foreach ($palletReturn->pallets as $pallet) {
            $pallet->update([
                'state' => PalletStateEnum::SETTLED
            ]);
        }

        return $this->update($palletReturn, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasPermissionTo("fulfilments.{$this->fulfilment->id}.edit");
    }

    public function jsonResponse(PalletReturn $palletReturn): JsonResource
    {
        return new PalletReturnResource($palletReturn);
    }

    public function asController(Organisation $organisation, FulfilmentCustomer $fulfilmentCustomer, PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        return $this->handle($palletReturn, $this->validatedData);
    }
}
