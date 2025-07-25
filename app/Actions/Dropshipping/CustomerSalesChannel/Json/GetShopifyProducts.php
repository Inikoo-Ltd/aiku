<?php

/*
 * author Arya Permana - Kirin
 * created on 10-07-2025-10h-29m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\CustomerSalesChannel\Json;

use App\Actions\Dropshipping\Shopify\Product\FindShopifyProductVariant;
use App\Actions\OrgAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;

class GetShopifyProducts extends OrgAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, string $searchInput): array|null
    {
        return FindShopifyProductVariant::run($customerSalesChannel, $searchInput);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request)
    {
        $this->initialisation($customerSalesChannel->organisation, $request);

        return $this->handle($customerSalesChannel, $request->get('query', ''));
    }
}
