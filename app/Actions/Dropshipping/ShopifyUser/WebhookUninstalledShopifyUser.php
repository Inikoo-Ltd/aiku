<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\ShopifyUser;

use App\Actions\Dropshipping\CustomerSalesChannel\CloseCustomerSalesChannel;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;

class WebhookUninstalledShopifyUser extends OrgAction
{
    use WithActionUpdate;


    public function handle(ShopifyUser $shopifyUser): void
    {
        if (! $shopifyUser->customerSalesChannel) {
            return;
        }

        CloseCustomerSalesChannel::run($shopifyUser->customerSalesChannel);
    }

    public function asController(ShopifyUser $shopifyUser): void
    {
        $this->handle($shopifyUser);
    }
}
