<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 22 Jul 2024 13:51:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Http\Resources\Fulfilment\PalletReturnResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\ActionRequest;

class SubmitAndConfirmPalletReturn extends OrgAction
{
    use WithActionUpdate;

    /**
     * @var array|\ArrayAccess|mixed
     */
    private PalletReturn $palletReturn;
    protected bool $asAction = false;

    public function handle(PalletReturn $palletReturn): PalletReturn
    {
        $palletReturn = SubmitPalletReturn::run($palletReturn, []);

        return ConfirmPalletReturn::make()->action($palletReturn);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }
        if ($this->palletReturn->state != PalletReturnStateEnum::IN_PROCESS) {
            return false;
        }


        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }


    public function jsonResponse(PalletReturn $palletReturn): JsonResource
    {
        return new PalletReturnResource($palletReturn);
    }

    public function asController(FulfilmentCustomer $fulfilmentCustomer, PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->palletReturn = $palletReturn;
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        return $this->handle($palletReturn);
    }

    public function action(PalletReturn $palletReturn): PalletReturn
    {
        $this->asAction     = true;
        $this->palletReturn = $palletReturn;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, []);

        return $this->handle($palletReturn);
    }


}
