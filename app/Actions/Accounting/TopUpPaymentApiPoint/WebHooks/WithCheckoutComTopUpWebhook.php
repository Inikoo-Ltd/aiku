<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 May 2025 22:37:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\TopUpPaymentApiPoint\WebHooks;

use App\Actions\Accounting\TopUpPaymentApiPoint\UpdateTopUpPaymentApiPoint;
use App\Enums\Accounting\TopUpPaymentApiPoint\TopUpPaymentApiPointStateEnum;
use App\Models\Accounting\TopUpPaymentApiPoint;
use Illuminate\Support\Arr;

trait WithCheckoutComTopUpWebhook
{
    private function processError(TopUpPaymentApiPoint $topUpPaymentApiPoint, array $payment): TopUpPaymentApiPoint
    {
        return UpdateTopUpPaymentApiPoint::run(
            $topUpPaymentApiPoint,
            [
                'state' => TopUpPaymentApiPointStateEnum::ERROR,
                'processed_at' => now(),
                'data' => [
                    'error' => Arr::except($payment, ['error'])
                ]

            ]
        );
    }


}
