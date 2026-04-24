<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class UpdateEbayOffer implements ShouldBeUnique
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

        $ebayUser = $customerSalesChannel->user;
        if (!$ebayUser instanceof EbayUser) {
            return;
        }

        $platformPortfolioLog = StorePlatformPortfolioLog::run($portfolio, []);

        try {
            $ebayUser->updateOffer(
                $portfolio->platform_product_id,
                [
                    'title' => $portfolio->customer_product_name,
                    'description' => $portfolio->customer_description,
                    'price' => $portfolio->customer_price
                ]
            );

            UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                'status' => PlatformPortfolioLogsStatusEnum::OK
            ]);
        } catch (Throwable $e) {
            UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => 'E2: ' . $e->getMessage()
            ]);
        }
    }
}
