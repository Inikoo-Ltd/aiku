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
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class UpdateWooPortfolio
{
    use AsAction;


    public string $jobQueue = 'woo';

    public string $commandSignature = 'inventory-woo:update {portfolioID}';

    public function asCommand(Command $command): void
    {
        $this->handle(
            $command->argument('portfolioID')
        );
    }

    public function getJobUniqueId(int $portfolioID): string
    {
        return $portfolioID;
    }


    public function handle(int $portfolioID): void
    {
        $portfolio = Portfolio::find($portfolioID);


        if (!$portfolio || $portfolio->platform_product_id == null || !$portfolio->customerSalesChannel || !$portfolio->platform_status) {
            return;
        }

        $customerSalesChannel = $portfolio->customerSalesChannel;

        if ($customerSalesChannel->status != CustomerSalesChannelStatusEnum::OPEN) {
            return;
        }


        if ($customerSalesChannel->ban_stock_update_util && $customerSalesChannel->ban_stock_update_util->gt(now())) {
            return;
        }


        $wooCommerceUser = $customerSalesChannel->user;
        if (!$wooCommerceUser instanceof WooCommerceUser) {
            return;
        }


        $platformPortfolioLog = StorePlatformPortfolioLog::run($portfolio, []);
        /** @var Product $product */
        $product = $portfolio->item;


        $availableQuantity = $product->available_quantity ?? 0;


        $wooCommerceUser->setTimeout(45);
        try {
            $response = $wooCommerceUser->updateWooCommerceProduct(
                $portfolio->platform_product_id,
                [
                    "stock_quantity" => $availableQuantity,
                ]
            );


            if (Arr::get($response, 'stock_quantity') == $availableQuantity) {
                UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                    'status'           => PlatformPortfolioLogsStatusEnum::OK,
                    'last_stock_value' => $availableQuantity
                ]);
                $customerSalesChannel->update([
                    'ban_stock_update_util' => null
                ]);

                $portfolio->update([
                    'last_stock_value'      => $availableQuantity,
                    'stock_last_updated_at' => now()
                ]);
            } else {
                $ban = true;

                $rawMessage  = Arr::get($response, '0', 'Unknown error');
                $messageData = json_decode($rawMessage, true);
                if ($messageData) {
                    $message = Arr::get($messageData, 'message');
                    if (Arr::get($messageData, 'code') == 'rest_invalid_param' || Arr::get($messageData, 'code') == 'woocommerce_rest_product_invalid_id' || Arr::get($messageData, 'data.status') == 404 || Arr::get($messageData, 'data.status') == 400) {
                        $ban = false;
                    }
                } else {
                    $message = $rawMessage;
                }


                UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                    'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => 'E1: '.$message
                ]);

                if ($ban) {
                    $customerSalesChannel->update([
                        'ban_stock_update_util' => now()->addHours(3),
                    ]);
                }

                $portfolio->update([
                    'stock_last_fail_updated_at' => now()
                ]);
            }
        } catch (Throwable $e) {
            UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => 'E2: '.$e->getMessage()
            ]);
            $portfolio->update([
                'stock_last_fail_updated_at' => now()
            ]);
            $customerSalesChannel->update([
                'ban_stock_update_util' => now()->addHours(3),
            ]);
        }
    }


}
