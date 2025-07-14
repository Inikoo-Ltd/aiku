<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:25:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\StoreMultiplePortfolios;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\ActionRequest;

class StoreMultiplePortfoliosFromShopify extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, array $modelData): void
    {
        StoreMultiplePortfolios::run($shopifyUser->customerSalesChannel, $modelData);
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array']
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(ShopifyUser $shopifyUser, ActionRequest $request): void
    {
        $this->initialisation($shopifyUser->organisation, $request);
        $this->handle($shopifyUser, $this->validatedData);
    }
}
