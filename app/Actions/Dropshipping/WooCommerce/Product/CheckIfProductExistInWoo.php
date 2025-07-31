<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class CheckIfProductExistInWoo extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(WooCommerceUser $wooCommerceUser, Portfolio $portfolio): ?array
    {
        try {
            $searchFields = [
                'sku' => $portfolio->sku,
                'slug' => $portfolio->platform_handle,
                'search' => $portfolio->item_name
            ];

            $result = [];

            foreach ($searchFields as $field => $value) {
                $searchResult = $wooCommerceUser->getWooCommerceProducts([
                    $field => $value
                ]);

                if (!empty($searchResult)) {
                    $result = $searchResult;
                    break;
                }
            }

            return $result;
        } catch (\Exception $e) {
            Sentry::captureMessage("Failed to upload product due to: " . $e->getMessage());

            return null;
        }
    }
}
