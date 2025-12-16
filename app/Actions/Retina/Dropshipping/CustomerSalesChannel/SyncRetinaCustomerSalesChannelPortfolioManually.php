<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 02 Jul 2025 13:35:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\Shopify\Product\UpdateInventoryInShopifyPortfolio;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateInventoryInEbayPortfolio;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateInventoryInWooPortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use Lorisleiva\Actions\ActionRequest;

class SyncRetinaCustomerSalesChannelPortfolioManually extends RetinaAction
{
    use WithActionUpdate;

    public function handle(CustomerSalesChannel $customerSalesChannel): ?CustomerSalesChannel
    {
        $platformUser = $customerSalesChannel->user;

        if (! $platformUser) {
            return null;
        }

        /** @var CustomerSalesChannel|null $result */
        $result = match ($customerSalesChannel->platform->type) {
            PlatformTypeEnum::SHOPIFY => UpdateInventoryInShopifyPortfolio::run($customerSalesChannel),
            PlatformTypeEnum::WOOCOMMERCE => UpdateInventoryInWooPortfolio::run($customerSalesChannel),
            PlatformTypeEnum::EBAY => UpdateInventoryInEbayPortfolio::run($customerSalesChannel),
            default => null
        };

        if (!$result) {
            return null;
        }

        return $customerSalesChannel;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): ?CustomerSalesChannel
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel);
    }
}
