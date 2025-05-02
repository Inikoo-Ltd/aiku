<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 May 2025 11:11:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerClient;

use App\Actions\Dropshipping\CustomerClient\Hydrators\CustomerClientHydrateBasket;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Dropshipping\CustomerClient;

class HydrateCustomerClients
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:customer_clients {organisations?*} {--S|shop= shop slug} {--s|slug=}';

    public function __construct()
    {
        $this->model = CustomerClient::class;
    }

    public function handle(CustomerClient $customerClient): void
    {
        CustomerClientHydrateBasket::run($customerClient);
    }


}
