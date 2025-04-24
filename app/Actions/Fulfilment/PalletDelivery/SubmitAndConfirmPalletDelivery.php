<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 22 Jul 2024 13:51:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Http\Resources\Fulfilment\PalletDeliveryResource;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\ActionRequest;

class SubmitAndConfirmPalletDelivery extends OrgAction
{
    use WithActionUpdate;


    private PalletDelivery $palletDelivery;

    public function handle(PalletDelivery $palletDelivery): PalletDelivery
    {
        $palletDelivery = SubmitPalletDelivery::run($palletDelivery);
        $palletDelivery = ConfirmPalletDelivery::make()->action($palletDelivery);
        return SetPalletDeliveryDate::run($palletDelivery);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->palletDelivery->state != PalletDeliveryStateEnum::IN_PROCESS) {
            return false;
        }

        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function jsonResponse(PalletDelivery $palletDelivery): JsonResource
    {
        return new PalletDeliveryResource($palletDelivery);
    }

    public function action(PalletDelivery $palletDelivery): PalletDelivery
    {
        $this->asAction       = true;
        $this->palletDelivery = $palletDelivery;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, []);
        return $this->handle($palletDelivery);
    }

    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): PalletDelivery
    {
        $this->palletDelivery = $palletDelivery;
        $this->initialisationFromFulfilment($palletDelivery->fulfilment, $request);
        return $this->handle($palletDelivery);
    }



}
