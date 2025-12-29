<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Nov 2025 19:29:55 Malaysia Time, Plane KL - Bali
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferAllowance;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Models\Discounts\OfferAllowance;
use Lorisleiva\Actions\ActionRequest;

class ActivateOfferAllowance extends OrgAction
{
    use WithActionUpdate;


    public function handle(OfferAllowance $offerAllowance): OfferAllowance
    {

        $modelData = [
            'state' => OfferAllowanceStateEnum::ACTIVE,
            'status' => true
        ];

        if (!$offerAllowance->start_at) {
            data_set($modelData, 'start_at', now());
        }

        $offerAllowance->update($modelData);

        return $offerAllowance;
    }


    public function asController(OfferAllowance $offerAllowance, ActionRequest $request): OfferAllowance
    {
        $this->initialisationFromShop($offerAllowance->shop, $request);

        return $this->handle($offerAllowance);
    }


}
