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
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class UpdateWooProduct implements ShouldBeUnique
{
    use AsAction;

    public function handle(Portfolio $portfolio): void
    {
        if ($portfolio->platform_product_id == null || !$portfolio->customerSalesChannel || !$portfolio->platform_status) {
            return;
        }

        $customerSalesChannel = $portfolio->customerSalesChannel;

        if ($customerSalesChannel->status != CustomerSalesChannelStatusEnum::OPEN) {
            return;
        }

        $wooCommerceUser = $customerSalesChannel->user;
        if (!$wooCommerceUser instanceof WooCommerceUser) {
            return;
        }

        $platformPortfolioLog = StorePlatformPortfolioLog::run($portfolio, []);

        $wooCommerceUser->setTimeout(45);
        try {
            $wooCommerceUser->updateWooCommerceProduct(
                $portfolio->platform_product_id,
                [
                    'price' => $portfolio->customer_price,
                    'title' => $portfolio->customer_product_name,
                    'description' => $portfolio->customer_description
                ]
            );

            UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                'status'           => PlatformPortfolioLogsStatusEnum::OK
            ]);
        } catch (Throwable $e) {
            UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => 'E2: '.$e->getMessage()
            ]);
        }
    }
}
