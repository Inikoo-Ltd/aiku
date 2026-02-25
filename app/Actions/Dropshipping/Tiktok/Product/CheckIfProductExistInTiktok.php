<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class CheckIfProductExistInTiktok extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(TiktokUser $tiktokUser, Portfolio $portfolio): array
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
                $result = $tiktokUser->getProduct($portfolio->platform_product_id);
            } else {
                foreach ($searchFields as $field => $value) {
                    $searchResult = $tiktokUser->getProducts([
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
