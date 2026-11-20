<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class UpdateWooProduct implements ShouldBeUnique
{
    use AsAction;

    /**
     * @param  array<int, string>|null  $onlyFields  name and/or description: sends just those, and never the price
     *
     * @return bool whether WooCommerce took the update
     */
    public function handle(Portfolio $portfolio, ?array $onlyFields = null): bool
    {
        if ($portfolio->platform_product_id == null || !$portfolio->customerSalesChannel || !$portfolio->platform_status) {
            return false;
        }

        $customerSalesChannel = $portfolio->customerSalesChannel;

        if ($customerSalesChannel->status != CustomerSalesChannelStatusEnum::OPEN) {
            return false;
        }

        $wooCommerceUser = $customerSalesChannel->user;
        if (!$wooCommerceUser instanceof WooCommerceUser) {
            return false;
        }

        $productData = [
            'regular_price' => (string) $portfolio->customer_price,
            'name'          => $portfolio->customer_product_name,
            'description'   => $portfolio->customer_description
        ];

        if ($onlyFields !== null) {
            $productData = Arr::only($productData, $onlyFields);
        }

        $platformPortfolioLog = StorePlatformPortfolioLog::run($portfolio, []);

        $wooCommerceUser->setTimeout(45);

        try {
            $response = $wooCommerceUser->updateWooCommerceProduct($portfolio->platform_product_id, $productData);

            if (!Arr::get($response, 'id')) {
                UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                    'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => 'E1: '.json_encode($response)
                ]);

                return false;
            }

            UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                'status'           => PlatformPortfolioLogsStatusEnum::OK
            ]);

            return true;
        } catch (Throwable $e) {
            UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => 'E2: '.$e->getMessage()
            ]);

            return false;
        }
    }
}
