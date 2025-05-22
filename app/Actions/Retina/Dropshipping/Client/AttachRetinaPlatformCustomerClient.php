<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Client;

use App\Actions\RetinaAction;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Dropshipping\WooCommerceUser;

class AttachRetinaPlatformCustomerClient extends RetinaAction
{
    public function handle(Customer $customer, ShopifyUser|TiktokUser|WooCommerceUser $userable, $modelData = []): void
    {
        data_set($modelData, 'customer_id', $customer->id);
        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);

        $userable->clients()->create($modelData);
    }
}
