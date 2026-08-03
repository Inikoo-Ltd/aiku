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
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class UpdateEbayPortfolio implements ShouldBeUnique
{
    use AsAction;


    public string $jobQueue = 'ebay';

    public string $commandSignature = 'inventory-ebay:update {portfolioID}';

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

        $ebayUser = $customerSalesChannel->user;
        if (!$ebayUser instanceof EbayUser) {
            return;
        }

        $product = $portfolio->item;

        if (!$product instanceof Product) {
            return;
        }

        $platformPortfolioLog = StorePlatformPortfolioLog::run($portfolio, []);

        $availableQuantity = $product->available_quantity ?? 0;

        if (!$product->is_for_sale && !$product->is_bundle) {
            $availableQuantity = 0;
        }

        if ($customerSalesChannel->max_quantity_advertise > 0) {
            $availableQuantity = min($availableQuantity, $customerSalesChannel->max_quantity_advertise);
        }

        $ebayUser->setTimeout(45);
        try {
            $offer = $ebayUser->getOffer($portfolio->platform_product_id);

            if (!Arr::get($offer, 'offerId')) {
                UpdatePlatformPortfolioLog::dispatch($platformPortfolioLog, [
                    'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => 'E1: '.json_encode(Arr::get($offer, 'errors', $offer))
                ]);

                $portfolio->update([
                    'stock_last_fail_updated_at' => now()
                ]);

                if (!Arr::has($offer, 'errors')) {
                    $customerSalesChannel->update([
                        'ban_stock_update_util' => now()->addSeconds(10)
                    ]);
                }

                return;
            }

            if ((int) Arr::get($offer, 'availableQuantity') === $availableQuantity) {
                $this->markUpdated($portfolio, $customerSalesChannel, $platformPortfolioLog, $availableQuantity);

                return;
            }

            $response = $ebayUser->updateProductPriceAndQuantity([
                'requests' => [
                    [
                        'sku'    => Arr::get($offer, 'sku'),
                        'offers' => [
                            [
                                'offerId'           => $portfolio->platform_product_id,
                                'availableQuantity' => $availableQuantity
                            ]
                        ]
                    ]
                ]
            ]);

            $statusCode = (int) Arr::get($response, 'responses.0.statusCode');

            if (in_array($statusCode, [200, 204])) {
                $this->markUpdated($portfolio, $customerSalesChannel, $platformPortfolioLog, $availableQuantity);
            } else {
                $errors = Arr::get($response, 'responses.0.errors', Arr::get($response, 'errors', $response));

                UpdatePlatformPortfolioLog::dispatch($platformPortfolioLog, [
                    'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => 'E1: '.json_encode($errors)
                ]);

                $portfolio->update([
                    'stock_last_fail_updated_at' => now()
                ]);
            }
        } catch (Throwable $e) {
            UpdatePlatformPortfolioLog::dispatch($platformPortfolioLog, [
                'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => 'E2: '.$e->getMessage()
            ]);
            $portfolio->update([
                'stock_last_fail_updated_at' => now()
            ]);
            $customerSalesChannel->update([
                'ban_stock_update_util' => now()->addSeconds(10)
            ]);
        }
    }

    public function markUpdated(Portfolio $portfolio, CustomerSalesChannel $customerSalesChannel, $platformPortfolioLog, int $availableQuantity): void
    {
        UpdatePlatformPortfolioLog::dispatch($platformPortfolioLog, [
            'status'           => PlatformPortfolioLogsStatusEnum::OK,
            'last_stock_value' => $availableQuantity
        ]);

        if ($customerSalesChannel->ban_stock_update_util !== null) {
            $customerSalesChannel->update([
                'ban_stock_update_util' => null
            ]);
        }

        $portfolio->update([
            'last_stock_value'      => $availableQuantity,
            'stock_last_updated_at' => now()
        ]);
    }
}
