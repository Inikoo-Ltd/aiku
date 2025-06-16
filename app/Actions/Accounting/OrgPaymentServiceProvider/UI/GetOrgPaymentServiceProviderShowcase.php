<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Apr 2024 20:11:12 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrgPaymentServiceProvider\UI;

use App\Models\Accounting\OrgPaymentServiceProvider;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgPaymentServiceProviderShowcase
{
    use AsObject;

    public function handle(OrgPaymentServiceProvider $orgPaymentServiceProvider): array
    {
        return [
            'type'   => $orgPaymentServiceProvider->type,
            'slug'   => $orgPaymentServiceProvider->slug,
            'code'   => $orgPaymentServiceProvider->code,
            'created_at' => $orgPaymentServiceProvider->created_at,
            'payment_service_provider' => $orgPaymentServiceProvider->paymentServiceProvider,
        ];
    }
}
