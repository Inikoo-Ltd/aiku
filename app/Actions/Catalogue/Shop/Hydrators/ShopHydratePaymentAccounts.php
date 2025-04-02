<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 17:04:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydratePaymentAccounts implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }


    public function handle(Shop $shop): void
    {
        $stats = [
            'number_org_payment_service_providers' => $shop->orgPaymentServiceProviders()->count(),
            'number_payment_accounts'              => $shop->paymentAccountShops()->count(),
        ];

        $shop->accountingStats()->update($stats);
    }


}
