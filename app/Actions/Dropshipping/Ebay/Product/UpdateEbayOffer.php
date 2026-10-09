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
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class UpdateEbayOffer implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Portfolio $portfolio, bool $withTitle = false): string
    {
        return $portfolio->id.($withTitle ? '-title' : '');
    }

    /**
     * The title is only sent when the customer saved the product: a price run must not overwrite a title changed on eBay.
     *
     * @return string|null the reason eBay refused the update, null when it took it or nothing was sent
     */
    public function handle(Portfolio $portfolio, bool $withTitle = false): ?string
    {
        if ($portfolio->platform_product_id == null || !$portfolio->customerSalesChannel || !$portfolio->platform_status) {
            return null;
        }

        $customerSalesChannel = $portfolio->customerSalesChannel;

        if ($customerSalesChannel->status != CustomerSalesChannelStatusEnum::OPEN) {
            return null;
        }

        $ebayUser = $customerSalesChannel->user;
        if (!$ebayUser instanceof EbayUser) {
            return null;
        }

        $platformPortfolioLog = StorePlatformPortfolioLog::run($portfolio, []);

        $offerData = [
            'description' => $portfolio->customer_description,
        ];

        if ($withTitle) {
            $offerData['title'] = $portfolio->customer_product_name;
        }

        if (
            !Arr::get($customerSalesChannel->settings, 'do_not_update_prices')
            && Arr::get($portfolio->settings, 'pricing.type') !== 'not_follow'
        ) {
            $offerData['price'] = (string) $portfolio->customer_price;
        }

        try {
            $response = $ebayUser->updateOffer(
                $portfolio->platform_product_id,
                $offerData
            );

            $error = EbayUser::ebayResponseError($response);

            if ($error) {
                UpdatePlatformPortfolioLog::dispatch($platformPortfolioLog, [
                    'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => 'E1: '.json_encode($response)
                ]);

                return $error;
            }

            UpdatePlatformPortfolioLog::dispatch($platformPortfolioLog, [
                'status' => PlatformPortfolioLogsStatusEnum::OK
            ]);
        } catch (Throwable $e) {
            UpdatePlatformPortfolioLog::dispatch($platformPortfolioLog, [
                'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => 'E2: ' . $e->getMessage()
            ]);

            return $e->getMessage();
        }

        return null;
    }
}
