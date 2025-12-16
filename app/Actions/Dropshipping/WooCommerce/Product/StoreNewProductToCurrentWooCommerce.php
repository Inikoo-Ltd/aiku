<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\OrgAction;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreNewProductToCurrentWooCommerce extends OrgAction implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'woo';


    public function getJobUniqueId(WooCommerceUser $wooCommerceUser, Portfolio $portfolio): string
    {
        return $portfolio->id;
    }

    /**
     * @throws \Exception
     */
    public function handle(WooCommerceUser $wooCommerceUser, Portfolio $portfolio): void
    {
        $result = $wooCommerceUser->checkConnection();
        if ($result) {
            StoreWooCommerceProduct::run($wooCommerceUser, $portfolio);
        } else {
            $wooCommerceUser->customerSalesChannel->update([
                'ban_stock_update_util' => now()->addSeconds(10)
            ]);
        }
    }

    /**
     * @throws \Exception
     */
    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        /** @var WooCommerceUser $wooCommerceUser */
        $wooCommerceUser = $portfolio->customerSalesChannel->user;
        $this->initialisation($portfolio->organisation, $request);

        $this->handle($wooCommerceUser, $portfolio);
    }
}
