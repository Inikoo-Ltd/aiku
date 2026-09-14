<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\OrgAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreBulkDispatchProductToCurrentWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public string $jobQueue = 'dropshipping-long';

    /**
     * @throws \Exception
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, $portfolios, array $bulkProgress): void
    {
        /** @var WooCommerceUser $wooCommerceUser */
        $wooCommerceUser = $customerSalesChannel->user;

        $needCheckConnection = !$wooCommerceUser->checkConnection();

        foreach ($portfolios as $portfolio) {
            StoreNewProductToCurrentWooCommerce::dispatch($wooCommerceUser, $portfolio, $needCheckConnection, $bulkProgress);
        }
    }
}
