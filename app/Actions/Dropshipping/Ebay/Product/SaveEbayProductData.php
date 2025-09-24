<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Jul 2025 08:28:03 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Exception;
use Illuminate\Support\Arr;
use Sentry;

class SaveEbayProductData extends RetinaAction
{
    use WithActionUpdate;

    public string $jobQueue = 'ebay';
    public int $jobBackoff = 5;


    public function handle(Portfolio $portfolio): ?array
    {
        /** @var EbayUser $ebayUser */
        $ebayUser = $portfolio->customerSalesChannel->user;

        try {
            $productID = $portfolio->platform_product_id;

            $offer = $ebayUser->getOffer($productID);
            $result = $ebayUser->getProduct(Arr::get($offer, 'sku'));
            $data = $portfolio->data;

            data_set($data, 'ebay_product', [
                'offerId' => Arr::get($offer, 'id'),
                'sku' => Arr::get($result, 'sku'),
                'name' => Arr::get($result, 'product.title'),
                'images' => Arr::get($result, 'product.imageUrls'),
            ]);

            $dataToUpdate = [
                'data' => $data
            ];

            UpdatePortfolio::run($portfolio, $dataToUpdate);

            return $result;
        } catch (Exception $e) {
            Sentry::captureException($e);

            return null;
        }
    }
}
