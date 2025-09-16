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
            $productID = $portfolio->sku;

            $result = $ebayUser->getProduct($productID);

            $data = $portfolio->data;
            data_set($data, 'ebay_product', $result);

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
