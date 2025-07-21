<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:36:35 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Shopify\Product\GetShopifyProductFromPortfolio;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Portfolio\PortfolioPlatformAvailabilityOptionEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;

class DuplicatedProductFoundInPlatform extends OrgAction
{
    use WithActionUpdate;

    public function handle(CustomerSalesChannel $customerSalesChannel, Portfolio $portfolio, $modelData = []): void
    {
        $platformProductAvailabilities = [];
        if ($customerSalesChannel->user instanceof ShopifyUser) {
            $platformProductAvailabilities = GetShopifyProductFromPortfolio::run($customerSalesChannel->user, $portfolio);
        }

        if (! blank($platformProductAvailabilities)) {
            $options = [
                'options' => PortfolioPlatformAvailabilityOptionEnum::USE_EXISTING->value
            ];
            data_set($modelData, 'platform_product_availabilities', array_merge($platformProductAvailabilities, $options));

            // Extract product ID and variant ID if available
            if (isset($platformProductAvailabilities['id'])) {
                $modelData['platform_product_id'] = $platformProductAvailabilities['id'];
            }

            // Extract variant ID from the first variant if available
            if (isset($platformProductAvailabilities['variants']) && !empty($platformProductAvailabilities['variants'])) {
                $firstVariant = $platformProductAvailabilities['variants'][0];
                if (isset($firstVariant['id'])) {
                    $modelData['platform_product_variant_id'] = $firstVariant['id'];
                }
            }
        }

        $this->update($portfolio, $modelData);
    }
}
