<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Actions\Dropshipping\Portfolio\Logs\StorePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\Logs\UpdatePlatformPortfolioLog;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Dropshipping\WithPortfolioErrorResponse;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WixUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreProductToWix extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithPortfolioErrorResponse;

    public function handle(Portfolio $portfolio): Portfolio
    {
        /** @var CustomerSalesChannel $customerSalesChannel */
        $customerSalesChannel = $portfolio->customerSalesChannel;

        /** @var WixUser $wixUser */
        $wixUser = $customerSalesChannel->user;

        if (!$wixUser) {
            return $portfolio;
        }

        $logs = StorePlatformPortfolioLog::run($portfolio, [
            'type' => PlatformPortfolioLogsTypeEnum::UPLOAD
        ]);

        try {
            $wixProduct = $wixUser->createProduct($this->productPayload($portfolio));

            $wixProductId = Arr::get($wixProduct, 'product.id');

            if (!$wixProductId) {
                throw new \Exception(Arr::get($wixProduct, 'message', 'Failed to create Wix product: no product id returned.'));
            }

            UpdatePortfolio::run($portfolio, [
                'platform_product_id'         => $wixProductId,
                'platform_product_variant_id' => $wixProductId,
                'errors_response'             => null
            ]);

            $this->pushImages($wixUser, $portfolio, $wixProductId);

            CheckWixPortfolio::run($portfolio);

            $portfolio->refresh();

            if ($portfolio->platform_status) {
                UpdatePlatformPortfolioLog::dispatch($logs, [
                    'status' => PlatformPortfolioLogsStatusEnum::OK
                ]);
            } else {
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => $this->portfolioErrorResponse($wixProduct)
                ]);

                UpdatePlatformPortfolioLog::dispatch($logs, [
                    'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                    'response' => json_encode($wixProduct)
                ]);
            }

            return $portfolio;
        } catch (\Throwable $e) {
            UpdatePlatformPortfolioLog::dispatch($logs, [
                'status'   => PlatformPortfolioLogsStatusEnum::FAIL,
                'response' => $e->getMessage()
            ]);

            UpdatePortfolio::run($portfolio, [
                'errors_response' => $this->portfolioErrorResponse($e->getMessage())
            ]);

            return $portfolio;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function productPayload(Portfolio $portfolio): array
    {
        return [
            'name'         => $portfolio->customer_product_name ?: $portfolio->item_name,
            'productType'  => 'physical',
            'sku'          => $portfolio->sku,
            'description'  => $portfolio->customer_description ?: '',
            'visible'      => true,
            'priceData'    => [
                'price' => (float) $portfolio->customer_price
            ],
            'manageVariants' => false,
        ];
    }

    private function pushImages(WixUser $wixUser, Portfolio $portfolio, string $wixProductId): void
    {
        $item = $portfolio->item;

        if (!$item instanceof Product) {
            return;
        }

        $media = [];

        foreach ($item->images as $image) {
            $imageUrl = UploadProductImageToWix::run($image);

            if ($imageUrl) {
                $media[] = ['url' => $imageUrl];
            }
        }

        if ($media) {
            $wixUser->addProductMedia($wixProductId, $media);
        }
    }
}
