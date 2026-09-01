<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WixUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class CheckIfProductExistInWix extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    public function handle(WixUser $wixUser, Portfolio $portfolio): array
    {
        try {
            if ($portfolio->platform_product_id) {
                $product = Arr::get($wixUser->getProduct($portfolio->platform_product_id), 'product');

                return $product ? [$product] : [];
            }

            if (!$portfolio->sku) {
                return [];
            }

            return $wixUser->searchProductsBySku($portfolio->sku);
        } catch (\Exception $e) {
            Sentry::captureMessage('Failed to look the product up in Wix due to: '.$e->getMessage());

            return [];
        }
    }
}
