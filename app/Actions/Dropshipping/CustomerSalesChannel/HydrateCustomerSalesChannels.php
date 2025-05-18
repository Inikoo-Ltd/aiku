<?php

/** @noinspection PhpUnused */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Apr 2025 18:29:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateCustomerClients;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateOrders;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Dropshipping\CustomerSalesChannel;

class HydrateCustomerSalesChannels
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:customer_has_platforms';

    public function __construct()
    {
        $this->model = CustomerSalesChannel::class;
    }

    public function handle(CustomerSalesChannel $customerHasPlatform): void
    {
        CustomerSalesChannelsHydrateCustomerClients::run($customerHasPlatform);
        CustomerSalesChannelsHydrateOrders::run($customerHasPlatform);
        CustomerSalesChannelsHydrateOrders::run($customerHasPlatform);
    }


}
