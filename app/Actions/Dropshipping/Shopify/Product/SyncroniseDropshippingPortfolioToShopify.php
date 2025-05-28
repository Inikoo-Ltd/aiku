<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class SyncroniseDropshippingPortfolioToShopify extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser, Portfolio $portfolio): void
    {
        try {
            $images = [];
            foreach ($portfolio->item->images as $image) {
                $images[] = [
                    "attachment" => $image->getBase64Image()
                ];
            }
        } catch (\Exception $e) {
            Sentry::captureException($e);
        }

        $body = [
            "product" => [
                "id" => $portfolio->item->id,
                "title" => $portfolio->item->name,
                "body_html" => $portfolio->item->description,
                "vendor" => $portfolio->item->shop->name,
                "product_type" => $portfolio->item->family?->name,
                "images" => $images
            ]
        ];

        RequestApiUploadProductToShopify::dispatch($shopifyUser, $portfolio, $body);
    }

    public function asController(ShopifyUser $shopifyUser, Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($shopifyUser, $portfolio);
    }
}
