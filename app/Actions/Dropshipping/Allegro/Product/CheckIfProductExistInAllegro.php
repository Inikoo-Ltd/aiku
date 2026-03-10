<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Product;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class CheckIfProductExistInAllegro extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(AllegroUser $allegroUser, Portfolio $portfolio): array
    {
        try {
            if ($portfolio->platform_product_id) {
                $searchFields = [
                    'product_id' => $portfolio->platform_product_id
                ];
            } else {
                $searchFields = [
                    'seller_skus' => [$portfolio->sku]
                ];
            }

            $result = [];

            if (Arr::has($searchFields, 'product_id')) {
                $result = $allegroUser->getProduct($portfolio->platform_product_id);
            } else {
                foreach ($searchFields as $field => $value) {
                    $searchResult = $allegroUser->getProducts([
                        $field => $value
                    ], [
                        'page_size' => 1
                    ]);

                    if (!empty($searchResult)) {
                        $result = $searchResult;
                        break;
                    }
                }
            }

            return $result;
        } catch (\Exception $e) {
            Sentry::captureMessage("Failed to upload product due to: " . $e->getMessage());

            return [];
        }
    }
}
