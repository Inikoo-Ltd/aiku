<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class UpdateEbayPortfolioThreshold
{
    use AsAction;

    public function handle(CustomerSalesChannel $customerSalesChannel, Portfolio $portfolio): void
    {
        /** @var EbayUser $ebayUser */
        $ebayUser = $customerSalesChannel->user;

        $platformPortfolioLog = StorePlatformPortfolioLog::run($portfolio, []);

        try {
            $availableQuantity = 0; // Due to threshold hit and force to be Out of Stock

            $ebayUser->updateQuantityOffer(
                $portfolio->platform_product_id,
                [
                    "availableQuantity" => $availableQuantity
                ]
            );

            $response = $ebayUser->getOffer($portfolio->platform_product_id);

            if (Arr::get($response, 'availableQuantity') == $availableQuantity) {
                UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                    'status'           => PlatformPortfolioLogsStatusEnum::OK,
                    'last_stock_value' => $availableQuantity
                ]);

                $portfolio->update([
                    'last_stock_value'      => $availableQuantity,
                    'stock_last_updated_at' => now()
                ]);
            } else {
                $rawMessage  = Arr::get($response, '0', 'Unknown error');
                $messageData = json_decode($rawMessage, true);
                if ($messageData) {
                    $message = Arr::get($messageData, 'message');
                } else {
                    $message = $rawMessage;
                }

                UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                    'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => 'E1: '.$message
                ]);
            }
        } catch (Throwable $e) {
            UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => 'E2: '.$e->getMessage()
            ]);
        }
    }
}
