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


        if ($portfolio->customerSalesChannel->status != CustomerSalesChannelStatusEnum::OPEN) {
            return;
        }


        $wooCommerceUser = $portfolio->customerSalesChannel->user;
        if (!$wooCommerceUser instanceof WooCommerceUser) {
            return;
        }


        $platformPortfolioLog = StorePlatformPortfolioLog::run($portfolio, []);
        /** @var Product $product */
        $product = $portfolio->item;


        $availableQuantity = $product->available_quantity ?? 0;

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
                $portfolio->update([
                    'last_stock_value'      => $availableQuantity,
                    'last_stock_updated_at' => now()
                ]);
            } else {
                $response = json_decode($response[0], true);
                UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                    'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => Arr::get($response, 'message') ?? __('Unknown')
                ]);
                $portfolio->update([
                    'stock_last_fail_updated_at' => now()
                ]);
            }
        } catch (Throwable $e) {
            UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => $e->getMessage()
            ]);
            $portfolio->update([
                'stock_last_fail_updated_at' => now()
            ]);
        }
    }


}
