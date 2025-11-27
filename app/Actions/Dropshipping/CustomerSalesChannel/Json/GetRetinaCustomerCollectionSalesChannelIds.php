<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Nov 2025 16:11:15 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel\Json;

use App\Actions\Dropshipping\CustomerSalesChannel\Json\Traits\WithCustomerSalesChannelPortfolioQuery;
use App\Actions\RetinaAction;
use App\Models\Catalogue\Collection;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;

class GetRetinaCustomerCollectionSalesChannelIds extends RetinaAction
{
    use WithCustomerSalesChannelPortfolioQuery;

    public function handle(Customer $customer, Collection $collection): array
    {
        $productIds = $this->getProductIdsFromCollection($collection);

        // Delegate to shared query helper in trait
        return $this->buildCustomerSalesChannelIdsForProductIds($customer, $productIds);
    }

    public function asController(Collection $collection, ActionRequest $request): \Illuminate\Http\Response|array
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $collection);
    }

}
