<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Jul 2025 08:28:03 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Exception;
use Sentry;

class SaveWooProductData extends RetinaAction
{
    use WithActionUpdate;

    public string $jobQueue = 'woo';
    public int $jobBackoff = 5;


    public function handle(Portfolio $portfolio): ?array
    {
        /** @var WooCommerceUser $wooCommerce */
        $wooCommerce = $portfolio->customerSalesChannel->user;

        try {
            $productID = $portfolio->platform_product_id;

            $result = $wooCommerce->getWooCommerceProduct($productID);

            $data = $portfolio->data;
            data_set($data, 'woo_product', $result);

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
