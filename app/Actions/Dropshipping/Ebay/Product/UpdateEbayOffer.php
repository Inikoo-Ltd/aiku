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
     * Never sends a description: nobody authorised us to change the descriptions on a customer's eBay listings.
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

        $sendsPrice = !Arr::get($customerSalesChannel->settings, 'do_not_update_prices')
            && Arr::get($portfolio->settings, 'pricing.type') !== 'not_follow';

        if (!$withTitle && !$sendsPrice) {
            return null;
        }

        $platformPortfolioLog = StorePlatformPortfolioLog::run($portfolio, []);

        try {
            [$response, $error] = $this->pushPriceAndTitle($ebayUser, $portfolio, $sendsPrice, $withTitle);

            if ($error) {
                UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                    'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => 'E1: '.json_encode($response)
                ]);

                return $error;
            }

            UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                'status' => PlatformPortfolioLogsStatusEnum::OK
            ]);
        } catch (Throwable $e) {
            UpdatePlatformPortfolioLog::run($platformPortfolioLog, [
                'status' => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => 'E2: ' . $e->getMessage()
            ]);

            return $e->getMessage();
        }

        return null;
    }

    /**
     * The offer itself is never PUT: that full replace needs a description and eBay refuses offers held without one.
     * The price goes through the bulk endpoint, which changes the price alone, and the title lives on the inventory item.
     *
     * @return array{0: mixed, 1: string|null} eBay's response and the reason it refused, if it did
     */
    public function pushPriceAndTitle(EbayUser $ebayUser, Portfolio $portfolio, bool $sendsPrice, bool $withTitle): array
    {
        $offer = $ebayUser->getOffer($portfolio->platform_product_id);

        if (!Arr::get($offer, 'offerId')) {
            return [$offer, EbayUser::ebayResponseError($offer) ?? 'Offer not found on eBay'];
        }

        if ($sendsPrice) {
            $response = $ebayUser->updateProductPriceAndQuantity([
                'requests' => [
                    [
                        'sku'    => Arr::get($offer, 'sku'),
                        'offers' => [
                            [
                                'offerId' => $portfolio->platform_product_id,
                                'price'   => [
                                    'value'    => (string) $portfolio->customer_price,
                                    'currency' => Arr::get($offer, 'pricingSummary.price.currency') ?? $portfolio->shop->currency->code,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            if (!in_array((int) Arr::get($response, 'responses.0.statusCode'), [200, 204])) {
                return [$response, EbayUser::ebayResponseError(['errors' => UpdateEbayPortfolio::bulkUpdateErrors($response)]) ?? 'Price update refused by eBay'];
            }
        }

        if ($withTitle && filled($portfolio->customer_product_name) && filled(Arr::get($offer, 'sku'))) {
            $titleResponse = $ebayUser->updateInventoryItemTitle(Arr::get($offer, 'sku'), $portfolio->customer_product_name);

            if ($error = EbayUser::ebayResponseError($titleResponse)) {
                return [$titleResponse, $error];
            }
        }

        return [null, null];
    }
}
