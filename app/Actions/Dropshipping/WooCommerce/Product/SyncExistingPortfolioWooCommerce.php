<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\RetinaAction;
use App\Events\UploadProductToWooCommerceProgressEvent;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SyncExistingPortfolioWooCommerce extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(WooCommerceUser $wooCommerceUser, Portfolio $portfolio)
    {
        try {
            $result = CheckAvailabilityPortfolioWooCommerce::run($wooCommerceUser, $portfolio);

            if ($result) {
                $portfolio = UpdatePortfolio::run($portfolio, [
                    'platform_product_id' => Arr::get($result, 'id')
                ]);

                UploadProductToWooCommerceProgressEvent::dispatch($wooCommerceUser, $portfolio);

                return $portfolio;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
