<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 18:11:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\TopUpPaymentApiPoint;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\TopUpPaymentApiPoint;

class UpdateTopUpPaymentApiPoint extends RetinaAction
{
    use WithActionUpdate;


    public function handle(TopUpPaymentApiPoint $topUpPaymentApiPoint, array $modelData): TopUpPaymentApiPoint
    {
        return $this->update($topUpPaymentApiPoint, $modelData);
    }

}
