<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Nov 2025 16:09:42 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel\Json;

use App\Actions\OrgAction;
use App\Models\Catalogue\Collection;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;

class GetCustomerCollectionSalesChannelIds extends OrgAction
{
    public function handle(Customer $customer, Collection $collection): array
    {
        return GetRetinaCustomerCollectionSalesChannelIds::run($customer, $collection);
    }

    public function asController(Customer $customer, Collection $collection, ActionRequest $request): \Illuminate\Http\Response|array
    {
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $collection);
    }

}
