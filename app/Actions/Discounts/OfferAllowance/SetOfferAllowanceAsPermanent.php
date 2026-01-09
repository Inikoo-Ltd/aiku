<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Jan 2026 13:05:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferAllowance;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Models\Discounts\OfferAllowance;

class SetOfferAllowanceAsPermanent extends OrgAction
{
    use WithActionUpdate;


    public function handle(OfferAllowance $offerAllowance): OfferAllowance
    {

        $modelData = [
            'state'             => OfferAllowanceStateEnum::ACTIVE,
            'status'            => true,
            'end_at'            => null,
            'duration'          => OfferDurationEnum::PERMANENT,
        ];

        if (!$offerAllowance->start_at) {
            data_set($modelData, 'start_at', now());
        }

        $offerAllowance->update($modelData);

        return $offerAllowance;
    }





}
