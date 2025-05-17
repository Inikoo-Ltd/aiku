<?php

/** @noinspection PhpUnused */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Apr 2025 18:29:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerHasPlatforms;

use App\Actions\Dropshipping\CustomerHasPlatforms\Hydrators\CustomerHasPlatformsHydrateCustomerClients;
use App\Actions\Dropshipping\CustomerHasPlatforms\Hydrators\CustomerHasPlatformsHydrateOrders;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\CRM\CustomerSalesChannel;

class HydrateCustomerHasPlatforms
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:customer_has_platforms';

    public function __construct()
    {
        $this->model = CustomerSalesChannel::class;
    }

    public function handle(CustomerSalesChannel $customerHasPlatform): void
    {
        CustomerHasPlatformsHydrateCustomerClients::run($customerHasPlatform);
        CustomerHasPlatformsHydrateOrders::run($customerHasPlatform);
        CustomerHasPlatformsHydrateOrders::run($customerHasPlatform);
    }


}
